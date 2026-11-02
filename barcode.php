<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$id_pemesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_pemesanan) {
    $_SESSION['error'] = "Data pemesanan tidak ditemukan.";
    header('Location: dashboard.php');
    exit;
}

$id_user = (int)$_SESSION['id_user'];
$role    = $_SESSION['role'] ?? 'user';

if ($role === 'admin' || $role === 'kasir') {
    $query = "SELECT p.*, f.judul, j.studio, j.tanggal, j.jam 
              FROM pemesanan p 
              JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
              JOIN film f ON j.id_film = f.id_film 
              WHERE p.id_pemesanan = $id_pemesanan";
} else {
    $query = "SELECT p.*, f.judul, j.studio, j.tanggal, j.jam 
              FROM pemesanan p 
              JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
              JOIN film f ON j.id_film = f.id_film 
              WHERE p.id_pemesanan = $id_pemesanan AND p.id_user = $id_user";
}

$result = $conn->query($query);
if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Pemesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: dashboard.php');
    exit;
}

$order = $result->fetch_assoc();

$jadwal_dt   = strtotime($order['tanggal'] . ' ' . $order['jam']);
$batas_cetak = $jadwal_dt + (24 * 60 * 60);

if ($role === 'user' && time() > $batas_cetak) {
    $_SESSION['error'] = "Tiket tidak dapat dicetak — sudah lebih dari 1 hari sejak jadwal tayang.";
    header('Location: dashboard.php');
    exit;
}

if ($role === 'user' && !in_array($order['status'], ['Lunas', 'Pending'])) {
    $_SESSION['error'] = "Tiket tidak dapat dicetak — status pesanan: " . $order['status'] . ".";
    header('Location: dashboard.php');
    exit;
}

$kursiList = [];
$kursiResult = $conn->query("SELECT kode_kursi FROM detail_pemesanan WHERE id_pemesanan = $id_pemesanan ORDER BY kode_kursi");
while ($k = $kursiResult->fetch_assoc()) $kursiList[] = $k['kode_kursi'];
$kursiStr = implode(', ', $kursiList);

// ===== AMBIL VOUCHER =====
$voucherList = [];
$vQ = $conn->query("
    SELECT v.*, p.judul AS promo_judul 
    FROM voucher_gratis v 
    LEFT JOIN promo p ON v.id_promo = p.id_promo 
    WHERE v.id_pemesanan_asal = $id_pemesanan 
      AND v.id_user = " . (int)$order['id_user'] . "
    ORDER BY v.id_voucher ASC
");
if ($vQ) {
    while ($v = $vQ->fetch_assoc()) $voucherList[] = $v;
}
$totalVoucher = count($voucherList);

$now = time();
$isInGrace = ($now >= $jadwal_dt && $now <= $batas_cetak);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1280">
    <title>Cetak Tiket<?= $totalVoucher > 0 ? ' + Voucher' : ''; ?> - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0B1120;
            color: #F1F5F9;
            min-width: 1024px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-orbs {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .orb { position: absolute; border-radius: 50%; filter: blur(130px); }
        .orb.o1 { width: 600px; height: 600px; background: #1E40AF; top: -300px; left: -200px; opacity: 0.5; }
        .orb.o2 { width: 500px; height: 500px; background: #2563EB; bottom: -250px; right: -150px; opacity: 0.4; }
        .orb.o3 { width: 350px; height: 350px; background: #FBBF24; top: 40%; right: 15%; opacity: 0.10; }

        .ticket-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 560px;
        }

        .toolbar { display: flex; gap: 10px; margin-bottom: 18px; }
        .btn-toolbar {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: 0.25s;
        }
        .btn-print-all {
            background: linear-gradient(135deg, #2563EB, #1E40AF);
            color: #fff;
            box-shadow: 0 10px 25px rgba(37,99,235,0.35);
        }
        .btn-print-all:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(37,99,235,0.5); color: #fff; }
        .btn-print-all i { color: #FCD34D; }
        .btn-back {
            background: transparent;
            color: #94A3B8;
            border: 1.5px solid #1E293B;
            flex: 0 0 auto;
            min-width: 130px;
        }
        .btn-back:hover { background: #1E293B; color: #fff; }

        .info-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .info-banner.warning { background: rgba(251,191,36,0.12); border: 1.5px solid rgba(251,191,36,0.4); color: #FCD34D; }
        .info-banner.success { background: rgba(16,185,129,0.12); border: 1.5px solid rgba(16,185,129,0.4); color: #6EE7B7; }
        .info-banner i { font-size: 1.1rem; flex-shrink: 0; }
        .info-banner .banner-text { flex: 1; line-height: 1.5; }
        .info-banner .banner-text strong { display: block; font-size: 0.9rem; margin-bottom: 2px; }

        /* ====== SINGLE SHEET (TIKET + VOUCHER) ====== */
        .single-sheet {
            background: #FFFFFF;
            color: #0B1120;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 70px rgba(37,99,235,0.2);
            position: relative;
        }

        /* ===== TIKET BAGIAN ATAS ===== */
        .ticket-header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: #fff;
            padding: 24px 32px 20px;
            position: relative;
            overflow: hidden;
        }
        .ticket-header::before {
            content: '';
            position: absolute;
            top: -80px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(251,191,36,0.3), transparent 65%);
        }
        .ticket-brand { display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; }
        .ticket-brand-logo {
            width: 42px; height: 42px;
            position: relative;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.35));
            flex-shrink: 0;
        }
        .ticket-brand-logo svg { width: 100%; height: 100%; display: block; }
        .ticket-brand-text { font-size: 1.15rem; font-weight: 800; letter-spacing: 0.3px; color: #fff; }
        .ticket-brand-text span { color: #FCD34D; }

        .ticket-status {
            position: absolute;
            top: 22px; right: 32px;
            background: rgba(251,191,36,0.15);
            border: 1.5px solid rgba(251,191,36,0.5);
            color: #FCD34D;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            z-index: 2;
        }
        .ticket-status .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #FCD34D;
            box-shadow: 0 0 8px rgba(251,191,36,0.9);
        }

        .ticket-body { padding: 24px 32px 20px; }

        .ticket-film-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0B1120;
            letter-spacing: -0.4px;
            margin-bottom: 4px;
        }
        .ticket-film-studio {
            color: #64748B;
            font-size: 0.86rem;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ticket-film-studio i { color: #2563EB; }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
            padding: 16px 0;
            border-top: 1px dashed #CBD5E1;
            border-bottom: 1px dashed #CBD5E1;
        }
        .detail-label {
            color: #64748B;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .detail-label i { color: #2563EB; font-size: 0.7rem; }
        .detail-value { color: #0B1120; font-size: 0.9rem; font-weight: 700; }
        .detail-value.seats { color: #2563EB; letter-spacing: 0.5px; }

        .barcode-box { margin-top: 18px; text-align: center; }
        .barcode-label {
            color: #64748B;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .barcode-svg-wrap {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 12px;
            display: inline-block;
        }
        .barcode-svg-wrap svg { display: block; max-width: 100%; height: auto; }

        .kode-booking-text {
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0B1120;
            letter-spacing: 2px;
            margin-top: 10px;
        }

        .ticket-total {
            margin-top: 18px;
            padding: 14px 18px;
            background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(30,64,175,0.05));
            border: 1.5px solid rgba(37,99,235,0.25);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ticket-total-label {
            color: #64748B;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .ticket-total-value { font-size: 1.25rem; font-weight: 800; color: #2563EB; }

        /* ===== PERFORASI (GARIS SOBEK) ===== */
        .perforation {
            position: relative;
            height: 24px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .perforation::before,
        .perforation::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #0B1120;
        }
        .perforation::before { left: -12px; }
        .perforation::after { right: -12px; }
        .perforation-line {
            width: calc(100% - 40px);
            height: 0;
            border-top: 2px dashed #CBD5E1;
        }

        /* ===== VOUCHER BAGIAN BAWAH ===== */
        .voucher-section {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            padding: 22px 28px 24px;
        }
        .voucher-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 800;
            color: #78350F;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #D97706;
        }
        .voucher-section-title i { color: #D97706; font-size: 1.1rem; }
        .voucher-count-badge {
            margin-left: auto;
            background: #D97706;
            color: #fff;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .voucher-item {
            background: #FFFFFF;
            border: 1.5px dashed #D97706;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 12px;
            position: relative;
        }
        .voucher-item:last-child { margin-bottom: 0; }

        .voucher-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .voucher-item-title {
            color: #92400E;
            font-size: 0.85rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .voucher-item-title i { font-size: 0.85rem; }
        .voucher-item-num {
            background: #FEF3C7;
            color: #92400E;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            border: 1px solid #D97706;
        }

        .voucher-item-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .voucher-item-left { flex: 1; min-width: 200px; }
        .voucher-kode-label {
            font-size: 0.65rem;
            color: #92400E;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .voucher-kode {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 800;
            color: #78350F;
            letter-spacing: 1px;
            margin-bottom: 6px;
            word-break: break-all;
        }
        .voucher-info-line {
            font-size: 0.72rem;
            color: #92400E;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .voucher-info-line strong { color: #78350F; }

        .voucher-barcode-small {
            background: #FFFBF0;
            border: 1px solid #E5C68A;
            border-radius: 8px;
            padding: 8px 10px;
            flex-shrink: 0;
        }
        .voucher-barcode-small svg { display: block; height: 50px; }

        .voucher-instruction-compact {
            margin-top: 14px;
            padding: 12px 16px;
            background: rgba(255,255,255,0.55);
            border-radius: 10px;
            border: 1px dashed #D97706;
            font-size: 0.72rem;
            color: #78350F;
            line-height: 1.5;
        }
        .voucher-instruction-compact strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #92400E;
        }
        .voucher-instruction-compact i { margin-right: 5px; }

        /* ===== PRINT ===== */
        @media print {
            @page { size: A5; margin: 8mm; }
            body {
                background: #fff !important;
                padding: 0 !important;
                min-width: auto !important;
                display: block !important;
            }
            .bg-orbs, .info-banner, .toolbar { display: none !important; }
            .ticket-wrap { max-width: 100% !important; }
            .single-sheet {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                border-radius: 0 !important;
                page-break-inside: avoid;
            }
            .perforation::before,
            .perforation::after { background: #fff; }
            .ticket-header::after { display: none; }
            .voucher-section {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="bg-orbs">
        <div class="orb o1"></div>
        <div class="orb o2"></div>
        <div class="orb o3"></div>
    </div>

    <div class="ticket-wrap">

        <?php if ($isInGrace && $role === 'user'): ?>
            <div class="info-banner warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="banner-text">
                    <strong>Tiket masih bisa diakses</strong>
                    Jadwal tayang sudah dimulai. Terkunci pada <?= date('d M Y H:i', $batas_cetak); ?> WIB.
                </div>
            </div>
        <?php elseif ($role !== 'user'): ?>
            <div class="info-banner success">
                <i class="fas fa-shield-alt"></i>
                <div class="banner-text">
                    <strong>Akses <?= ucfirst($role); ?></strong>
                    Anda dapat memverifikasi tiket ini kapan saja.
                </div>
            </div>
        <?php endif; ?>

        <div class="toolbar">
            <button class="btn-toolbar btn-print-all" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Tiket<?= $totalVoucher > 0 ? ' + ' . $totalVoucher . ' Voucher' : ''; ?>
            </button>
            <a href="dashboard.php" class="btn-toolbar btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <!-- ============================================
             SINGLE SHEET: TIKET + SEMUA VOUCHER
             ============================================ -->
        <div class="single-sheet">

            <!-- TIKET UTAMA -->
            <div class="ticket-header">
                <div class="ticket-brand">
                    <div class="ticket-brand-logo">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <defs>
                                <linearGradient id="ticketLogoGrad" x1="0" y1="0" x2="48" y2="48">
                                    <stop stop-color="#3B82F6"/>
                                    <stop offset="1" stop-color="#1E40AF"/>
                                </linearGradient>
                            </defs>
                            <rect width="48" height="48" rx="14" fill="url(#ticketLogoGrad)"/>
                            <rect x="4" y="4" width="40" height="20" rx="10" fill="white" opacity="0.06"/>
                            <path d="M19 15L35 24L19 33V15Z" fill="#FCD34D" stroke="#FCD34D" stroke-width="2.5" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="ticket-brand-text">WIZ<span>CINEMA</span></div>
                </div>
                <div class="ticket-status">
                    <span class="dot"></span> <?= htmlspecialchars($order['status']); ?>
                </div>
            </div>

            <div class="ticket-body">
                <div class="ticket-film-title"><?= htmlspecialchars($order['judul']); ?></div>
                <div class="ticket-film-studio">
                    <i class="fas fa-couch"></i> Studio <?= htmlspecialchars($order['studio']); ?>
                </div>

                <div class="detail-grid">
                    <div>
                        <div class="detail-label"><i class="fas fa-calendar"></i> Tanggal</div>
                        <div class="detail-value"><?= date('d M Y', strtotime($order['tanggal'])); ?></div>
                    </div>
                    <div>
                        <div class="detail-label"><i class="fas fa-clock"></i> Jam</div>
                        <div class="detail-value"><?= date('H:i', strtotime($order['jam'])); ?> WIB</div>
                    </div>
                    <div>
                        <div class="detail-label"><i class="fas fa-user"></i> Nama</div>
                        <div class="detail-value"><?= htmlspecialchars($order['nama_pemesan'] ?? '-'); ?></div>
                    </div>
                    <div>
                        <div class="detail-label"><i class="fas fa-chair"></i> Kursi</div>
                        <div class="detail-value seats"><?= htmlspecialchars($kursiStr); ?></div>
                    </div>
                </div>

                <div class="barcode-box">
                    <div class="barcode-label">Kode Booking</div>
                    <div class="barcode-svg-wrap">
                        <svg id="barcode-tiket"></svg>
                    </div>
                    <div class="kode-booking-text"><?= htmlspecialchars($order['kode_booking']); ?></div>
                </div>

                <div class="ticket-total">
                    <span class="ticket-total-label">Total</span>
                    <span class="ticket-total-value">Rp <?= number_format($order['total_harga'], 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- PERFORASI + VOUCHER (kalau ada) -->
            <?php if ($totalVoucher > 0): ?>
                <div class="perforation"><div class="perforation-line"></div></div>

                <div class="voucher-section">
                    <div class="voucher-section-title">
                        <i class="fas fa-gift"></i> Voucher Gratis Anda
                        <span class="voucher-count-badge"><?= $totalVoucher; ?> Voucher</span>
                    </div>

                    <?php foreach ($voucherList as $idx => $v): ?>
                        <div class="voucher-item">
                            <div class="voucher-item-header">
                                <div class="voucher-item-title">
                                    <i class="fas fa-ticket-alt"></i> Tiket Nonton Gratis
                                </div>
                                <div class="voucher-item-num">#<?= $idx + 1; ?> / <?= $totalVoucher; ?></div>
                            </div>

                            <div class="voucher-item-body">
                                <div class="voucher-item-left">
                                    <div class="voucher-kode-label">Kode Voucher</div>
                                    <div class="voucher-kode"><?= htmlspecialchars($v['kode_voucher']); ?></div>
                                    <div class="voucher-info-line"><strong>Dari promo:</strong> <?= htmlspecialchars($v['promo_judul'] ?? '-'); ?></div>
                                    <div class="voucher-info-line"><strong>Berlaku s/d:</strong> <?= !empty($v['expired_at']) ? date('d M Y', strtotime($v['expired_at'])) : 'Tanpa batas'; ?></div>
                                    <div class="voucher-info-line"><strong>Untuk:</strong> Film apa saja (1 tiket gratis)</div>
                                </div>
                                <div class="voucher-barcode-small">
                                    <svg class="barcode-voucher" data-kode="<?= htmlspecialchars($v['kode_voucher']); ?>"></svg>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="voucher-instruction-compact">
                        <strong><i class="fas fa-lightbulb"></i> Cara Pakai:</strong>
                        Login → menu "Voucher Saya" → pilih voucher → "Gunakan Voucher" → pilih film & kursi → total Rp 0.
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Barcode tiket
        JsBarcode("#barcode-tiket", <?= json_encode($order['kode_booking']); ?>, {
            format: "CODE128",
            lineColor: "#0B1120",
            width: 2,
            height: 60,
            displayValue: false,
            background: "#F8FAFC"
        });

        // Barcode voucher (semua)
        document.querySelectorAll('.barcode-voucher').forEach(function(el) {
            JsBarcode(el, el.dataset.kode, {
                format: "CODE128",
                lineColor: "#78350F",
                width: 1.5,
                height: 40,
                displayValue: false,
                background: "#FFFBF0"
            });
        });
    </script>
</body>
</html>