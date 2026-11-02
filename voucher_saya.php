<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$id_user = (int)$_SESSION['id_user'];

$conn->query("UPDATE voucher_gratis SET status='expired' WHERE id_user = $id_user AND status='active' AND expired_at IS NOT NULL AND expired_at < NOW()");

$vouchers = $conn->query("
    SELECT v.*, p.judul AS promo_judul 
    FROM voucher_gratis v 
    LEFT JOIN promo p ON v.id_promo = p.id_promo 
    WHERE v.id_user = $id_user 
    ORDER BY 
        FIELD(v.status, 'active', 'used', 'expired'),
        v.created_at DESC
");

$totalActive  = $conn->query("SELECT COUNT(*) AS c FROM voucher_gratis WHERE id_user = $id_user AND status='active'")->fetch_assoc()['c'];
$totalUsed    = $conn->query("SELECT COUNT(*) AS c FROM voucher_gratis WHERE id_user = $id_user AND status='used'")->fetch_assoc()['c'];
$totalExpired = $conn->query("SELECT COUNT(*) AS c FROM voucher_gratis WHERE id_user = $id_user AND status='expired'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Saya - WizCinema</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 26px;
        }
        .page-header h4 {
            font-weight: 800; font-size: 1.55rem; color: #fff;
            display: flex; align-items: center; gap: 12px;
            letter-spacing: -0.3px; margin: 0;
        }
        .page-header h4 i {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(251,191,36,0.35);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }
        .stat-card {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); border-color: #FBBF24; }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent);
        }
        .stat-card.active   { --accent: #FBBF24; }
        .stat-card.used     { --accent: #10B981; }
        .stat-card.expired  { --accent: #EF4444; }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-card .label {
            color: #94A3B8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 18px;
        }

        .voucher-card {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 2px dashed #F59E0B;
            border-radius: 18px;
            padding: 22px;
            color: #78350F;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        .voucher-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245,158,11,0.3), transparent 65%);
            pointer-events: none;
        }
        .voucher-card:hover:not(.used):not(.expired) {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(245,158,11,0.4);
        }

        .voucher-card.used,
        .voucher-card.expired {
            background: #1E293B;
            border-color: #334155;
            color: #64748B;
            opacity: 0.7;
        }

        .voucher-badge {
            display: inline-block;
            background: #F59E0B;
            color: #fff;
            padding: 5px 14px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.68rem;
            letter-spacing: 1px;
            margin-bottom: 14px;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
        }
        .voucher-card.used .voucher-badge { background: #475569; }
        .voucher-card.expired .voucher-badge { background: #7F1D1D; }

        .voucher-kode {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
            word-break: break-all;
        }

        .voucher-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .voucher-info-row .label { opacity: 0.75; font-weight: 600; }
        .voucher-info-row .value { font-weight: 700; }

        .btn-pakai {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.88rem;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: 0.25s;
            box-shadow: 0 6px 16px rgba(245,158,11,0.4);
            position: relative;
            z-index: 1;
        }
        .btn-pakai:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(245,158,11,0.6);
            color: #fff;
        }
        .btn-pakai i { color: #FEF3C7; }

        .voucher-used-info {
            text-align: center;
            font-size: 0.78rem;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed currentColor;
            position: relative;
            z-index: 1;
            opacity: 0.8;
        }

        .empty-box {
            grid-column: 1/-1;
            text-align: center;
            padding: 70px 20px;
            color: #64748B;
            background: #111A2E;
            border: 1px dashed #1E293B;
            border-radius: 18px;
        }
        .empty-box i {
            font-size: 3rem;
            opacity: 0.25;
            display: block;
            margin-bottom: 14px;
        }
        .empty-box p { font-size: 0.92rem; margin-bottom: 20px; }
        .btn-explore {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            color: #1F2937;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: 0.25s;
        }
        .btn-explore:hover {
            color: #1F2937;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(251,191,36,0.4);
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
        }
        @media (max-width: 480px) {
            .voucher-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <h4><i class="fas fa-gift"></i> Voucher Saya</h4>
        </div>

        <?php if(isset($_SESSION['message'])): ?>
            <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); color:#6EE7B7; border-radius:10px; padding:12px 16px; font-size:0.85rem; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']); ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#FCA5A5; border-radius:10px; padding:12px 16px; font-size:0.85rem; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card active">
                <div class="number"><?= $totalActive; ?></div>
                <div class="label">Voucher Aktif</div>
            </div>
            <div class="stat-card used">
                <div class="number"><?= $totalUsed; ?></div>
                <div class="label">Sudah Dipakai</div>
            </div>
            <div class="stat-card expired">
                <div class="number"><?= $totalExpired; ?></div>
                <div class="label">Kadaluarsa</div>
            </div>
        </div>

        <div class="voucher-grid">
            <?php if ($vouchers->num_rows > 0): ?>
                <?php while ($v = $vouchers->fetch_assoc()): 
                    $isExpired = ($v['status'] === 'expired') || 
                                 ($v['status'] === 'active' && !empty($v['expired_at']) && strtotime($v['expired_at']) < time());
                ?>
                <div class="voucher-card <?= $v['status'] === 'used' ? 'used' : ($isExpired ? 'expired' : ''); ?>">
                    <div class="voucher-badge">
                        <?php if ($v['status'] === 'used'): ?>
                            <i class="fas fa-check-double"></i> SUDAH DIPAKAI
                        <?php elseif ($isExpired): ?>
                            <i class="fas fa-clock"></i> KADALUARSA
                        <?php else: ?>
                            🎁 VOUCHER GRATIS
                        <?php endif; ?>
                    </div>

                    <div class="voucher-kode"><?= htmlspecialchars($v['kode_voucher']); ?></div>

                    <div class="voucher-info-row">
                        <span class="label">Dari Promo</span>
                        <span class="value"><?= htmlspecialchars($v['promo_judul'] ?? '-'); ?></span>
                    </div>
                    <div class="voucher-info-row">
                        <span class="label">Berlaku</span>
                        <span class="value"><?= $v['expired_at'] ? date('d M Y', strtotime($v['expired_at'])) : 'Tanpa batas'; ?></span>
                    </div>
                    <div class="voucher-info-row">
                        <span class="label">Untuk</span>
                        <span class="value">Film apa saja</span>
                    </div>

                    <?php if ($v['status'] === 'active' && !$isExpired): ?>
                        <a href="pakai_voucher.php?id=<?= $v['id_voucher']; ?>" class="btn-pakai">
                            <i class="fas fa-ticket-alt"></i> Gunakan Voucher
                        </a>
                    <?php elseif ($v['status'] === 'used'): ?>
                        <div class="voucher-used-info">
                            <i class="fas fa-check-circle"></i>
                            Dipakai <?= $v['used_at'] ? date('d M Y, H:i', strtotime($v['used_at'])) : ''; ?>
                        </div>
                    <?php else: ?>
                        <div class="voucher-used-info">
                            <i class="fas fa-clock"></i> Voucher sudah kadaluarsa
                        </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-box">
                    <i class="fas fa-gift"></i>
                    <p>Belum ada voucher. Klaim promo <strong>"Beli X Gratis Y"</strong> untuk mendapat voucher gratis!</p>
                    <a href="index.php" class="btn-explore">
                        <i class="fas fa-fire"></i> Lihat Promo Tersedia
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>