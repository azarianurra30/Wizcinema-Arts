<?php
// ===== Secure session cookie params SEBELUM session_start =====
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['id_user'];

// ===== PERBAIKAN #1: Verifikasi user masih ada di DB =====
// Kalau akun sudah dihapus admin, session lama tidak boleh tetap aktif.
$stmt = $conn->prepare("SELECT id_user, nama, username, role, foto_profil, bio FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    // User tidak ada di DB → bersihkan session & redirect
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// ===== PERBAIKAN #2: Sinkronkan session dengan data terbaru dari DB =====
// Kalau user ganti nama/foto/role di tab lain, session ikut update otomatis.
$_SESSION['nama']     = $currentUser['nama'];
$_SESSION['username'] = $currentUser['username'];
$_SESSION['role']     = $currentUser['role'] ?? 'user';
$_SESSION['foto']     = $currentUser['foto_profil'] ?? 'default.png';
$_SESSION['bio']      = $currentUser['bio'] ?? '';

$role = $_SESSION['role'];

// ===== PERBAIKAN #3: Generate CSRF token =====
// Dipakai nanti untuk klaim promo (fetch ke klaim_promo.php).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ===== Data untuk halaman =====
$klaimSaya = [];
$qKlaim = $conn->query("SELECT id_promo, status FROM user_promo WHERE id_user = $userId");
if ($qKlaim) {
    while ($k = $qKlaim->fetch_assoc()) {
        $klaimSaya[(int)$k['id_promo']] = $k['status'];
    }
}

$films = $conn->query("SELECT * FROM film WHERE LOWER(status) = 'now showing' ORDER BY judul ASC");
if ($films->num_rows == 0) {
    $films = $conn->query("SELECT * FROM film ORDER BY judul ASC");
}
$comingSoon = $conn->query("SELECT * FROM film WHERE LOWER(status) = 'coming soon' ORDER BY judul ASC");
$promos = $conn->query("SELECT * FROM promo WHERE status = 'Aktif' ORDER BY created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHOW - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .section-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }
        .section-title i {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: #fff;
        }
        .ic-gold { background: linear-gradient(135deg, #FBBF24, #F59E0B); box-shadow: 0 8px 20px rgba(251,191,36,0.35); }
        .ic-fire { background: linear-gradient(135deg, #EF4444, #DC2626); box-shadow: 0 8px 20px rgba(239,68,68,0.35); }
        .ic-clock { background: linear-gradient(135deg, #8B5CF6, #7C3AED); box-shadow: 0 8px 20px rgba(139,92,246,0.35); }

        /* ===== PROMO (COMPACT) ===== */
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 14px;
            margin-bottom: 40px;
        }
        .promo-card {
            position: relative;
            border-radius: 14px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #4C1D95 0%, #6D28D9 50%, #7C3AED 100%);
            box-shadow: 0 10px 25px rgba(109,40,217,0.3);
            overflow: hidden;
            transition: 0.3s;
            isolation: isolate;
        }
        .promo-card::after {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 150px; height: 150px;
            background: radial-gradient(circle, rgba(251,191,36,0.3), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .promo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 40px rgba(109,40,217,0.5);
        }
        .promo-card.claimed {
            background: linear-gradient(135deg, #064E3B 0%, #047857 50%, #059669 100%);
            box-shadow: 0 10px 25px rgba(5,150,105,0.3);
        }

        /* ===== VOUCHER PROMO (HIJAU) ===== */
        .promo-card.voucher-promo {
            background: linear-gradient(135deg, #065F46 0%, #059669 50%, #10B981 100%);
            box-shadow: 0 10px 25px rgba(16,185,129,0.35);
        }
        .promo-card.voucher-promo::after {
            background: radial-gradient(circle, rgba(251,191,36,0.4), transparent 70%);
        }
        .promo-card.voucher-promo:hover {
            box-shadow: 0 18px 40px rgba(16,185,129,0.55);
        }
        .promo-card.voucher-promo .promo-badge i {
            color: #FCD34D;
            animation: giftPulse 2s ease-in-out infinite;
        }
        @keyframes giftPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2) rotate(-10deg); }
        }
        .promo-card.voucher-promo .promo-percent {
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 16px rgba(251,191,36,0.6);
        }
        .promo-card.voucher-promo .promo-percent small {
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        .promo-card.voucher-promo .promo-code {
            color: #059669;
        }
        /* Voucher + sudah claimed = hijau gelap */
        .promo-card.voucher-promo.claimed {
            background: linear-gradient(135deg, #064E3B 0%, #047857 50%, #059669 100%);
        }

        .promo-inner { position: relative; z-index: 1; }

        .promo-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            gap: 6px;
        }
        .promo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 0.55rem;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .promo-badge i { color: #FCD34D; font-size: 0.55rem; }
        .promo-card.claimed .promo-badge i { color: #fff; animation: none; }

        .promo-min {
            font-size: 0.58rem;
            color: #fff;
            font-weight: 700;
            padding: 3px 7px;
            background: rgba(0,0,0,0.25);
            border-radius: 5px;
            white-space: nowrap;
        }

        .promo-percent {
            font-size: 1.9rem;
            font-weight: 800;
            color: #FCD34D;
            line-height: 0.95;
            letter-spacing: -1.5px;
            margin-bottom: 6px;
            text-shadow: 0 4px 16px rgba(251,191,36,0.5);
        }
        .promo-card.claimed .promo-percent { color: #fff; text-shadow: 0 4px 16px rgba(255,255,255,0.3); }
        .promo-percent small { font-size: 0.85rem; font-weight: 700; letter-spacing: 0; }

        .promo-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .promo-desc {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.82);
            line-height: 1.4;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Info "berlaku film apa saja" untuk voucher */
        .promo-voucher-info {
            font-size: 0.63rem;
            color: rgba(255,255,255,0.9);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .promo-voucher-info i {
            font-size: 0.6rem;
            color: #FCD34D;
        }

        .promo-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 10px;
        }
        .promo-tags span {
            padding: 2px 7px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 5px;
            font-size: 0.58rem;
            color: #fff;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .promo-tags span i { font-size: 0.55rem; }

        .promo-bottom {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        .promo-code {
            padding: 5px 10px;
            background: #fff;
            color: #4C1D95;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 800;
            font-size: 0.68rem;
            letter-spacing: 0.8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .promo-card.claimed .promo-code { color: #047857; }

        .btn-klaim {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border: none;
            border-radius: 7px;
            background: #FCD34D;
            color: #4C1D95;
            font-family: inherit;
            font-size: 0.68rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(251,191,36,0.5);
            position: relative;
            z-index: 10;
            white-space: nowrap;
        }
        .btn-klaim:hover:not(:disabled) {
            background: #FBBF24;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(251,191,36,0.7);
        }
        .btn-klaim:disabled {
            background: rgba(255,255,255,0.28);
            color: #fff;
            cursor: not-allowed;
            box-shadow: none;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .btn-klaim.loading {
            background: rgba(255,255,255,0.28);
            color: #fff;
            cursor: wait;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .btn-klaim i { font-size: 0.65rem; }

        /* ===== FILM ===== */
        .film-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 22px;
        }
        .film-card {
            background: #111A2E;
            border-radius: 16px;
            overflow: hidden;
            transition: 0.35s;
            border: 1px solid #1E293B;
            text-decoration: none;
            color: #fff;
            display: block;
        }
        .film-card:hover {
            transform: translateY(-8px);
            border-color: #3B82F6;
            box-shadow: 0 20px 45px rgba(59,130,246,0.2);
        }
        .film-poster {
            width: 100%;
            height: 290px;
            object-fit: cover;
            background: #0B1120;
            display: block;
            transition: 0.5s;
        }
        .film-card:hover .film-poster { transform: scale(1.05); }
        .film-info { padding: 16px 18px; }
        .film-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .film-meta { display: flex; gap: 6px; flex-wrap: wrap; }
        .film-meta .badge {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
        }
        .badge-blue { background: rgba(59,130,246,0.15); color: #60A5FA; border: 1px solid rgba(59,130,246,0.25); }
        .badge-dark { background: rgba(148,163,184,0.1); color: #CBD5E1; border: 1px solid rgba(148,163,184,0.15); }

        .divider-line {
            border: none;
            border-top: 1px solid #1E293B;
            margin: 44px 0 30px;
        }

        .coming-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 18px;
        }
        .coming-card {
            background: #111A2E;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #1E293B;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
            display: block;
        }
        .coming-card:hover {
            border-color: #FBBF24;
            transform: translateY(-4px);
        }
        .coming-card .film-poster { height: 220px; }
        .coming-badge {
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            color: #1F2937;
            font-weight: 800;
            font-size: 0.68rem;
            padding: 5px 12px;
            border-radius: 999px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .empty-box {
            grid-column: 1/-1;
            text-align: center;
            padding: 70px 20px;
            color: #64748B;
        }
        .empty-box i { font-size: 3rem; opacity: 0.25; display: block; margin-bottom: 14px; }

        /* ===== TOAST ===== */
        .toast-fix {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            background: #111A2E;
            border: 1.5px solid #10B981;
            color: #6EE7B7;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            opacity: 0;
            transition: 0.4s;
            z-index: 999999;
            pointer-events: none;
            max-width: 90vw;
            text-align: left;
        }
        .toast-fix.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast-fix.error { border-color: #EF4444; color: #FCA5A5; }
        .toast-fix.error i { color: #EF4444; }
        .toast-fix.success i { color: #10B981; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .film-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
            .film-poster { height: 210px; }
            .coming-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .promo-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .promo-card { padding: 12px 14px; }
            .promo-percent { font-size: 1.6rem; }
            .promo-card.voucher-promo .promo-percent { font-size: 1.25rem; }
            .promo-title { font-size: 0.78rem; }
            .promo-desc { display: none; }
            .promo-voucher-info { display: none; }
            .film-poster { height: 175px; }
            .section-title { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <?php if($promos && $promos->num_rows > 0): ?>
            <h2 class="section-title"><i class="fas fa-tags ic-gold"></i> Promo</h2>
            <div class="promo-grid">
                <?php while($pr = $promos->fetch_assoc()): 
                    $idp = (int)$pr['id_promo'];
                    $statusSaya = $klaimSaya[$idp] ?? null;
                    $sudahKlaim = ($statusSaya === 'active');
                    $sudahPakai = ($statusSaya === 'used');
                    
                    $tipe_pr = $pr['tipe_diskon'] ?? 'persen';
                    $isVoucherPromo = ($tipe_pr === 'buy_x_get_y');
                    
                    $cardClass = ($sudahKlaim || $sudahPakai) ? 'promo-card claimed' : 'promo-card';
                    if ($isVoucherPromo) $cardClass .= ' voucher-promo';
                    
                    $kuota    = (int)($pr['kuota'] ?? 0);
                    $terpakai = (int)$conn->query("SELECT COUNT(*) AS c FROM user_promo WHERE id_promo = $idp AND status='used'")->fetch_assoc()['c'];
                    $kuotaHabis = ($kuota > 0 && $terpakai >= $kuota);
                ?>
                <div class="<?= $cardClass; ?>">
                    <div class="promo-inner">
                        <div class="promo-top">
                            <span class="promo-badge">
                                <?php if ($isVoucherPromo): ?>
                                    <i class="fas fa-gift"></i> Tiket Gratis
                                <?php elseif ($tipe_pr === 'persen'): ?>
                                    <i class="fas fa-fire"></i> Diskon
                                <?php else: ?>
                                    <i class="fas fa-fire"></i> Potongan
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($pr['min_pembelian']) && $pr['min_pembelian'] > 0): ?>
                                <span class="promo-min">Min Rp <?= number_format($pr['min_pembelian'], 0, ',', '.'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="promo-percent">
                            <?php 
                            if ($isVoucherPromo) {
                                $jmlV = (int)($pr['nilai_diskon'] ?? 1);
                                echo '🎁<small> ' . $jmlV . ' VOUCHER</small>';
                            } elseif ($tipe_pr === 'persen') {
                                echo (int)($pr['nilai_diskon'] ?? 0) . '<small>%</small>';
                            } else {
                                echo 'Rp ' . number_format($pr['nilai_diskon'] ?? 0, 0, ',', '.');
                            }
                            ?>
                        </div>
                        <div class="promo-title"><?= htmlspecialchars($pr['judul']); ?></div>
                        <div class="promo-desc"><?= htmlspecialchars($pr['deskripsi']); ?></div>

                        <?php if ($isVoucherPromo): ?>
                            <div class="promo-voucher-info">
                                <i class="fas fa-film"></i> Berlaku film apa saja
                            </div>
                        <?php endif; ?>

                        <div class="promo-tags">
                            <?php if (!empty($pr['tanggal_berakhir'])): ?>
                                <span><i class="fas fa-calendar"></i> s/d <?= date('d M', strtotime($pr['tanggal_berakhir'])); ?></span>
                            <?php endif; ?>
                            <?php if ($kuota > 0): ?>
                                <span><i class="fas fa-users"></i> <?= max(0, $kuota - $terpakai); ?>/<?= $kuota; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="promo-bottom">
                            <div class="promo-code"><?= htmlspecialchars($pr['kode_promo']); ?></div>
                            
                            <?php if ($sudahPakai): ?>
                                <button type="button" class="btn-klaim" disabled>
                                    <i class="fas fa-check-double"></i> Dipakai
                                </button>
                            <?php elseif ($sudahKlaim): ?>
                                <button type="button" class="btn-klaim" disabled>
                                    <i class="fas fa-check"></i> Diklaim
                                </button>
                            <?php elseif ($kuotaHabis): ?>
                                <button type="button" class="btn-klaim" disabled>
                                    <i class="fas fa-times"></i> Habis
                                </button>
                            <?php elseif ($role === 'user'): ?>
                                <button type="button" class="btn-klaim" 
                                        data-kode="<?= htmlspecialchars($pr['kode_promo']); ?>"
                                        onclick="klaimPromo(this)">
                                    <i class="fas fa-bookmark"></i> Klaim
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-klaim" disabled title="Hanya user yang bisa klaim promo">
                                    <i class="fas fa-lock"></i> User
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title"><i class="fas fa-fire ic-fire"></i> Sedang Tayang</h2>
        <div class="film-grid">
            <?php if ($films && $films->num_rows > 0): ?>
                <?php while($film = $films->fetch_assoc()): ?>
                <a href="detail_film.php?id=<?= $film['id_film']; ?>" class="film-card">
                    <img src="assets/img/<?= htmlspecialchars($film['poster'] ?? 'default.jpg'); ?>" class="film-poster" alt="Poster" onerror="this.src='assets/img/default.jpg'">
                    <div class="film-info">
                        <div class="film-title"><?= htmlspecialchars($film['judul']); ?></div>
                        <div class="film-meta">
                            <span class="badge badge-dark"><?= htmlspecialchars($film['genre'] ?? ''); ?></span>
                            <span class="badge badge-blue"><?= htmlspecialchars($film['rating_usia'] ?? ''); ?></span>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-box"><i class="fas fa-film"></i><p>Belum ada film.</p></div>
            <?php endif; ?>
        </div>

        <hr class="divider-line">

        <h2 class="section-title"><i class="fas fa-clock ic-clock"></i> Segera Hadir</h2>
        <div class="coming-grid">
            <?php if ($comingSoon && $comingSoon->num_rows > 0): ?>
                <?php while($film = $comingSoon->fetch_assoc()): ?>
                <a href="detail_film.php?id=<?= $film['id_film']; ?>" class="coming-card">
                    <img src="assets/img/<?= htmlspecialchars($film['poster'] ?? 'default.jpg'); ?>" class="film-poster" alt="Poster" onerror="this.src='assets/img/default.jpg'">
                    <div class="film-info">
                        <div class="film-title" style="font-size:0.92rem;"><?= htmlspecialchars($film['judul']); ?></div>
                        <div class="film-meta" style="margin-top:6px;">
                            <span class="coming-badge">Coming Soon</span>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-box" style="padding:40px;"><i class="fas fa-clock"></i><p>Belum ada.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast-fix" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMsg">Berhasil!</span>
    </div>

    <script>
        // ===== PERBAIKAN #4: CSRF token untuk fetch ke klaim_promo.php =====
        // Token ini akan diverifikasi server-side setelah kita perbaiki klaim_promo.php.
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']); ?>;

        function klaimPromo(btn) {
            const kode = btn.dataset.kode;
            if (!kode) {
                showToast('Kode promo tidak valid', 'error');
                return;
            }

            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('klaim_promo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'kode=' + encodeURIComponent(kode) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                btn.classList.remove('loading');

                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Diklaim';
                    btn.disabled = true;
                    const card = btn.closest('.promo-card');
                    if (card) card.classList.add('claimed');
                    showToast(data.message || 'Promo berhasil diklaim!', 'success');
                } else {
                    if (data.already) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Diklaim';
                        btn.disabled = true;
                        const card = btn.closest('.promo-card');
                        if (card) card.classList.add('claimed');
                    } else {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }
                    showToast(data.message || 'Gagal mengklaim promo', 'error');
                }
            })
            .catch(err => {
                console.error('Klaim promo error:', err);
                btn.classList.remove('loading');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                showToast('Gagal memproses klaim. Cek koneksi Anda.', 'error');
            });
        }

        let toastTimer = null;
        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            const icon  = toast.querySelector('i');

            document.getElementById('toastMsg').textContent = msg;
            toast.classList.remove('error', 'success');

            if (type === 'error') {
                toast.classList.add('error');
                icon.className = 'fas fa-exclamation-circle';
            } else {
                toast.classList.add('success');
                icon.className = 'fas fa-check-circle';
            }

            toast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
        }
    </script>
</body>
</html>