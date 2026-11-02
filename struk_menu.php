<?php
session_start();
include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_pesanan = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM pesanan_menu WHERE id_pesanan_menu = ?");
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    header('Location: index.php');
    exit;
}

$kode_pesanan = !empty($pesanan['kode_pesanan']) 
    ? $pesanan['kode_pesanan'] 
    : 'MN-' . date('Ymd', strtotime($pesanan['created_at'])) . '-' . str_pad($pesanan['id_pesanan_menu'], 4, '0', STR_PAD_LEFT);

$detail = $conn->query("SELECT * FROM detail_pesanan_menu WHERE id_pesanan_menu = " . $id_pesanan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1280">
    <title>Struk Pesanan - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            align-items: center;
            justify-content: center;
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
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(130px);
        }
        .orb.o1 { width: 600px; height: 600px; background: #1E40AF; top: -300px; left: -200px; opacity: 0.5; }
        .orb.o2 { width: 500px; height: 500px; background: #2563EB; bottom: -250px; right: -150px; opacity: 0.4; }
        .orb.o3 { width: 350px; height: 350px; background: #FBBF24; top: 40%; right: 15%; opacity: 0.10; }

        .struk-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 500px;
        }

        .struk {
            background: #FFFFFF;
            color: #0B1120;
            border-radius: 22px;
            overflow: hidden;
            box-shadow:
                0 40px 80px rgba(0,0,0,0.6),
                0 0 70px rgba(37,99,235,0.2);
            position: relative;
        }

        .struk-header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: #fff;
            padding: 28px 32px 24px;
            position: relative;
            overflow: hidden;
        }
        .struk-header::before {
            content: '';
            position: absolute;
            top: -80px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(251,191,36,0.3), transparent 65%);
        }
        .struk-header::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FCD34D, #F59E0B, #FCD34D);
            background-size: 200% 100%;
            animation: shineMove 3.5s linear infinite;
        }
        @keyframes shineMove {
            0%   { background-position: 0% 0; }
            100% { background-position: 200% 0; }
        }

        .struk-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
        }
        .struk-brand-logo {
            width: 42px; height: 42px;
            position: relative;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.35));
            flex-shrink: 0;
        }
        .struk-brand-logo svg { width: 100%; height: 100%; display: block; }
        .struk-brand-logo::after {
            content: '';
            position: absolute;
            top: -3px; right: -3px;
            width: 11px; height: 11px;
            border-radius: 50%;
            background: #FBBF24;
            box-shadow: 0 0 10px rgba(251,191,36,0.9);
            border: 2px solid #2563EB;
        }
        .struk-brand-text {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.3px;
            color: #fff;
        }
        .struk-brand-text span { color: #FCD34D; }

        .struk-status {
            position: absolute;
            top: 26px;
            right: 32px;
            background: rgba(251,191,36,0.15);
            border: 1.5px solid rgba(251,191,36,0.5);
            color: #FCD34D;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            z-index: 2;
        }
        .struk-status .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #FCD34D;
            box-shadow: 0 0 8px rgba(251,191,36,0.9);
        }

        .struk-body {
            padding: 26px 32px 0;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #CBD5E1;
        }
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
        .info-row .label i {
            color: #2563EB;
            width: 14px;
            font-size: 0.82rem;
        }
        .info-row .value {
            color: #0B1120;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
            max-width: 60%;
        }
        .info-row .value.kode {
            color: #2563EB;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .items-title {
            color: #64748B;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 20px 0 12px;
        }
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #CBD5E1;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            font-size: 0.9rem;
        }
        .item-row .item-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }
        .item-row .item-name {
            color: #0B1120;
            font-weight: 700;
        }
        .item-row .item-qty {
            color: #64748B;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .item-row .item-price {
            color: #0B1120;
            font-weight: 700;
            white-space: nowrap;
        }

        .total-box {
            margin: 20px 0 22px;
            padding: 18px 22px;
            background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(30,64,175,0.05));
            border: 1.5px solid rgba(37,99,235,0.25);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-box .label {
            color: #64748B;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .total-box .value {
            font-size: 1.4rem;
            font-weight: 800;
            color: #2563EB;
        }

        .barcode-box {
            text-align: center;
            padding-bottom: 24px;
        }
        .barcode-label {
            color: #64748B;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .barcode-svg-wrap {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px;
            display: inline-block;
        }
        .barcode-svg-wrap svg { display: block; max-width: 100%; height: auto; }

        .struk-actions {
            padding: 0 32px 32px;
        }
        .btn-print {
            width: 100%;
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 0.92rem;
            font-weight: 700;
            font-family: inherit;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 10px 24px rgba(37,99,235,0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            margin-bottom: 10px;
        }
        .btn-print::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(251,191,36,0.35), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        .btn-print:hover::before { transform: translateX(100%); }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(37,99,235,0.5);
            color: #fff;
        }
        .btn-print i { color: #FCD34D; }

        .btn-back {
            width: 100%;
            background: transparent;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            padding: 13px;
            font-weight: 600;
            font-size: 0.88rem;
            font-family: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.22s;
        }
        .btn-back:hover {
            background: #F8FAFC;
            color: #0B1120;
            border-color: #CBD5E1;
        }
        .btn-back i { color: #2563EB; }

        @media print {
            body { background: #fff; padding: 0; }
            .bg-orbs { display: none; }
            .struk {
                box-shadow: none;
                border: 2px solid #000;
            }
            .struk-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="bg-orbs">
        <div class="orb o1"></div>
        <div class="orb o2"></div>
        <div class="orb o3"></div>
    </div>

    <div class="struk-wrap">
        <div class="struk">

            <div class="struk-header">
                <div class="struk-brand">
                    <div class="struk-brand-logo">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <defs>
                                <linearGradient id="strukLogoGrad" x1="0" y1="0" x2="48" y2="48">
                                    <stop stop-color="#3B82F6"/>
                                    <stop offset="1" stop-color="#1E40AF"/>
                                </linearGradient>
                            </defs>
                            <rect width="48" height="48" rx="14" fill="url(#strukLogoGrad)"/>
                            <rect x="4" y="4" width="40" height="20" rx="10" fill="white" opacity="0.06"/>
                            <path d="M19 15L35 24L19 33V15Z" 
                                  fill="#FCD34D" 
                                  stroke="#FCD34D" 
                                  stroke-width="2.5" 
                                  stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="struk-brand-text">WIZ<span>CINEMA</span></div>
                </div>

                <div class="struk-status">
                    <span class="dot"></span> <?= htmlspecialchars($pesanan['status'] ?? 'Pending'); ?>
                </div>
            </div>

            <div class="struk-body">

                <div class="info-list">
                    <div class="info-row">
                        <span class="label"><i class="fas fa-receipt"></i> Kode Pesanan</span>
                        <span class="value kode"><?= htmlspecialchars($kode_pesanan); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-user"></i> Pemesan</span>
                        <span class="value"><?= htmlspecialchars($pesanan['nama_pemesan'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-wallet"></i> Metode</span>
                        <span class="value"><?= strtoupper($pesanan['metode_pembayaran'] ?? 'Cash'); ?></span>
                    </div>
                    <?php if (!empty($pesanan['nomor_pembayaran'])): ?>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-hashtag"></i> Nomor</span>
                        <span class="value"><?= htmlspecialchars($pesanan['nomor_pembayaran']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-calendar"></i> Tanggal</span>
                        <span class="value"><?= date('d M Y H:i', strtotime($pesanan['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($pesanan['catatan'])): ?>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-comment"></i> Catatan</span>
                        <span class="value"><?= htmlspecialchars($pesanan['catatan']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="items-title">Detail Pesanan</div>
                <div class="items-list">
                    <?php while ($d = $detail->fetch_assoc()): 
                        $opsi = json_decode($d['opsi_tambahan'] ?? '[]', true);
                        $opsi_str = is_array($opsi) ? implode(', ', $opsi) : '';
                    ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><?= htmlspecialchars($d['nama_item'] ?? '-'); ?></div>
                                <div class="item-qty">x<?= (int)($d['jumlah'] ?? 1); ?><?= $opsi_str ? ' · ' . htmlspecialchars($opsi_str) : ''; ?></div>
                            </div>
                            <div class="item-price">Rp <?= number_format($d['subtotal'] ?? 0, 0, ',', '.'); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="total-box">
                    <span class="label">Total</span>
                    <span class="value">Rp <?= number_format($pesanan['total_harga'] ?? 0, 0, ',', '.'); ?></span>
                </div>

                <div class="barcode-box">
                    <div class="barcode-label">Kode Pesanan</div>
                    <div class="barcode-svg-wrap">
                        <svg id="barcode"></svg>
                    </div>
                </div>
            </div>

            <div class="struk-actions">
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Cetak Struk
                </button>
                <a href="menu.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <script>
        JsBarcode("#barcode", "<?= htmlspecialchars($kode_pesanan); ?>", {
            format: "CODE128",
            lineColor: "#0B1120",
            width: 2,
            height: 70,
            displayValue: false,
            background: "#F8FAFC"
        });
    </script>
</body>
</html>