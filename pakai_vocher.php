<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$id_voucher = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_user = (int)$_SESSION['id_user'];

$voucher = $conn->query("
    SELECT v.*, p.judul AS promo_judul 
    FROM voucher_gratis v 
    LEFT JOIN promo p ON v.id_promo = p.id_promo 
    WHERE v.id_voucher = $id_voucher 
      AND v.id_user = $id_user 
      AND v.status = 'active'
")->fetch_assoc();

if (!$voucher) {
    $_SESSION['error'] = "Voucher tidak ditemukan atau sudah dipakai.";
    header('Location: voucher_saya.php');
    exit;
}

// Cek expired
if (!empty($voucher['expired_at']) && strtotime($voucher['expired_at']) < time()) {
    $conn->query("UPDATE voucher_gratis SET status='expired' WHERE id_voucher = $id_voucher");
    $_SESSION['error'] = "Voucher sudah kadaluarsa.";
    header('Location: voucher_saya.php');
    exit;
}

$error = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'pilih_jadwal';

// Ambil film yang punya jadwal tersedia
$films = $conn->query("
    SELECT DISTINCT f.id_film, f.judul, f.poster, f.genre, f.durasi, f.rating_usia 
    FROM film f 
    JOIN jadwal j ON f.id_film = j.id_film 
    WHERE f.status = 'Now Showing'
      AND (j.tanggal > CURDATE() OR (j.tanggal = CURDATE() AND j.jam > CURTIME()))
    ORDER BY f.judul ASC
");

// Ambil jadwal berdasarkan film terpilih
$id_film_terpilih = isset($_GET['film']) ? (int)$_GET['film'] : 0;
$film_terpilih = null;
$jadwals = null;

if ($id_film_terpilih > 0) {
    $film_terpilih = $conn->query("SELECT * FROM film WHERE id_film = $id_film_terpilih")->fetch_assoc();
    if ($film_terpilih) {
        $jadwals = $conn->query("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM kursi WHERE id_jadwal = j.id_jadwal AND status='tersedia') AS kursi_tersedia
            FROM jadwal j 
            WHERE j.id_film = $id_film_terpilih
              AND (j.tanggal > CURDATE() OR (j.tanggal = CURDATE() AND j.jam > CURTIME()))
            ORDER BY j.tanggal ASC, j.jam ASC
        ");
    }
}

// ===== PROSES PAKAI VOUCHER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gunakan_voucher'])) {
    $id_jadwal = (int)$_POST['id_jadwal'];
    $kode_kursi = trim($_POST['kode_kursi']);
    $nama_pemesan = trim($_POST['nama_pemesan']);
    
    if ($id_jadwal <= 0) {
        $error = 'Pilih jadwal terlebih dahulu.';
    } elseif (empty($kode_kursi)) {
        $error = 'Pilih kursi terlebih dahulu.';
    } elseif (empty($nama_pemesan)) {
        $error = 'Nama pemesan wajib diisi.';
    } else {
        // Validasi jadwal
        $jadwal = $conn->query("SELECT j.*, f.judul FROM jadwal j JOIN film f ON j.id_film = f.id_film WHERE j.id_jadwal = $id_jadwal")->fetch_assoc();
        
        if (!$jadwal) {
            $error = 'Jadwal tidak ditemukan.';
        } else {
            // Validasi kursi tersedia
            $cekKursi = $conn->query("SELECT status FROM kursi WHERE id_jadwal = $id_jadwal AND kode_kursi = '$kode_kursi'")->fetch_assoc();
            
            if (!$cekKursi || $cekKursi['status'] !== 'tersedia') {
                $error = 'Kursi tidak tersedia.';
            } else {
                $conn->begin_transaction();
                try {
                    $kode_booking = 'VCR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    
                    // Insert pemesanan harga 0
                    $stmt = $conn->prepare("INSERT INTO pemesanan 
                        (kode_booking, id_user, id_jadwal, total_harga, status, metode, nama_pemesan, kode_promo, diskon_amount, tanggal_pesan) 
                        VALUES (?, ?, ?, 0, 'Lunas', 'Voucher', ?, 'VOUCHER', 0, NOW())");
                    $stmt->bind_param("siis", $kode_booking, $id_user, $id_jadwal, $nama_pemesan);
                    if (!$stmt->execute()) throw new Exception("Insert pemesanan gagal: " . $stmt->error);
                    $id_pemesanan_baru = $conn->insert_id;
                    $stmt->close();
                    
                    // Insert detail kursi
                    $stmtSeat = $conn->prepare("INSERT INTO detail_pemesanan (id_pemesanan, kode_kursi) VALUES (?, ?)");
                    $stmtSeat->bind_param("is", $id_pemesanan_baru, $kode_kursi);
                    if (!$stmtSeat->execute()) throw new Exception("Insert kursi gagal: " . $stmtSeat->error);
                    $stmtSeat->close();
                    
                    // Update kursi jadi dipesan
                    $conn->query("UPDATE kursi SET status='dipesan' WHERE id_jadwal = $id_jadwal AND kode_kursi = '$kode_kursi'");
                    
                    // Update voucher jadi used
                    $conn->query("UPDATE voucher_gratis SET status='used', used_at=NOW(), id_pemesanan_pakai = $id_pemesanan_baru WHERE id_voucher = $id_voucher");
                    
                    $conn->commit();
                    
                    $_SESSION['message'] = "Voucher berhasil digunakan! Tiket gratis untuk \"{$jadwal['judul']}\" sudah dibuat.";
                    header("Location: cetak_tiket.php?id=$id_pemesanan_baru");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Gagal pakai voucher: " . $e->getMessage();
                }
            }
        }
    }
}

// Ambil kursi untuk jadwal yang dipilih (kalau sudah pilih jadwal)
$id_jadwal_terpilih = isset($_GET['jadwal']) ? (int)$_GET['jadwal'] : 0;
$kursiPerBaris = [];

if ($id_jadwal_terpilih > 0) {
    $kursiQuery = $conn->query("SELECT * FROM kursi WHERE id_jadwal = $id_jadwal_terpilih ORDER BY SUBSTRING(kode_kursi, 1, 1), CAST(SUBSTRING(kode_kursi, 2) AS UNSIGNED)");
    while ($k = $kursiQuery->fetch_assoc()) {
        if (preg_match('/^([A-Za-z]+)(\d+)$/', $k['kode_kursi'], $m)) {
            $row = strtoupper($m[1]);
            if (!isset($kursiPerBaris[$row])) $kursiPerBaris[$row] = [];
            $kursiPerBaris[$row][] = $k;
        }
    }
    ksort($kursiPerBaris);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gunakan Voucher - WizCinema</title>
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

        .back-link {
            color: #94A3B8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: 0.25s;
        }
        .back-link:hover { color: #FBBF24; transform: translateX(-3px); }

        /* Voucher info banner */
        .voucher-banner {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 2px dashed #F59E0B;
            border-radius: 18px;
            padding: 22px 26px;
            color: #78350F;
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }
        .voucher-banner::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245,158,11,0.3), transparent 65%);
            pointer-events: none;
        }
        .voucher-banner .v-badge {
            display: inline-block;
            background: #F59E0B;
            color: #fff;
            padding: 5px 14px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.68rem;
            letter-spacing: 1px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .voucher-banner .v-kode {
            font-family: 'Courier New', monospace;
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }
        .voucher-banner .v-info {
            font-size: 0.82rem;
            opacity: 0.85;
            line-height: 1.6;
        }
        .voucher-banner .v-info strong { font-weight: 800; }

        /* Step indicator */
        .step-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 26px;
            flex-wrap: wrap;
        }
        .step-item {
            flex: 1;
            min-width: 140px;
            padding: 12px 16px;
            background: #111A2E;
            border: 1.5px solid #1E293B;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748B;
            transition: 0.25s;
        }
        .step-item.active {
            background: rgba(251,191,36,0.1);
            border-color: #FBBF24;
            color: #FCD34D;
        }
        .step-item.done {
            background: rgba(16,185,129,0.1);
            border-color: #10B981;
            color: #34D399;
        }
        .step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #1E293B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .step-item.active .step-num { background: #FBBF24; color: #1F2937; }
        .step-item.done .step-num { background: #10B981; color: #fff; }

        /* Section */
        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; color: #fff;
        }

        /* Film grid */
        .film-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .film-card {
            background: #111A2E;
            border: 1.5px solid #1E293B;
            border-radius: 14px;
            overflow: hidden;
            text-decoration: none;
            color: #fff;
            transition: 0.3s;
            display: block;
        }
        .film-card:hover {
            border-color: #FBBF24;
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(251,191,36,0.2);
        }
        .film-card.selected {
            border-color: #FBBF24;
            box-shadow: 0 0 0 3px rgba(251,191,36,0.25);
        }
        .film-poster {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #0B1120;
            display: block;
        }
        .film-info { padding: 12px 14px; }
        .film-title {
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .film-meta { font-size: 0.7rem; color: #64748B; }

        /* Jadwal grid */
        .jadwal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 14px;
            margin-bottom: 30px;
        }
        .jadwal-card {
            background: #111A2E;
            border: 1.5px solid #1E293B;
            border-radius: 14px;
            padding: 18px;
            transition: 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }
        .jadwal-card:hover:not(.disabled) {
            border-color: #FBBF24;
            transform: translateY(-3px);
        }
        .jadwal-card.selected {
            border-color: #FBBF24;
            background: rgba(251,191,36,0.05);
            box-shadow: 0 0 0 3px rgba(251,191,36,0.2);
        }
        .jadwal-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .jadwal-date {
            font-weight: 700;
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jadwal-date i { color: #FBBF24; }
        .jadwal-time {
            color: #FCD34D;
            font-weight: 800;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .jadwal-studio {
            font-size: 0.78rem;
            color: #94A3B8;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .jadwal-kursi {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
            background: rgba(16,185,129,0.15);
            color: #34D399;
            display: inline-block;
        }
        .jadwal-kursi.habis {
            background: rgba(239,68,68,0.15);
            color: #F87171;
        }

        /* Seat selection */
        .seat-container {
            max-width: 640px;
            margin: 0 auto 30px;
            background: #111A2E;
            border-radius: 20px;
            padding: 28px 22px;
            border: 1px solid #1E293B;
        }
        .screen {
            background: linear-gradient(180deg, #FBBF24 0%, #F59E0B 60%, transparent 100%);
            height: 8px;
            border-radius: 50px 50px 0 0;
            max-width: 400px;
            margin: 20px auto 6px;
            box-shadow: 0 4px 24px rgba(251,191,36,0.5);
        }
        .screen-label {
            text-align: center;
            color: #475569;
            font-size: 0.65rem;
            letter-spacing: 3px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .seat-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .seat-row-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .row-label {
            width: 22px;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 800;
            color: #475569;
            flex-shrink: 0;
        }
        .seat-row {
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        .seat {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.72rem;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
        }
        .seat-available {
            background: #0B1120;
            color: #94A3B8;
            border: 1.5px solid #1E293B;
        }
        .seat-available:hover {
            border-color: #FBBF24;
            color: #FCD34D;
            transform: translateY(-2px);
        }
        .seat-selected {
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            color: #1F2937;
            border: 1.5px solid #FCD34D;
            box-shadow: 0 4px 14px rgba(251,191,36,0.5);
            transform: translateY(-2px);
        }
        .seat-booked {
            background: #1E293B;
            color: #475569;
            cursor: not-allowed;
            opacity: 0.4;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 0.75rem;
            color: #94A3B8;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-box { width: 16px; height: 16px; border-radius: 4px; display: inline-block; }
        .legend-box.available { background: #0B1120; border: 1.5px solid #1E293B; }
        .legend-box.selected { background: linear-gradient(135deg, #FBBF24, #F59E0B); }
        .legend-box.booked { background: #1E293B; }

        /* Form pemesan */
        .form-card {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 22px;
            max-width: 640px;
            margin: 0 auto;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #94A3B8;
            margin-bottom: 8px;
        }
        .form-label i { color: #FBBF24; margin-right: 6px; }
        .form-input {
            width: 100%;
            background: #0B1120;
            border: 1.5px solid #1E293B;
            color: #fff;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
            transition: 0.25s;
        }
        .form-input:focus {
            border-color: #FBBF24;
            box-shadow: 0 0 0 3px rgba(251,191,36,0.15);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            color: #1F2937;
            border: none;
            border-radius: 11px;
            padding: 14px;
            font-size: 0.95rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.25s;
            box-shadow: 0 8px 20px rgba(251,191,36,0.4);
            margin-top: 18px;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(251,191,36,0.6);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.35);
            color: #FCA5A5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .empty-box {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
            background: #111A2E;
            border: 1px dashed #1E293B;
            border-radius: 16px;
        }
        .empty-box i { font-size: 2.5rem; opacity: 0.25; display: block; margin-bottom: 12px; }

        /* Summary box */
        .summary-box {
            background: rgba(251,191,36,0.08);
            border: 1.5px solid rgba(251,191,36,0.3);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            margin-bottom: 8px;
        }
        .summary-row:last-child { margin-bottom: 0; }
        .summary-row .label { color: #94A3B8; }
        .summary-row .value { color: #fff; font-weight: 700; }
        .summary-row .value.highlight { color: #FCD34D; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .seat { width: 36px; height: 36px; font-size: 0.68rem; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <a href="voucher_saya.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Voucher Saya
        </a>

        <!-- Banner Voucher -->
        <div class="voucher-banner">
            <div class="v-badge">🎁 Voucher Gratis</div>
            <div class="v-kode"><?= htmlspecialchars($voucher['kode_voucher']); ?></div>
            <div class="v-info">
                Dari promo <strong><?= htmlspecialchars($voucher['promo_judul'] ?? '-'); ?></strong>
                · Berlaku s/d <strong><?= $voucher['expired_at'] ? date('d M Y', strtotime($voucher['expired_at'])) : 'Tidak terbatas'; ?></strong>
                <br>Voucher ini untuk <strong>1 tiket gratis</strong> film apa saja.
            </div>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Step indicator -->
        <div class="step-bar">
            <div class="step-item <?= ($id_film_terpilih > 0) ? 'done' : 'active'; ?>">
                <span class="step-num"><?= ($id_film_terpilih > 0) ? '<i class="fas fa-check"></i>' : '1'; ?></span>
                Pilih Film
            </div>
            <div class="step-item <?= ($id_film_terpilih > 0 && $id_jadwal_terpilih == 0) ? 'active' : (($id_jadwal_terpilih > 0) ? 'done' : ''); ?>">
                <span class="step-num"><?= ($id_jadwal_terpilih > 0) ? '<i class="fas fa-check"></i>' : '2'; ?></span>
                Pilih Jadwal
            </div>
            <div class="step-item <?= ($id_jadwal_terpilih > 0) ? 'active' : ''; ?>">
                <span class="step-num">3</span>
                Pilih Kursi
            </div>
        </div>

        <?php if ($id_film_terpilih == 0): ?>
            <!-- ===== STEP 1: PILIH FILM ===== -->
            <div class="section-title"><i class="fas fa-film"></i> Pilih Film</div>
            
            <?php if ($films->num_rows > 0): ?>
                <div class="film-grid">
                    <?php while ($f = $films->fetch_assoc()): ?>
                        <a href="pakai_voucher.php?id=<?= $id_voucher; ?>&film=<?= $f['id_film']; ?>" class="film-card">
                            <img src="assets/img/<?= htmlspecialchars($f['poster'] ?? 'default.jpg'); ?>" 
                                 class="film-poster" 
                                 onerror="this.src='assets/img/default.jpg'"
                                 alt="<?= htmlspecialchars($f['judul']); ?>">
                            <div class="film-info">
                                <div class="film-title"><?= htmlspecialchars($f['judul']); ?></div>
                                <div class="film-meta"><?= htmlspecialchars($f['genre']); ?> · <?= htmlspecialchars($f['rating_usia']); ?></div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    <i class="fas fa-film"></i>
                    <p>Belum ada film yang punya jadwal tersedia.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($id_jadwal_terpilih == 0): ?>
            <!-- ===== STEP 2: PILIH JADWAL ===== -->
            <div class="section-title"><i class="fas fa-calendar-alt"></i> Pilih Jadwal - <?= htmlspecialchars($film_terpilih['judul']); ?></div>
            
            <?php if ($jadwals && $jadwals->num_rows > 0): ?>
                <div class="jadwal-grid">
                    <?php while ($j = $jadwals->fetch_assoc()): 
                        $habis = ($j['kursi_tersedia'] <= 0);
                    ?>
                        <?php if ($habis): ?>
                            <div class="jadwal-card disabled">
                        <?php else: ?>
                            <a href="pakai_voucher.php?id=<?= $id_voucher; ?>&film=<?= $id_film_terpilih; ?>&jadwal=<?= $j['id_jadwal']; ?>" class="jadwal-card">
                        <?php endif; ?>
                            <div class="jadwal-date"><i class="fas fa-calendar-day"></i> <?= date('d M Y', strtotime($j['tanggal'])); ?></div>
                            <div class="jadwal-time"><?= date('H:i', strtotime($j['jam'])); ?> WIB</div>
                            <div class="jadwal-studio"><i class="fas fa-couch"></i> Studio <?= htmlspecialchars($j['studio']); ?></div>
                            <span class="jadwal-kursi <?= $habis ? 'habis' : ''; ?>">
                                <i class="fas fa-chair"></i> <?= $j['kursi_tersedia']; ?> kursi tersedia
                            </span>
                        <?php if ($habis): ?>
                            </div>
                        <?php else: ?>
                            </a>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    <i class="fas fa-calendar-times"></i>
                    <p>Belum ada jadwal tersedia untuk film ini.</p>
                </div>
            <?php endif; ?>

            <a href="pakai_voucher.php?id=<?= $id_voucher; ?>" class="back-link" style="margin-top:20px;">
                <i class="fas fa-arrow-left"></i> Ganti Film
            </a>

        <?php else: ?>
            <!-- ===== STEP 3: PILIH KURSI + KONFIRMASI ===== -->
            <?php 
            $jadwal_terpilih = $conn->query("SELECT j.*, f.judul FROM jadwal j JOIN film f ON j.id_film = f.id_film WHERE j.id_jadwal = $id_jadwal_terpilih")->fetch_assoc();
            ?>
            <div class="section-title"><i class="fas fa-couch"></i> Pilih Kursi - <?= htmlspecialchars($jadwal_terpilih['judul']); ?></div>
            
            <form method="POST" id="formVoucher">
                <input type="hidden" name="id_jadwal" value="<?= $id_jadwal_terpilih; ?>">
                <input type="hidden" name="kode_kursi" id="inputKursi" value="">

                <div class="seat-container">
                    <div style="text-align:center; margin-bottom:16px;">
                        <div style="font-size:0.85rem; color:#94A3B8;">
                            Studio <?= htmlspecialchars($jadwal_terpilih['studio']); ?> · 
                            <?= date('d M Y', strtotime($jadwal_terpilih['tanggal'])); ?> · 
                            <?= date('H:i', strtotime($jadwal_terpilih['jam'])); ?> WIB
                        </div>
                    </div>
                    
                    <div class="screen"></div>
                    <div class="screen-label">LAYAR BIOSKOP</div>

                    <div class="seat-area">
                        <?php if (empty($kursiPerBaris)): ?>
                            <div class="empty-box"><i class="fas fa-couch"></i><p>Belum ada kursi.</p></div>
                        <?php else: ?>
                            <?php foreach ($kursiPerBaris as $rowLabel => $kursiList): ?>
                                <div class="seat-row-wrap">
                                    <div class="row-label"><?= $rowLabel; ?></div>
                                    <div class="seat-row">
                                        <?php foreach ($kursiList as $k): 
                                            $available = ($k['status'] === 'tersedia');
                                            $kursiNum = preg_replace('/^[A-Za-z]+/', '', $k['kode_kursi']);
                                        ?>
                                            <button type="button"
                                                    class="seat <?= $available ? 'seat-available' : 'seat-booked'; ?>"
                                                    data-seat="<?= htmlspecialchars($k['kode_kursi']); ?>"
                                                    <?= $available ? '' : 'disabled'; ?>>
                                                <?= htmlspecialchars($kursiNum); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row-label"><?= $rowLabel; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="legend">
                        <span class="legend-item"><span class="legend-box available"></span> Tersedia</span>
                        <span class="legend-item"><span class="legend-box selected"></span> Dipilih</span>
                        <span class="legend-item"><span class="legend-box booked"></span> Terisi</span>
                    </div>
                </div>

                <div class="form-card">
                    <div class="summary-box">
                        <div class="summary-row">
                            <span class="label">Film</span>
                            <span class="value"><?= htmlspecialchars($jadwal_terpilih['judul']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="label">Kursi</span>
                            <span class="value highlight" id="summaryKursi">Belum dipilih</span>
                        </div>
                        <div class="summary-row">
                            <span class="label">Total Bayar</span>
                            <span class="value highlight" style="color:#34D399;">GRATIS (Rp 0)</span>
                        </div>
                    </div>

                    <label class="form-label"><i class="fas fa-user"></i> Nama Pemesan</label>
                    <input type="text" name="nama_pemesan" class="form-input" 
                           value="<?= htmlspecialchars($_SESSION['nama'] ?? ''); ?>" 
                           placeholder="Masukkan nama lengkap" required>

                    <button type="submit" name="gunakan_voucher" class="btn-primary" id="btnSubmit" disabled>
                        <i class="fas fa-ticket-alt"></i> Gunakan Voucher Sekarang
                    </button>
                </div>
            </form>

            <div style="text-align:center; margin-top:16px;">
                <a href="pakai_voucher.php?id=<?= $id_voucher; ?>&film=<?= $id_film_terpilih; ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Ganti Jadwal
                </a>
            </div>

            <script>
                let selectedSeat = null;
                
                document.querySelectorAll('.seat-available').forEach(btn => {
                    btn.addEventListener('click', function() {
                        // Deselect semua
                        document.querySelectorAll('.seat-selected').forEach(s => {
                            s.classList.remove('seat-selected');
                            s.classList.add('seat-available');
                        });
                        
                        // Select yang baru
                        if (selectedSeat === this.dataset.seat) {
                            selectedSeat = null;
                            this.classList.remove('seat-selected');
                            this.classList.add('seat-available');
                        } else {
                            selectedSeat = this.dataset.seat;
                            this.classList.remove('seat-available');
                            this.classList.add('seat-selected');
                        }
                        
                        updateSummary();
                    });
                });
                
                function updateSummary() {
                    const inputKursi = document.getElementById('inputKursi');
                    const summaryKursi = document.getElementById('summaryKursi');
                    const btnSubmit = document.getElementById('btnSubmit');
                    
                    if (selectedSeat) {
                        inputKursi.value = selectedSeat;
                        summaryKursi.textContent = selectedSeat;
                        summaryKursi.style.color = '#FCD34D';
                        btnSubmit.disabled = false;
                    } else {
                        inputKursi.value = '';
                        summaryKursi.textContent = 'Belum dipilih';
                        summaryKursi.style.color = '#94A3B8';
                        btnSubmit.disabled = true;
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>