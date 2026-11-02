<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kasir') {
    header('Location: index.php');
    exit;
}

if (isset($_POST['add_pemasukan'])) {
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $jumlah = (int)$_POST['jumlah'];
    $tanggal = $_POST['tanggal'];
    $conn->query("INSERT INTO pemasukan (deskripsi, jumlah, tanggal) VALUES ('$deskripsi', $jumlah, '$tanggal')");
    header('Location: kasir_laporan.php?notif=pemasukan');
    exit;
}

if (isset($_POST['add_pengeluaran'])) {
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $jumlah = (int)$_POST['jumlah'];
    $tanggal = $_POST['tanggal'];
    $conn->query("INSERT INTO pengeluaran (deskripsi, jumlah, tanggal) VALUES ('$deskripsi', $jumlah, '$tanggal')");
    header('Location: kasir_laporan.php?notif=pengeluaran');
    exit;
}

$pemasukan = $conn->query("SELECT * FROM pemasukan ORDER BY tanggal DESC");
$pengeluaran = $conn->query("SELECT * FROM pengeluaran ORDER BY tanggal DESC");
$laporanTiket = $conn->query("SELECT p.*, f.judul FROM pemesanan p JOIN jadwal j ON p.id_jadwal=j.id_jadwal JOIN film f ON j.id_film=f.id_film ORDER BY p.tanggal_pesan DESC");
$laporanMenu = $conn->query("SELECT pm.*, u.nama AS user_nama FROM pesanan_menu pm LEFT JOIN users u ON pm.id_user=u.id_user ORDER BY pm.created_at DESC");

$totalPemasukan = $conn->query("SELECT COALESCE(SUM(jumlah),0) AS t FROM pemasukan")->fetch_assoc()['t'];
$totalPengeluaran = $conn->query("SELECT COALESCE(SUM(jumlah),0) AS t FROM pengeluaran")->fetch_assoc()['t'];
$totalTiket = $conn->query("SELECT COALESCE(SUM(total_harga),0) AS t FROM pemesanan WHERE status='Lunas'")->fetch_assoc()['t'];
$totalMenu = $conn->query("SELECT COALESCE(SUM(total_harga),0) AS t FROM pesanan_menu WHERE status='Lunas'")->fetch_assoc()['t'];
$saldo = $totalPemasukan + $totalTiket + $totalMenu - $totalPengeluaran;

$chartQuery = $conn->query("
    SELECT tanggal, 
           (SELECT COALESCE(SUM(jumlah),0) FROM pemasukan WHERE tanggal = d.tanggal) AS masuk,
           (SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE tanggal = d.tanggal) AS keluar
    FROM (
        SELECT tanggal FROM pemasukan
        UNION
        SELECT tanggal FROM pengeluaran
        ORDER BY tanggal DESC LIMIT 7
    ) d
    ORDER BY d.tanggal ASC
");
$chartLabels = [];
$chartMasuk = [];
$chartKeluar = [];
while ($c = $chartQuery->fetch_assoc()) {
    $chartLabels[] = date('d M', strtotime($c['tanggal']));
    $chartMasuk[] = (int)$c['masuk'];
    $chartKeluar[] = (int)$c['keluar'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Kasir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#0a0a0f; color:#fff; display:flex; min-height:100vh; }
        .main-content { flex:1; padding:30px 35px; max-width:1400px; margin:0 auto; }

        .page-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:28px; }
        .page-header h4 { font-weight:800; font-size:1.55rem; color:#fff; display:flex; align-items:center; gap:12px; }
        .page-header h4 i { color:#1E88E5; }
        .page-header .badge-date {
            background:#111827; border:1px solid #1f2937; color:#94a3b8;
            padding:8px 16px; border-radius:10px; font-size:0.82rem; font-weight:600;
            display:inline-flex; align-items:center; gap:8px;
        }
        .page-header .badge-date i { color:#1E88E5; }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
        .stat-card {
            background: linear-gradient(145deg, #0f1f3a 0%, #0a1424 100%);
            border: 1px solid rgba(30,136,229,0.25);
            border-radius: 16px;
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; width:100%; height:3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }
        .stat-card.clickable { cursor: pointer; }
        .stat-card.clickable:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: 0 12px 30px rgba(30,136,229,0.2); }
        .stat-card .stat-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
        .stat-card .stat-label { color:#94a3b8; font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; font-weight:700; }
        .stat-card .stat-icon {
            width:38px; height:38px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.95rem; color:#fff;
            background: var(--accent);
            box-shadow: 0 4px 12px var(--shadow);
        }
        .stat-card .stat-value { font-size:1.55rem; font-weight:900; color:#fff; line-height:1.1; margin-bottom:6px; }
        .stat-card .stat-sub { font-size:0.75rem; color:#64748b; font-weight:500; display:flex; align-items:center; gap:6px; }
        .stat-card.clickable .stat-sub .hint { color:#1E88E5; font-weight:700; font-size:0.72rem; margin-left:auto; }

        .stat-card.blue   { --accent:#1E88E5; --shadow:rgba(30,136,229,0.3); }
        .stat-card.green  { --accent:#10b981; --shadow:rgba(16,185,129,0.3); }
        .stat-card.red    { --accent:#ef4444; --shadow:rgba(239,68,68,0.3); }
        .stat-card.gold   { --accent:#f59e0b; --shadow:rgba(245,158,11,0.3); }
        .stat-card.purple { --accent:#8b5cf6; --shadow:rgba(139,92,246,0.3); }

        .card-custom { background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px 26px; margin-bottom:22px; }
        .section-title {
            font-size:1rem; font-weight:800; color:#fff;
            margin-bottom:20px; display:flex; align-items:center; gap:12px;
            padding-bottom:14px; border-bottom:1px solid #1f2937;
        }
        .section-title i {
            width:32px; height:32px; border-radius:9px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.85rem; color:#fff;
            background:linear-gradient(135deg, #1E88E5, #0D47A1);
        }
        .section-title .title-sub { color:#64748b; font-size:0.78rem; font-weight:500; margin-left:auto; }

        .table-wrap { background:#0a0a0f !important; border:1px solid #1f2937; border-radius:12px; overflow:hidden; }
        .table-wrap table { margin:0 !important; color:#fff !important; width:100%; background:transparent !important; border-collapse:collapse; }
        .table-wrap thead th {
            background:#0f172a !important; color:#64B5F6 !important;
            padding:13px 16px; font-size:0.7rem; text-transform:uppercase;
            letter-spacing:0.8px; font-weight:800;
            border-bottom:1px solid #1f2937 !important; text-align:left;
            white-space:nowrap;
        }
        .table-wrap tbody td {
            background:#0a0a0f !important; padding:13px 16px;
            border-bottom:1px solid #1a2233 !important; color:#cbd5e1 !important;
            font-size:0.83rem; vertical-align:middle;
        }
        .table-wrap tbody tr:last-child td { border-bottom:none !important; }
        .table-wrap tbody tr:hover td { background:#0f1729 !important; }

        .cell-code { color:#64B5F6; font-weight:700; font-family:'Courier New', monospace; font-size:0.8rem; }
        .cell-name { color:#fff; font-weight:600; }
        .cell-amount { color:#cbd5e1; font-weight:700; }
        .cell-date { color:#94a3b8; font-size:0.78rem; }

        .badge-amount { font-weight:800; font-size:0.88rem; }
        .badge-amount.green { color:#5ddb7e; }
        .badge-amount.red   { color:#ff8a9a; }

        .badge-status {
            padding:5px 12px; border-radius:20px;
            font-size:0.7rem; font-weight:700; letter-spacing:0.3px;
            display:inline-block; white-space:nowrap;
        }
        .badge-status.lunas   { background:rgba(16,185,129,0.15); color:#5ddb7e; }
        .badge-status.pending { background:rgba(245,158,11,0.15); color:#fbbf24; }
        .badge-status.baru    { background:rgba(30,136,229,0.15); color:#64B5F6; }
        .badge-status.batal   { background:rgba(239,68,68,0.15);  color:#ff8a9a; }
        .badge-status.refund  { background:rgba(30,136,229,0.15); color:#64B5F6; }

        .empty-row { text-align:center; padding:35px; color:#64748b; font-size:0.85rem; background:#0a0a0f !important; }
        .empty-row i { display:block; font-size:1.8rem; opacity:0.3; margin-bottom:10px; }

        .chart-card { background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px 26px; margin-bottom:22px; }
        .chart-header {
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap:wrap; gap:15px; margin-bottom:20px; padding-bottom:14px;
            border-bottom:1px solid #1f2937;
        }
        .chart-title { display:flex; align-items:center; gap:12px; font-size:1rem; font-weight:800; color:#fff; }
        .chart-title i {
            width:32px; height:32px; border-radius:9px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.85rem; color:#fff;
            background:linear-gradient(135deg, #1E88E5, #0D47A1);
        }
        .chart-title .title-sub { display:block; font-size:0.75rem; color:#64748b; font-weight:500; margin-top:2px; }
        .chart-legend { display:flex; gap:18px; align-items:center; }
        .legend-item { display:flex; align-items:center; gap:8px; font-size:0.78rem; font-weight:600; color:#94a3b8; }
        .legend-dot { width:10px; height:10px; border-radius:50%; }
        .legend-dot.green { background:#10b981; }
        .legend-dot.red   { background:#ef4444; }
        .chart-wrap { position:relative; height:280px; padding:10px 0; }

        .row-2 { display:grid; grid-template-columns:1fr 1fr; gap:22px; }

        .modal-content {
            background: #0f1f3a; border: 1px solid rgba(30,136,229,0.3);
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
        }
        .modal-header {
            padding: 18px 24px; border-bottom: 1px solid rgba(30,136,229,0.2);
            background: rgba(30,136,229,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .modal-header .modal-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem; flex-shrink: 0;
        }
        .modal-header .modal-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header .modal-icon.red   { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .modal-header .modal-title-custom { font-size: 1rem; font-weight: 700; color: #fff; line-height: 1.2; }
        .modal-header .modal-sub { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
        .modal-header .btn-close { margin-left: auto; filter: invert(1) brightness(2); opacity: 0.5; }
        .modal-header .btn-close:hover { opacity: 1; }
        .modal-body { padding: 24px; }
        .modal-footer {
            padding: 16px 24px; border-top: 1px solid rgba(30,136,229,0.15);
            background: rgba(30,136,229,0.03); display: flex; gap: 10px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label-sm {
            font-size: 0.72rem; color: #94a3b8; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 7px;
            display: block;
        }
        .form-input {
            background: #0a0a0f; border: 1px solid rgba(30,136,229,0.25);
            color: #fff; border-radius: 10px; padding: 12px 14px;
            font-size: 0.9rem; outline: none; transition: 0.2s; width: 100%;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus { background: #0a1424; border-color: #1E88E5; box-shadow: 0 0 0 3px rgba(30,136,229,0.15); color: #fff; }

        .btn-modal {
            flex: 1; border: none; border-radius: 10px; padding: 12px;
            font-weight: 700; font-size: 0.88rem; cursor: pointer;
            transition: 0.2s; display: inline-flex; align-items: center;
            justify-content: center; gap: 8px; color: #fff;
        }
        .btn-modal.cancel { background: transparent; border: 1px solid rgba(148,163,184,0.25); color: #94a3b8; }
        .btn-modal.cancel:hover { background: rgba(148,163,184,0.08); color: #fff; border-color: #64748b; }
        .btn-modal.green { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal.green:hover { box-shadow: 0 8px 20px rgba(16,185,129,0.35); }
        .btn-modal.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn-modal.red:hover { box-shadow: 0 8px 20px rgba(239,68,68,0.35); }

        @media (max-width: 992px) { .row-2 { grid-template-columns:1fr; } }
        @media (max-width: 768px) {
            .main-content { padding:20px; }
            .stat-card .stat-value { font-size:1.3rem; }
            .chart-wrap { height:240px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <h4><i class="fas fa-chart-pie"></i> Laporan Keuangan</h4>
            <span class="badge-date"><i class="fas fa-calendar-alt"></i> <?= date('d F Y'); ?></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card green clickable" onclick="bukaModal('modalPemasukan')">
                <div class="stat-head">
                    <span class="stat-label">Pemasukan</span>
                    <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                </div>
                <div class="stat-value">Rp <?= number_format($totalPemasukan, 0, ',', '.'); ?></div>
                <div class="stat-sub">Total pemasukan manual <span class="hint"><i class="fas fa-plus-circle"></i> Klik untuk isi</span></div>
            </div>

            <div class="stat-card red clickable" onclick="bukaModal('modalPengeluaran')">
                <div class="stat-head">
                    <span class="stat-label">Pengeluaran</span>
                    <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                </div>
                <div class="stat-value">Rp <?= number_format($totalPengeluaran, 0, ',', '.'); ?></div>
                <div class="stat-sub">Total pengeluaran <span class="hint"><i class="fas fa-plus-circle"></i> Klik untuk isi</span></div>
            </div>

            <div class="stat-card blue">
                <div class="stat-head">
                    <span class="stat-label">Pemasukan Tiket</span>
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                </div>
                <div class="stat-value">Rp <?= number_format($totalTiket, 0, ',', '.'); ?></div>
                <div class="stat-sub">Dari pesanan tiket Lunas</div>
            </div>

            <div class="stat-card gold">
                <div class="stat-head">
                    <span class="stat-label">Pemasukan Menu</span>
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                </div>
                <div class="stat-value">Rp <?= number_format($totalMenu, 0, ',', '.'); ?></div>
                <div class="stat-sub">Dari pesanan menu Lunas</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-head">
                    <span class="stat-label">Saldo Bersih</span>
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-value">Rp <?= number_format($saldo, 0, ',', '.'); ?></div>
                <div class="stat-sub">Saldo akhir saat ini</div>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i>
                    <div>
                        Grafik Arus Kas
                        <span class="title-sub">7 transaksi terakhir</span>
                    </div>
                </div>
                <div class="chart-legend">
                    <div class="legend-item"><span class="legend-dot green"></span> Pemasukan</div>
                    <div class="legend-item"><span class="legend-dot red"></span> Pengeluaran</div>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="chartKas"></canvas>
            </div>
        </div>

        <div class="row-2">
            <div class="card-custom">
                <div class="section-title">
                    <i class="fas fa-arrow-down" style="background:linear-gradient(135deg,#10b981,#059669);"></i>
                    Riwayat Pemasukan
                    <span class="title-sub"><?= $pemasukan->num_rows; ?> data</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:130px;">Tanggal</th>
                                <th>Deskripsi</th>
                                <th style="width:160px; text-align:right;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pemasukan && $pemasukan->num_rows > 0): ?>
                                <?php while ($p = $pemasukan->fetch_assoc()): ?>
                                <tr>
                                    <td class="cell-date"><?= date('d M Y', strtotime($p['tanggal'])); ?></td>
                                    <td><?= htmlspecialchars($p['deskripsi']); ?></td>
                                    <td style="text-align:right;"><span class="badge-amount green">+ Rp <?= number_format($p['jumlah'], 0, ',', '.'); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-row"><i class="fas fa-inbox"></i>Belum ada data pemasukan</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-custom">
                <div class="section-title">
                    <i class="fas fa-arrow-up" style="background:linear-gradient(135deg,#ef4444,#dc2626);"></i>
                    Riwayat Pengeluaran
                    <span class="title-sub"><?= $pengeluaran->num_rows; ?> data</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:130px;">Tanggal</th>
                                <th>Deskripsi</th>
                                <th style="width:160px; text-align:right;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pengeluaran && $pengeluaran->num_rows > 0): ?>
                                <?php while ($p = $pengeluaran->fetch_assoc()): ?>
                                <tr>
                                    <td class="cell-date"><?= date('d M Y', strtotime($p['tanggal'])); ?></td>
                                    <td><?= htmlspecialchars($p['deskripsi']); ?></td>
                                    <td style="text-align:right;"><span class="badge-amount red">- Rp <?= number_format($p['jumlah'], 0, ',', '.'); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-row"><i class="fas fa-inbox"></i>Belum ada data pengeluaran</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="section-title">
                <i class="fas fa-ticket-alt"></i>
                Pesanan Tiket
                <span class="title-sub"><?= $laporanTiket->num_rows; ?> transaksi</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Booking</th>
                            <th>Film</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($laporanTiket && $laporanTiket->num_rows > 0): ?>
                            <?php while ($t = $laporanTiket->fetch_assoc()): 
                                $st = strtolower($t['status'] ?? 'pending');
                                $stClass = 'pending';
                                if ($st === 'lunas') $stClass = 'lunas';
                                elseif ($st === 'batal') $stClass = 'batal';
                                elseif ($st === 'refunded') $stClass = 'refund';
                            ?>
                            <tr>
                                <td><span class="cell-code"><?= htmlspecialchars($t['kode_booking']); ?></span></td>
                                <td><span class="cell-name"><?= htmlspecialchars($t['judul']); ?></span></td>
                                <td><span class="cell-amount">Rp <?= number_format($t['total_harga'], 0, ',', '.'); ?></span></td>
                                <td><span class="badge-status <?= $stClass; ?>"><?= htmlspecialchars($t['status']); ?></span></td>
                                <td><span class="cell-date"><?= date('d M Y H:i', strtotime($t['tanggal_pesan'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-row"><i class="fas fa-inbox"></i>Belum ada pesanan tiket</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="section-title">
                <i class="fas fa-utensils"></i>
                Pesanan Menu
                <span class="title-sub"><?= $laporanMenu->num_rows; ?> transaksi</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pemesan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($laporanMenu && $laporanMenu->num_rows > 0): ?>
                            <?php while ($m = $laporanMenu->fetch_assoc()): 
                                $st = strtolower($m['status'] ?? 'baru');
                                $stClass = 'baru';
                                if ($st === 'lunas') $stClass = 'lunas';
                                elseif ($st === 'batal') $stClass = 'batal';
                                elseif ($st === 'pending') $stClass = 'pending';
                                elseif ($st === 'baru') $stClass = 'baru';

                                // Prioritas: nama_pemesan dulu, baru user_nama
                                $namaPemesan = !empty($m['nama_pemesan']) ? $m['nama_pemesan'] : (!empty($m['user_nama']) ? $m['user_nama'] : 'Guest');
                            ?>
                            <tr>
                                <td><span class="cell-code"><?= htmlspecialchars($m['kode_pesanan'] ?? '#' . $m['id_pesanan_menu']); ?></span></td>
                                <td><span class="cell-name"><?= htmlspecialchars($namaPemesan); ?></span></td>
                                <td><span class="cell-amount">Rp <?= number_format($m['total_harga'], 0, ',', '.'); ?></span></td>
                                <td><span class="badge-status <?= $stClass; ?>"><?= htmlspecialchars($m['status'] ?? 'Baru'); ?></span></td>
                                <td><span class="cell-date"><?= date('d M Y H:i', strtotime($m['created_at'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-row"><i class="fas fa-inbox"></i>Belum ada pesanan menu</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal fade" id="modalPemasukan" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <div class="modal-icon green"><i class="fas fa-arrow-down"></i></div>
                        <div>
                            <div class="modal-title-custom">Tambah Pemasukan</div>
                            <div class="modal-sub">Catat pemasukan yang tidak terduga</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Deskripsi</label>
                            <input type="text" name="deskripsi" class="form-input" placeholder="Contoh: Sumbangan, denda, dll" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-input" placeholder="50000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Tanggal</label>
                            <input type="date" name="tanggal" class="form-input" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_pemasukan" class="btn-modal green">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPengeluaran" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <div class="modal-icon red"><i class="fas fa-arrow-up"></i></div>
                        <div>
                            <div class="modal-title-custom">Tambah Pengeluaran</div>
                            <div class="modal-sub">Catat pengeluaran yang tidak terduga</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Deskripsi</label>
                            <input type="text" name="deskripsi" class="form-input" placeholder="Contoh: Perbaikan AC, kebersihan, dll" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-input" placeholder="20000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Tanggal</label>
                            <input type="date" name="tanggal" class="form-input" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_pengeluaran" class="btn-modal red">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function bukaModal(id) {
            new bootstrap.Modal(document.getElementById(id)).show();
        }

        const ctx = document.getElementById('chartKas').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: <?= json_encode($chartMasuk); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.7,
                        categoryPercentage: 0.6
                    },
                    {
                        label: 'Pengeluaran',
                        data: <?= json_encode($chartKeluar); ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.7,
                        categoryPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f1f3a',
                        borderColor: '#1f2937',
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 11, weight: '600' } }
                    },
                    y: {
                        grid: { color: 'rgba(31,41,55,0.5)', drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11, weight: '600' },
                            callback: function(v) {
                                if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                                if (v >= 1000) return 'Rp ' + (v/1000).toFixed(0) + 'rb';
                                return 'Rp ' + v;
                            }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>