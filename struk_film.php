<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$id_pemesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_user = (int)$_SESSION['id_user'];
$role = $_SESSION['role'] ?? '';

if (!$id_pemesanan) {
    $_SESSION['error'] = "Data pemesanan tidak ditemukan.";
    header('Location: dashboard.php');
    exit;
}

// ===== QUERY LENGKAP: poster, mall, dll =====
if ($role === 'admin' || $role === 'kasir') {
    $sql = "SELECT p.*, 
                   f.judul, f.poster, f.genre, f.durasi, f.rating_usia,
                   j.studio, j.tanggal, j.jam, j.id_mall,
                   m.nama_mall
            FROM pemesanan p 
            JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
            JOIN film f ON j.id_film = f.id_film 
            LEFT JOIN mall m ON j.id_mall = m.id_mall
            WHERE p.id_pemesanan = $id_pemesanan";
} else {
    $sql = "SELECT p.*, 
                   f.judul, f.poster, f.genre, f.durasi, f.rating_usia,
                   j.studio, j.tanggal, j.jam, j.id_mall,
                   m.nama_mall
            FROM pemesanan p 
            JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
            JOIN film f ON j.id_film = f.id_film 
            LEFT JOIN mall m ON j.id_mall = m.id_mall
            WHERE p.id_pemesanan = $id_pemesanan AND p.id_user = $id_user";
}

$order = $conn->query($sql)->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = "Pemesanan tidak ditemukan.";
    header('Location: dashboard.php');
    exit;
}

// Cek batas cetak (24 jam setelah tayang)
$jadwal_dt   = strtotime($order['tanggal'] . ' ' . $order['jam']);
$batas_cetak = $jadwal_dt + (24 * 60 * 60);

if ($role === 'user' && time() > $batas_cetak) {
    $_SESSION['error'] = "Tiket tidak dapat dicetak — sudah lebih dari 1 hari sejak jadwal tayang.";
    header('Location: dashboard.php');
    exit;
}

// Ambil kursi
$kursiList = [];
$k = $conn->query("SELECT kode_kursi FROM detail_pemesanan WHERE id_pemesanan = $id_pemesanan ORDER BY kode_kursi");
while ($r = $k->fetch_assoc()) $kursiList[] = $r['kode_kursi'];
$kursiStr = implode(', ', $kursiList);

// ===== Ambil voucher gratis yang lahir dari booking ini =====
$vouchers = $conn->query("
    SELECT v.*, pr.judul AS promo_judul
    FROM voucher_gratis v
    LEFT JOIN promo pr ON v.id_promo = pr.id_promo
    WHERE v.id_pemesanan_asal = $id_pemesanan 
      AND v.id_user = {$order['id_user']}
    ORDER BY v.id_voucher ASC
");

$totalVoucher = ($vouchers && $vouchers->num_rows > 0) ? $vouchers->num_rows : 0;
$totalHalaman = 1 + $totalVoucher;

$posterFile = !empty($order['poster']) ? $order['poster'] : 'default.jpg';
$namaMall   = !empty($order['nama_mall']) ? $order['nama_mall'] : 'WizCinema';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1280">
    <title>Cetak Tiket<?= $totalVoucher > 0 ? ' & Voucher' : ''; ?> - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0B1120;
            color: #F1F5F9;
            min-height: 100vh;
            padding: 30px 15px 80px;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            max-width: 560px;
            margin: 0 auto 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-toolbar {
            flex: 1;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: none;
            border-radius: 11px;
            font-weight: 700;
            font-size: 0.88rem;
            font-family: inherit;
            cursor: pointer;
            transition: 0.25s;
            text-decoration: none;
        }
        .btn-print-all {
            background: linear-gradient(135deg, #2563EB, #1E40AF);
            color: #fff;
            box-shadow: 0 8px 20px rgba(37,99,235,0.4);
        }
        .btn-print-all:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(37,99,235,0.55); color: #fff; }
        .btn-print-all i { color: #FCD34D; }
        .btn-back {
            background: transparent;
            color: #94A3B8;
            border: 1.5px solid #1E293B;
        }
        .btn-back:hover { background: #1E293B; color: #fff; border-color: #2A3854; }

        /* ===== HALAMAN CETAK ===== */
        .page {
            background: #fff;
            color: #0B1120;
            border-radius: 20px;
            padding: 28px;
            margin: 0 auto 30px;
            max-width: 560px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            page-break-after: always;
            position: relative;
        }
        .page:last-child { page-break-after: auto; }
        .page-ticket { border: 1px solid #E2E8F0; }

        .page-voucher {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 3px dashed #F59E0B;
        }

        /* ===== BRAND ===== */
        .ticket-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px dashed #CBD5E1;
        }
        .page-voucher .ticket-brand { border-bottom-color: #D97706; }

        .ticket-brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563EB, #1E40AF);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(37,99,235,0.3);
        }
        .page-voucher .ticket-brand-logo {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            box-shadow: 0 6px 16px rgba(245,158,11,0.4);
        }

        .ticket-brand-text {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #0B1120;
        }
        .ticket-brand-text span { color: #2563EB; }
        .page-voucher .ticket-brand-text span { color: #92400E; }

        .ticket-type-badge {
            margin-left: auto;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .badge-tiket {
            background: rgba(37,99,235,0.1);
            color: #2563EB;
            border: 1.5px solid rgba(37,99,235,0.3);
        }
        .badge-voucher {
            background: #F59E0B;
            color: #fff;
        }

        /* ===== FILM HEAD (POSTER + INFO) ===== */
        .film-head {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px dashed #CBD5E1;
        }
        .page-voucher .film-head { border-bottom-color: #D97706; }

        .poster-img {
            width: 90px;
            height: 130px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #E2E8F0;
            flex-shrink: 0;
            background: #0B1120;
        }
        .page-voucher .poster-img { border-color: #D97706; }

        .film-head-info { min-width: 0; flex: 1; }

        .film-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0B1120;
            letter-spacing: -0.4px;
            margin-bottom: 8px;
            line-height: 1.15;
        }
        .film-studio {
            color: #64748B;
            font-size: 0.86rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .film-studio i { color: #2563EB; width: 14px; }
        .page-voucher .film-studio i { color: #92400E; }

        /* ===== INFO LIST ===== */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 14px 0;
            border-top: 1px dashed #CBD5E1;
            border-bottom: 1px dashed #CBD5E1;
            margin-bottom: 16px;
        }
        .page-voucher .info-list { border-color: #D97706; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            gap: 15px;
        }
        .info-row .label {
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .page-voucher .info-row .label { color: #92400E; }
        .info-row .label i { color: #2563EB; width: 14px; font-size: 0.82rem; }
        .page-voucher .info-row .label i { color: #D97706; }
        .info-row .value {
            color: #0B1120;
            font-weight: 700;
            text-align: right;
            max-width: 62%;
            word-break: break-word;
        }
        .info-row .value.kode {
            color: #2563EB;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            font-size: 0.92rem;
        }
        .page-voucher .info-row .value.kode { color: #92400E; }
        .info-row .value.seats { color: #2563EB; letter-spacing: 0.5px; }

        /* ===== BARCODE ===== */
        .barcode-box {
            text-align: center;
            padding: 16px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            margin: 16px 0;
        }
        .page-voucher .barcode-box {
            background: #fff;
            border-color: #D97706;
        }
        .barcode-svg-wrap { display: inline-block; }
        .barcode-svg-wrap svg { display: block; max-width: 100%; height: auto; }

        /* ===== TOTAL ===== */
        .total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: rgba(37,99,235,0.08);
            border: 1.5px solid rgba(37,99,235,0.25);
            border-radius: 12px;
            margin-top: 16px;
        }
        .page-voucher .total-box {
            background: rgba(245,158,11,0.15);
            border-color: #D97706;
        }
        .total-box .label {
            color: #64748B;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .page-voucher .total-box .label { color: #92400E; }
        .total-box .value {
            font-size: 1.35rem;
            font-weight: 800;
            color: #2563EB;
        }
        .page-voucher .total-box .value { color: #92400E; }

        /* ===== VOUCHER HEADER ===== */
        .voucher-header-badge {
            text-align: center;
            margin-bottom: 16px;
        }
        .voucher-header-badge .badge-icon {
            display: inline-block;
            padding: 8px 24px;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #fff;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 1.5px;
            box-shadow: 0 6px 16px rgba(245,158,11,0.4);
        }
        .voucher-subtitle {
            text-align: center;
            color: #92400E;
            font-size: 0.78rem;
            margin-top: 8px;
            font-weight: 600;
        }

        .voucher-source {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.55);
            border: 1px dashed #D97706;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: #92400E;
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .voucher-source i { color: #D97706; flex-shrink: 0; }

        .voucher-instruction {
            background: rgba(255,255,255,0.6);
            border: 1px dashed #D97706;
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 16px;
            font-size: 0.78rem;
            color: #92400E;
            line-height: 1.6;
        }
        .voucher-instruction strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .voucher-instruction ol {
            margin-left: 18px;
            margin-top: 4px;
        }
        .voucher-instruction ol li { margin-bottom: 3px; }

        /* ===== FOOTER ===== */
        .page-footer {
            text-align: center;
            font-size: 0.7rem;
            color: #94A3B8;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #E2E8F0;
        }
        .page-voucher .page-footer {
            border-top-color: #D97706;
            color: #92400E;
        }

        /* ===== PRINT ===== */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .page {
                box-shadow: none;
                border-radius: 0;
                padding: 20px 30px;
                margin: 0;
                max-width: 100%;
                page-break-after: always;
                border: 2px solid #000 !important;
            }
            .page:last-child { page-break-after: auto; }
            .page-voucher { background: #fff; border-style: dashed !important; }
        }

        @media (max-width: 600px) {
            .toolbar { max-width: 100%; }
            .page { padding: 22px; }
            .film-title { font-size: 1.2rem; }
            .poster-img { width: 72px; height: 104px; }
        }
    </style>
</head>
<body>

    <!-- ===== TOOLBAR ===== -->
    <div class="toolbar">
        <button class="btn-toolbar btn-print-all" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak Semua (<?= $totalHalaman; ?> halaman)
        </button>
        <a href="dashboard.php" class="btn-toolbar btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- ==================================================
         HALAMAN 1 : TIKET UTAMA (LENGKAP + POSTER)
         ================================================== -->
    <div class="page page-ticket">
        <div class="ticket-brand">
            <div class="ticket-brand-logo"><i class="fas fa-film"></i></div>
            <div class="ticket-brand-text">WIZ<span>CINEMA</span></div>
            <div class="ticket-type-badge badge-tiket">TIKET</div>
        </div>

        <div class="film-head">
            <img src="assets/img/<?= htmlspecialchars($posterFile); ?>" 
                 class="poster-img" 
                 onerror="this.src='assets/img/default.jpg'" 
                 alt="Poster <?= htmlspecialchars($order['judul']); ?>">
            <div class="film-head-info">
                <div class="film-title"><?= htmlspecialchars($order['judul']); ?></div>
                <div class="film-studio"><i class="fas fa-building"></i> <?= htmlspecialchars($namaMall); ?></div>
                <div class="film-studio"><i class="fas fa-couch"></i> Studio <?= htmlspecialchars($order['studio']); ?></div>
                <?php if (!empty($order['genre'])): ?>
                    <div class="film-studio"><i class="fas fa-tag"></i> <?= htmlspecialchars($order['genre']); ?> · <?= htmlspecialchars($order['durasi'] ?? '-'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-list">
            <div class="info-row">
                <span class="label"><i class="fas fa-receipt"></i> Kode Booking</span>
                <span class="value kode"><?= htmlspecialchars($order['kode_booking']); ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-user"></i> Pemesan</span>
                <span class="value"><?= htmlspecialchars($order['nama_pemesan'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar-plus"></i> Tanggal Pesan</span>
                <span class="value">
                    <?= !empty($order['tanggal_pesan']) ? date('d M Y H:i', strtotime($order['tanggal_pesan'])) . ' WIB' : '-'; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar"></i> Tanggal Tayang</span>
                <span class="value"><?= date('d M Y', strtotime($order['tanggal'])); ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-clock"></i> Jam Tayang</span>
                <span class="value"><?= date('H:i', strtotime($order['jam'])); ?> WIB</span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-chair"></i> Kursi</span>
                <span class="value seats"><?= htmlspecialchars($kursiStr); ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-wallet"></i> Metode</span>
                <span class="value"><?= strtoupper($order['metode'] ?? 'Cash'); ?></span>
            </div>
            <?php if ($totalVoucher > 0): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-gift"></i> Bonus</span>
                <span class="value" style="color:#D97706;"><?= $totalVoucher; ?> Voucher Gratis</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="barcode-box">
            <div class="barcode-svg-wrap">
                <svg id="barcodeTiket"></svg>
            </div>
        </div>

        <div class="total-box">
            <span class="label">Total</span>
            <span class="value">Rp <?= number_format($order['total_harga'], 0, ',', '.'); ?></span>
        </div>

        <div class="page-footer">
            <i class="fas fa-check-circle"></i> Tunjukkan barcode ini di pintu masuk studio
        </div>
    </div>

    <!-- ==================================================
         HALAMAN 2+ : VOUCHER GRATIS (OTOMATIS IKUT)
         ================================================== -->
    <?php if ($totalVoucher > 0): ?>
        <?php $vouchers->data_seek(0); $vNum = 1; while ($v = $vouchers->fetch_assoc()): ?>
            <div class="page page-voucher">
                <div class="voucher-header-badge">
                    <div class="badge-icon">🎁 VOUCHER GRATIS</div>
                    <div class="voucher-subtitle">Tiket nonton gratis — berlaku untuk film apa saja</div>
                </div>

                <div class="ticket-brand">
                    <div class="ticket-brand-logo"><i class="fas fa-gift"></i></div>
                    <div class="ticket-brand-text">WIZ<span>CINEMA</span></div>
                    <div class="ticket-type-badge badge-voucher">#<?= $vNum; ?> / <?= $totalVoucher; ?></div>
                </div>

                <div class="film-head">
                    <img src="assets/img/<?= htmlspecialchars($posterFile); ?>" 
                         class="poster-img" 
                         onerror="this.src='assets/img/default.jpg'" 
                         alt="Poster">
                    <div class="film-head-info">
                        <div class="film-title">Tiket Gratis</div>
                        <div class="film-studio"><i class="fas fa-film"></i> Berlaku untuk film apa saja</div>
                        <div class="film-studio"><i class="fas fa-user"></i> <?= htmlspecialchars($order['nama_pemesan'] ?? '-'); ?></div>
                    </div>
                </div>

                <div class="voucher-source">
                    <i class="fas fa-info-circle"></i>
                    <span>Voucher ini Anda dapatkan dari pembelian tiket <strong><?= htmlspecialchars($order['judul']); ?></strong> (booking <strong><?= htmlspecialchars($order['kode_booking']); ?></strong>).</span>
                </div>

                <div class="info-list">
                    <div class="info-row">
                        <span class="label"><i class="fas fa-ticket-alt"></i> Kode Voucher</span>
                        <span class="value kode"><?= htmlspecialchars($v['kode_voucher']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-gift"></i> Dari Promo</span>
                        <span class="value"><?= htmlspecialchars($v['promo_judul'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-calendar-check"></i> Berlaku s/d</span>
                        <span class="value">
                            <?= !empty($v['expired_at']) ? date('d M Y', strtotime($v['expired_at'])) : 'Tidak terbatas'; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-info-circle"></i> Status</span>
                        <span class="value"><?= strtoupper($v['status']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-receipt"></i> Dari Booking</span>
                        <span class="value kode"><?= htmlspecialchars($order['kode_booking']); ?></span>
                    </div>
                </div>

                <div class="barcode-box">
                    <div class="barcode-svg-wrap">
                        <svg class="barcode-voucher" data-kode="<?= htmlspecialchars($v['kode_voucher']); ?>"></svg>
                    </div>
                </div>

                <div class="voucher-instruction">
                    <strong><i class="fas fa-list-ol"></i> Cara Pakai:</strong>
                    <ol>
                        <li>Buka menu <strong>"Voucher Saya"</strong> di aplikasi.</li>
                        <li>Pilih voucher, klik <strong>"Gunakan Voucher"</strong>.</li>
                        <li>Pilih film, jadwal, dan kursi — total otomatis <strong>Rp 0</strong>.</li>
                        <li>Cetak tiket gratis Anda.</li>
                    </ol>
                </div>

                <div class="page-footer">
                    <i class="fas fa-star"></i> Voucher gratis untuk 1 tiket film apa saja
                </div>
            </div>
        <?php $vNum++; endwhile; ?>
    <?php endif; ?>

    <script>
        // Barcode tiket utama
        JsBarcode("#barcodeTiket", <?= json_encode($order['kode_booking']); ?>, {
            format: "CODE128",
            lineColor: "#0B1120",
            width: 2,
            height: 70,
            displayValue: false,
            background: "#F8FAFC"
        });

        // Barcode semua voucher
        document.querySelectorAll('.barcode-voucher').forEach(function(el) {
            JsBarcode(el, el.dataset.kode, {
                format: "CODE128",
                lineColor: "#92400E",
                width: 2,
                height: 70,
                displayValue: false,
                background: "#FFFFFF"
            });
        });
    </script>
</body>
</html>