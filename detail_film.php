<?php
session_start();
include 'config.php';

$id_film = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_film) { header('Location: index.php'); exit; }

$film = $conn->query("SELECT * FROM film WHERE id_film = $id_film")->fetch_assoc();
if (!$film) { header('Location: index.php'); exit; }

$isComingSoon = (strtolower($film['status']) === 'coming soon');

$jadwals = $conn->query("SELECT id_jadwal, tanggal, jam, harga, studio FROM jadwal WHERE id_film = $id_film ORDER BY tanggal, jam");
$ulasan = $conn->query("SELECT u.*, us.nama FROM ulasan u JOIN users us ON u.id_user = us.id_user WHERE u.id_film = $id_film ORDER BY u.created_at DESC");

$ratingData = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM ulasan WHERE id_film = $id_film")->fetch_assoc();
$avgRating = $ratingData['avg_rating'] ? number_format($ratingData['avg_rating'], 1) : '0.0';
$totalUlasan = $ratingData['total'];

$loggedIn = isset($_SESSION['id_user']);
$role = $_SESSION['role'] ?? '';
$isUser = $loggedIn && $role === 'user';

// ===== CEK STATUS NONTON USER =====
$sudahPesan          = false;   // pernah pesan Lunas
$sudahTayang         = false;   // jam tayang sudah lewat (boleh ulasan)
$idPemesananUser     = 0;
$jadwalTayangUser    = null;    // untuk pesan "kapan bisa ulasan"
$jadwalAkanTayang    = null;

if ($isUser) {
    $id_user = (int)$_SESSION['id_user'];

    // Ambil pemesanan Lunas terakhir untuk film ini + info jadwal
    $cek = $conn->query("
        SELECT p.id_pemesanan, j.tanggal, j.jam 
        FROM pemesanan p 
        JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
        WHERE p.id_user = $id_user 
          AND j.id_film = $id_film 
          AND p.status = 'Lunas'
        ORDER BY p.id_pemesanan DESC 
        LIMIT 1
    ");

    if ($cek && $cek->num_rows > 0) {
        $row = $cek->fetch_assoc();
        $jadwal_dt = strtotime($row['tanggal'] . ' ' . $row['jam']);
        $sudahPesan = true;

        if (time() >= $jadwal_dt) {
            // Sudah lewat jam tayang → boleh ulasan
            $sudahTayang = true;
            $idPemesananUser = (int)$row['id_pemesanan'];
            $jadwalTayangUser = $jadwal_dt;
        } else {
            // Belum tayang → simpan info untuk tampilkan pesan
            $jadwalAkanTayang = $jadwal_dt;
        }
    }
}

// Cek sudah pernah ulasan
$sudahUlasan = false;
if ($isUser) {
    $cekUlasan = $conn->query("SELECT id_ulasan FROM ulasan WHERE id_user = " . (int)$_SESSION['id_user'] . " AND id_film = $id_film");
    $sudahUlasan = $cekUlasan->num_rows > 0;
}

// Ambil ID trailer YouTube
$trailerId = trim($film['trailer'] ?? '');
if (!empty($trailerId)) {
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([a-zA-Z0-9_-]{11})/', $trailerId, $matches)) {
        $trailerId = $matches[1];
    }
    $trailerId = preg_replace('/[^a-zA-Z0-9_-]/', '', $trailerId);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($film['judul']); ?> - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0B1120;
            color: #F1F5F9;
            display: flex;
            min-height: 100vh;
        }
        .main-content { flex: 1; padding: 32px 38px; min-width: 0; }

        .back-link {
            color: #94A3B8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: 0.25s;
        }
        .back-link:hover { color: #60A5FA; transform: translateX(-3px); }

        /* ===== HERO SECTION ===== */
        .film-hero {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 36px;
            background: linear-gradient(145deg, #111A2E 0%, #0F1729 100%);
            border-radius: 24px;
            padding: 36px;
            border: 1px solid #1E293B;
            margin-bottom: 36px;
            position: relative;
            overflow: hidden;
        }
        .film-hero::before {
            content: '';
            position: absolute;
            top: -40%; right: -10%;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.15), transparent 70%);
            pointer-events: none;
        }
        .film-hero::after {
            content: '';
            position: absolute;
            bottom: -30%; left: -5%;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(251,191,36,0.08), transparent 70%);
            pointer-events: none;
        }

        .film-poster-wrap {
            position: relative;
            z-index: 1;
            align-self: start;
        }
        .film-poster {
            width: 100%;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
            border: 1px solid rgba(59,130,246,0.2);
            aspect-ratio: 2/3;
            background: #0B1120;
        }

        .film-info {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        .film-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 18px;
            width: fit-content;
        }
        .film-status-badge.now-showing {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.4);
            color: #34D399;
        }
        .film-status-badge.coming-soon {
            background: rgba(251,191,36,0.15);
            border: 1px solid rgba(251,191,36,0.4);
            color: #FCD34D;
        }
        .film-status-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }

        .film-title {
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 16px;
            color: #fff;
            line-height: 1.1;
        }

        .film-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }
        .film-meta .badge {
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-blue {
            background: rgba(59,130,246,0.15);
            color: #60A5FA;
            border: 1px solid rgba(59,130,246,0.25);
        }
        .badge-dark {
            background: rgba(148,163,184,0.1);
            color: #CBD5E1;
            border: 1px solid rgba(148,163,184,0.15);
        }
        .badge-gold {
            background: rgba(251,191,36,0.15);
            color: #FCD34D;
            border: 1px solid rgba(251,191,36,0.3);
        }

        /* ===== SINOPSIS ===== */
        .sinopsis-section {
            margin-bottom: 28px;
        }
        .sinopsis-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #60A5FA;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .sinopsis-label i { font-size: 0.85rem; }
        .sinopsis-text {
            color: #94A3B8;
            line-height: 1.85;
            font-size: 0.95rem;
            max-width: 680px;
        }

        /* ===== TRAILER ===== */
        .trailer-section {
            margin-top: auto;
        }
        .trailer-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #EF4444;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .trailer-label i { font-size: 0.9rem; }
        .trailer-frame {
            position: relative;
            width: 100%;
            max-width: 640px;
            aspect-ratio: 16/9;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #1E293B;
            background: #000;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        }
        .trailer-frame iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* ===== SECTION TITLE ===== */
        .sec-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.2px;
        }
        .sec-title i {
            width: 38px; height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.88rem; color: #fff;
            box-shadow: 0 8px 20px rgba(59,130,246,0.35);
        }
        .sec-title.gold i {
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            box-shadow: 0 8px 20px rgba(251,191,36,0.35);
        }
        .sec-title.purple i {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            box-shadow: 0 8px 20px rgba(139,92,246,0.35);
        }

        /* ===== JADWAL ===== */
        .jadwal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 18px;
            margin-bottom: 44px;
        }
        .jadwal-card {
            background: linear-gradient(145deg, #111A2E 0%, #0F1729 100%);
            border: 1px solid #1E293B;
            border-radius: 18px;
            padding: 22px 24px;
            transition: all 0.35s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .jadwal-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #3B82F6, transparent);
            opacity: 0;
            transition: 0.3s;
        }
        .jadwal-card:hover {
            border-color: #3B82F6;
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(59,130,246,0.2);
        }
        .jadwal-card:hover::before { opacity: 1; }

        .jadwal-date {
            font-weight: 700;
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jadwal-date i { color: #3B82F6; font-size: 0.85rem; }
        .jadwal-time {
            color: #60A5FA;
            font-weight: 800;
            font-size: 1.3rem;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        .jadwal-studio {
            color: #64748B;
            font-size: 0.85rem;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jadwal-studio i { color: #8B5CF6; }
        .jadwal-price {
            font-weight: 800;
            font-size: 1.25rem;
            color: #FBBF24;
            margin-bottom: 16px;
            letter-spacing: -0.3px;
        }
        .btn-book {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 13px 0;
            font-weight: 700;
            font-size: 0.88rem;
            width: 100%;
            transition: 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
            font-family: inherit;
            cursor: pointer;
        }
        .btn-book:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(59,130,246,0.5);
            color: #fff;
        }
        .btn-book:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
            background: #1E293B;
            color: #94A3B8;
        }
        .empty-jadwal {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
            grid-column: 1/-1;
            background: #111A2E;
            border: 1px dashed #1E293B;
            border-radius: 18px;
        }
        .empty-jadwal i { font-size: 2.8rem; opacity: 0.2; display: block; margin-bottom: 14px; }

        /* ===== ULASAN ===== */
        .ulasan-section { margin-top: 20px; }
        .ulasan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 26px;
        }
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 20px;
            background: linear-gradient(145deg, #111A2E 0%, #0F1729 100%);
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 16px 28px;
        }
        .rating-big {
            font-size: 2.8rem;
            font-weight: 800;
            color: #FBBF24;
            line-height: 1;
            letter-spacing: -2px;
        }
        .rating-stars {
            font-size: 1rem;
            color: #FBBF24;
            letter-spacing: 3px;
            margin-bottom: 4px;
        }
        .rating-total {
            color: #64748B;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .ulasan-form {
            background: linear-gradient(145deg, #111A2E 0%, #0F1729 100%);
            border: 1px solid #1E293B;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 26px;
        }
        .ulasan-form h6 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #fff;
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
        }
        .ulasan-form h6 i { color: #60A5FA; }

        .form-control-custom {
            background: #0B1120;
            border: 1.5px solid #1E293B;
            color: #F1F5F9;
            border-radius: 12px;
            padding: 13px 16px;
            width: 100%;
            margin-bottom: 18px;
            font-family: inherit;
            transition: 0.25s;
            outline: none;
            resize: vertical;
            font-size: 0.9rem;
        }
        .form-control-custom::placeholder { color: #475569; }
        .form-control-custom:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
        }
        .form-label-custom {
            display: block;
            color: #94A3B8;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .star-select {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 20px;
        }
        .star-select input[type="radio"] { display: none; }
        .star-select label {
            font-size: 2.2rem;
            color: #1E293B;
            cursor: pointer;
            transition: 0.2s;
            line-height: 1;
        }
        .star-select input[type="radio"]:checked ~ label,
        .star-select label:hover,
        .star-select label:hover ~ label {
            color: #FBBF24;
            text-shadow: 0 0 20px rgba(251,191,36,0.5);
        }
        .btn-submit-ulasan {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff;
            border: none;
            border-radius: 11px;
            padding: 13px 32px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.3s;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
        }
        .btn-submit-ulasan:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(59,130,246,0.5);
        }

        .ulasan-list { display: grid; gap: 16px; }
        .ulasan-card {
            background: linear-gradient(145deg, #111A2E 0%, #0F1729 100%);
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 22px 26px;
            transition: 0.3s;
        }
        .ulasan-card:hover {
            border-color: #2A3854;
            transform: translateX(4px);
        }
        .ulasan-card .nama {
            font-weight: 700;
            color: #fff;
            font-size: 0.95rem;
        }
        .ulasan-card .bintang {
            color: #FBBF24;
            margin-left: 12px;
            font-size: 0.95rem;
            letter-spacing: 2px;
        }
        .ulasan-card .komentar {
            color: #94A3B8;
            margin-top: 10px;
            line-height: 1.75;
            font-size: 0.92rem;
        }
        .ulasan-card .tanggal {
            color: #475569;
            font-size: 0.75rem;
            margin-top: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .empty-ulasan {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
            background: #111A2E;
            border: 1px dashed #1E293B;
            border-radius: 18px;
        }
        .empty-ulasan i { font-size: 2.8rem; opacity: 0.2; display: block; margin-bottom: 14px; }

        .alert-info-custom {
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.3);
            color: #93C5FD;
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-info-custom i { font-size: 1.1rem; flex-shrink: 0; }
        .alert-info-custom a { color: #60A5FA; font-weight: 700; text-decoration: none; }
        .alert-info-custom a:hover { text-decoration: underline; }

        .alert-warning-custom {
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.3);
            color: #FCD34D;
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-warning-custom i { flex-shrink: 0; }

        .alert-success-custom {
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.3);
            color: #6EE7B7;
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success-custom i { flex-shrink: 0; }

        @media (max-width: 992px) {
            .film-hero { grid-template-columns: 1fr; padding: 28px; gap: 28px; }
            .film-poster-wrap { max-width: 280px; margin: 0 auto; }
            .film-title { font-size: 2rem; }
            .trailer-frame { max-width: 100%; }
        }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
        }
        @media (max-width: 480px) {
            .jadwal-grid { grid-template-columns: 1fr; }
            .film-title { font-size: 1.6rem; }
            .rating-summary { padding: 12px 20px; }
            .rating-big { font-size: 2.2rem; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke SHOW</a>

        <!-- ===== FILM HERO ===== -->
        <div class="film-hero">
            <div class="film-poster-wrap">
                <img src="assets/img/<?= htmlspecialchars($film['poster'] ?? 'default.jpg'); ?>" class="film-poster" alt="Poster" onerror="this.src='assets/img/default.jpg'">
            </div>
            <div class="film-info">
                <?php if($isComingSoon): ?>
                    <div class="film-status-badge coming-soon">
                        <span class="dot"></span> Coming Soon
                    </div>
                <?php else: ?>
                    <div class="film-status-badge now-showing">
                        <span class="dot"></span> Now Showing
                    </div>
                <?php endif; ?>

                <h1 class="film-title"><?= htmlspecialchars($film['judul']); ?></h1>
                
                <div class="film-meta">
                    <span class="badge badge-dark"><i class="fas fa-tag"></i> <?= htmlspecialchars($film['genre']); ?></span>
                    <span class="badge badge-dark"><i class="fas fa-clock"></i> <?= htmlspecialchars($film['durasi']); ?></span>
                    <span class="badge badge-blue"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($film['rating_usia']); ?></span>
                    <span class="badge badge-gold"><i class="fas fa-star"></i> <?= $avgRating; ?> (<?= $totalUlasan; ?> ulasan)</span>
                </div>

                <div class="sinopsis-section">
                    <div class="sinopsis-label"><i class="fas fa-book-open"></i> Sinopsis</div>
                    <p class="sinopsis-text"><?= nl2br(htmlspecialchars($film['sinopsis'])); ?></p>
                </div>

                <?php if(!empty($trailerId)): ?>
                    <div class="trailer-section">
                        <div class="trailer-label"><i class="fab fa-youtube"></i> Trailer Resmi</div>
                        <div class="trailer-frame">
                            <iframe 
                                src="https://www.youtube.com/embed/<?= htmlspecialchars($trailerId); ?>" 
                                title="Trailer <?= htmlspecialchars($film['judul']); ?>"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                referrerpolicy="strict-origin-when-cross-origin"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== JADWAL TAYANG ===== -->
        <h5 class="sec-title"><i class="fas fa-calendar-alt"></i> Jadwal Tayang</h5>
        <div class="jadwal-grid">
            <?php if ($jadwals->num_rows > 0): ?>
                <?php while($j = $jadwals->fetch_assoc()): 
                    $jadwal_dt_card = strtotime($j['tanggal'] . ' ' . $j['jam']);
                    $sudahMulai = (time() >= $jadwal_dt_card);
                ?>
                <div class="jadwal-card">
                    <div class="jadwal-date"><i class="fas fa-calendar-day"></i> <?= date('d M Y', strtotime($j['tanggal'])); ?></div>
                    <div class="jadwal-time"><?= date('H:i', strtotime($j['jam'])); ?> WIB</div>
                    <div class="jadwal-studio"><i class="fas fa-couch"></i> Studio <?= htmlspecialchars($j['studio']); ?></div>
                    <div class="jadwal-price">Rp <?= number_format($j['harga'], 0, ',', '.'); ?></div>
                    <?php if($isComingSoon): ?>
                        <button class="btn-book" disabled>
                            <i class="fas fa-clock"></i> Coming Soon
                        </button>
                    <?php elseif($sudahMulai): ?>
                        <button class="btn-book" disabled>
                            <i class="fas fa-hourglass-end"></i> Sudah Berlalu
                        </button>
                    <?php elseif($isUser): ?>
                        <a href="pilih_kursi.php?id_jadwal=<?= $j['id_jadwal']; ?>" class="btn-book">
                            <i class="fas fa-ticket-alt"></i> Pilih Kursi
                        </a>
                    <?php elseif($loggedIn): ?>
                        <button class="btn-book" disabled>
                            <i class="fas fa-lock"></i> Hanya User
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn-book">
                            <i class="fas fa-sign-in-alt"></i> Login untuk Pesan
                        </a>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-jadwal">
                    <i class="fas fa-calendar-times"></i>
                    <p>Belum ada jadwal untuk film ini.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== ULASAN ===== -->
        <div class="ulasan-section">
            <div class="ulasan-header">
                <h5 class="sec-title" style="margin-bottom:0;"><i class="fas fa-comments"></i> Ulasan Pengguna</h5>
                <div class="rating-summary">
                    <div class="rating-big"><?= $avgRating; ?></div>
                    <div>
                        <div class="rating-stars">
                            <?php 
                            $fullStars = floor($avgRating);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $fullStars ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <div class="rating-total"><?= $totalUlasan; ?> ulasan</div>
                    </div>
                </div>
            </div>

            <?php if($isUser): ?>
                <?php if($sudahUlasan): ?>
                    <!-- Sudah pernah ulasan -->
                    <div class="alert-success-custom">
                        <i class="fas fa-check-circle"></i> Anda sudah memberikan ulasan untuk film ini. Terima kasih!
                    </div>

                <?php elseif(!$sudahPesan): ?>
                    <!-- Belum pesan sama sekali -->
                    <div class="alert-info-custom">
                        <i class="fas fa-info-circle"></i> Anda harus menonton film ini terlebih dahulu (memesan tiket) untuk bisa memberi ulasan.
                    </div>

                <?php elseif($sudahPesan && !$sudahTayang): ?>
                    <!-- Sudah pesan, tapi belum tayang -->
                    <div class="alert-warning-custom">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Ulasan belum bisa diberikan.</strong><br>
                            Anda baru bisa memberi ulasan setelah film selesai/sedang ditayangkan 
                            (<strong><?= date('d M Y H:i', $jadwalAkanTayang); ?> WIB</strong>).
                        </div>
                    </div>

                <?php elseif($sudahPesan && $sudahTayang): ?>
                    <!-- Sudah tayang → boleh ulasan -->
                    <div class="ulasan-form">
                        <h6><i class="fas fa-pen"></i> Beri Ulasan Anda</h6>
                        <form action="tambahan_ulasan.php" method="POST">
                            <input type="hidden" name="id_film" value="<?= $id_film; ?>">
                            <input type="hidden" name="id_pemesanan" value="<?= $idPemesananUser; ?>">

                            <label class="form-label-custom">Rating</label>
                            <div class="star-select">
                                <input type="radio" name="rating" value="5" id="star5" required><label for="star5">★</label>
                                <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                                <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                                <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                                <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                            </div>

                            <label class="form-label-custom">Komentar</label>
                            <textarea name="komentar" class="form-control-custom" rows="3" placeholder="Tulis pengalaman menonton Anda..." required></textarea>

                            <button type="submit" class="btn-submit-ulasan">
                                <i class="fas fa-paper-plane"></i> Kirim Ulasan
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php elseif($loggedIn): ?>
                <div class="alert-warning-custom">
                    <i class="fas fa-info-circle"></i> Hanya <strong>User</strong> yang dapat memberikan ulasan.
                </div>
            <?php else: ?>
                <div class="alert-info-custom">
                    <i class="fas fa-info-circle"></i> <a href="login.php">Login</a> sebagai user untuk memberi ulasan.
                </div>
            <?php endif; ?>

            <!-- LIST ULASAN -->
            <div class="ulasan-list">
                <?php if ($ulasan && $ulasan->num_rows > 0): ?>
                    <?php while($u = $ulasan->fetch_assoc()): ?>
                    <div class="ulasan-card">
                        <div>
                            <span class="nama"><?= htmlspecialchars($u['nama']); ?></span>
                            <span class="bintang"><?= str_repeat('★', $u['rating']); ?><?= str_repeat('☆', 5 - $u['rating']); ?></span>
                        </div>
                        <div class="komentar"><?= nl2br(htmlspecialchars($u['komentar'])); ?></div>
                        <div class="tanggal">
                            <i class="fas fa-clock"></i> <?= date('d M Y', strtotime($u['created_at'])); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-ulasan">
                        <i class="fas fa-comment-slash"></i>
                        <p>Belum ada ulasan. Jadi yang pertama!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>