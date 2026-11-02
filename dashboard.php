<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$userId = (int)$_SESSION['id_user'];
$namaUser = $_SESSION['nama'] ?? 'User';

// Auto-create tabel notifikasi
$conn->query("CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    judul VARCHAR(100) DEFAULT 'Notifikasi',
    pesan TEXT NOT NULL,
    tipe VARCHAR(20) DEFAULT 'info',
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (id_user, is_read)
)");

$notifikasiList = null;
if ($role === 'user') {
    $notifikasiList = $conn->query("SELECT * FROM notifikasi WHERE id_user = $userId AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
}

// ===== AMBIL DATA SESUAI ROLE =====
if ($role === 'user') {
    $orders = $conn->query("
        SELECT 
            p.*, f.id_film, f.judul, f.poster, j.studio, j.tanggal, j.jam,
            (SELECT GROUP_CONCAT(dp.kode_kursi SEPARATOR ', ') FROM detail_pemesanan dp WHERE dp.id_pemesanan = p.id_pemesanan) AS kursi
        FROM pemesanan p 
        JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
        JOIN film f ON j.id_film = f.id_film 
        WHERE p.id_user = $userId 
        ORDER BY p.tanggal_pesan DESC
    ");
    $userTotalOrder = $conn->query("SELECT COUNT(*) AS c FROM pemesanan WHERE id_user = $userId")->fetch_assoc()['c'];
    $userVoucher    = $conn->query("SELECT COUNT(*) AS c FROM voucher_gratis WHERE id_user = $userId AND status='active'")->fetch_assoc()['c'];

} elseif ($role === 'admin') {
    $countFilm   = $conn->query("SELECT COUNT(*) AS c FROM film")->fetch_assoc()['c'];
    $countOrder  = $conn->query("SELECT COUNT(*) AS c FROM pemesanan")->fetch_assoc()['c'];
    $countUlasan = $conn->query("SELECT COUNT(*) AS c FROM ulasan")->fetch_assoc()['c'];
    $countUser   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];

} elseif ($role === 'kasir') {
    $kasirTodayOrder = $conn->query("SELECT COUNT(*) AS c FROM pemesanan WHERE DATE(tanggal_pesan) = CURDATE()")->fetch_assoc()['c'];
    $pendingRefund   = $conn->query("SELECT COUNT(*) AS c FROM pemesanan WHERE status='Batal'")->fetch_assoc()['c'];
    
    $ordersTiket = $conn->query("SELECT p.*, f.judul, u.nama AS user_nama 
                                 FROM pemesanan p 
                                 JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
                                 JOIN film f ON j.id_film = f.id_film 
                                 JOIN users u ON p.id_user = u.id_user 
                                 ORDER BY p.tanggal_pesan DESC LIMIT 15");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0B1120;
            --bg-surface: #111A2E;
            --bg-surface-hover: #1A2740;
            --border-color: #1E293B;
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --blue: #3B82F6;
            --blue-grad: linear-gradient(135deg, #3B82F6, #2563EB);
            --green: #10B981;
            --green-grad: linear-gradient(135deg, #10B981, #059669);
            --yellow: #F59E0B;
            --yellow-grad: linear-gradient(135deg, #FBBF24, #D97706);
            --red: #EF4444;
            --red-grad: linear-gradient(135deg, #EF4444, #DC2626);
            --purple: #8B5CF6;
            --purple-grad: linear-gradient(135deg, #8B5CF6, #7C3AED);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .main-content { flex: 1; padding: 32px 38px; min-width: 0; }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 32px; flex-wrap: wrap; gap: 16px;
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 6px 0; letter-spacing: -0.5px; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; font-weight: 500; }
        
        .date-badge {
            background: var(--bg-surface); padding: 10px 18px; border-radius: 10px;
            border: 1px solid var(--border-color); font-size: 0.85rem;
            font-weight: 700; display: flex; align-items: center; gap: 8px;
        }
        .date-badge i { color: var(--blue); }

        .alert-custom {
            padding: 16px 20px; border-radius: 10px; margin-bottom: 24px;
            font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 12px;
            border: 1px solid transparent;
        }
        .alert-success { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.3); color: #34D399; }
        .alert-error { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #F87171; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px; margin-bottom: 32px;
        }
        .stat-card {
            background: var(--bg-surface); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 24px; position: relative; overflow: hidden;
            transition: transform 0.25s ease, border-color 0.25s;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: #334155; }
        
        .stat-card::before {
            content: ''; position: absolute; top: 0; right: 0; width: 120px; height: 120px;
            background: var(--glow); opacity: 0.1; filter: blur(40px); border-radius: 50%;
        }
        .stat-card.blue { --glow: var(--blue); }
        .stat-card.green { --glow: var(--green); }
        .stat-card.yellow { --glow: var(--yellow); }
        .stat-card.purple { --glow: var(--purple); }

        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; position: relative; z-index: 1; }
        .stat-label { font-size: 0.85rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff;
        }
        .stat-icon.blue { background: var(--blue-grad); box-shadow: 0 4px 15px rgba(59,130,246,0.3); }
        .stat-icon.green { background: var(--green-grad); box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .stat-icon.yellow { background: var(--yellow-grad); box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
        .stat-icon.purple { background: var(--purple-grad); box-shadow: 0 4px 15px rgba(139,92,246,0.3); }

        .stat-value { font-size: 2.2rem; font-weight: 800; line-height: 1; letter-spacing: -1px; position: relative; z-index: 1; }

        .section-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 20px; letter-spacing: -0.3px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: var(--blue); }
        
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 32px; }
        
        .menu-card {
            background: var(--bg-surface); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 24px; display: flex; align-items: flex-start; gap: 18px;
            text-decoration: none; transition: all 0.25s ease;
        }
        .menu-card:hover { background: var(--bg-surface-hover); border-color: #3B82F6; transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        
        .menu-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0; color: #fff;
        }
        .menu-icon.ic-film { background: var(--blue-grad); }
        .menu-icon.ic-jadwal { background: var(--purple-grad); }
        .menu-icon.ic-studio { background: var(--yellow-grad); }
        .menu-icon.ic-promo { background: var(--red-grad); }
        .menu-icon.ic-ulasan { background: var(--green-grad); }
        .menu-icon.ic-suara { background: rgba(148, 163, 184, 0.2); color: var(--text-secondary); }

        .menu-content h3 { font-size: 1rem; font-weight: 800; margin: 0 0 6px 0; color: var(--text-primary); }
        .menu-content p { font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.5; font-weight: 500; }

        .table-container { 
            background: var(--bg-surface); border: 1px solid var(--border-color); 
            border-radius: 16px; overflow: hidden; margin-bottom: 24px; 
        }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        th {
            background: rgba(11, 17, 32, 0.5); color: var(--text-secondary); font-size: 0.75rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 16px 24px; border-bottom: 1px solid var(--border-color); white-space: nowrap;
        }
        td {
            padding: 16px 24px; border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem; vertical-align: middle; font-weight: 500;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(59,130,246,0.03); }

        .code-text { font-family: 'Courier New', monospace; color: var(--blue); font-weight: 700; font-size: 0.85rem; background: rgba(59,130,246,0.1); padding: 4px 8px; border-radius: 6px; }
        .movie-cell { display: flex; align-items: center; gap: 12px; }
        .movie-thumb { width: 36px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); }
        .movie-title { font-weight: 700; color: var(--text-primary); }
        .schedule-date { font-weight: 700; display: block; color: var(--text-primary); }
        .schedule-time { font-size: 0.75rem; color: var(--text-secondary); display: block; margin-top: 2px; }

        .badge { 
            padding: 6px 14px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; 
            display: inline-flex; align-items: center; gap: 6px; color: #fff;
        }
        .badge-success { background: var(--green-grad); }
        .badge-warning { background: var(--yellow-grad); color: #1F2937; }
        .badge-danger { background: var(--red-grad); }
        .badge-info { background: var(--blue-grad); }
        .badge-neutral { background: #334155; }

        .btn-action { 
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; 
            border-radius: 8px; font-size: 0.8rem; font-weight: 700; text-decoration: none; 
            transition: all 0.2s; border: 1px solid var(--border-color); 
            background: var(--bg-body); color: var(--text-primary); cursor: pointer;
        }
        .btn-action:hover { background: var(--border-color); transform: translateY(-1px); }
        .btn-action.btn-primary { background: var(--blue-grad); border-color: var(--blue); color: #fff; }
        .btn-action.btn-primary:hover { box-shadow: 0 4px 12px rgba(59,130,246,0.4); }
        .btn-disabled { opacity: 0.5; cursor: not-allowed; background: transparent; }

        .empty-state { text-align: center; padding: 48px 20px; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; margin-bottom: 12px; display: block; opacity: 0.5; }
        .empty-state p { font-size: 0.9rem; font-weight: 500; margin: 0; }

        .notif-container { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 32px; overflow: hidden; }
        .notif-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
        .notif-header i { color: var(--yellow); }
        .notif-item { padding: 16px 24px; display: flex; gap: 16px; border-bottom: 1px solid var(--border-color); }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(245,158,11,0.15); color: var(--yellow); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
        .notif-content h4 { font-size: 0.9rem; font-weight: 800; margin: 0 0 4px 0; }
        .notif-content p { font-size: 0.85rem; color: var(--text-secondary); margin: 0 0 8px 0; line-height: 1.5; }
        .notif-content small { font-size: 0.75rem; color: #64748B; font-weight: 600; display: flex; align-items: center; gap: 4px; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 24px; }
            .stats-grid { grid-template-columns: 1fr; }
            .menu-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <!-- ===== ALERTS ===== -->
        <?php if(isset($_SESSION['message'])): ?>
            <div class="alert-custom alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']); ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert-custom alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['notif_kasir'])): ?>
            <div class="alert-custom alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['notif_kasir']); ?></div>
            <?php unset($_SESSION['notif_kasir']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_kasir'])): ?>
            <div class="alert-custom alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error_kasir']); ?></div>
            <?php unset($_SESSION['error_kasir']); ?>
        <?php endif; ?>

        <!-- =========================================
             USER DASHBOARD
             ========================================= -->
        <?php if($role === 'user'): ?>
            
            <div class="page-header">
                <div>
                    <h1>Dashboard Pengguna</h1>
                    <p>Selamat datang kembali, <?= htmlspecialchars($namaUser); ?>. Siap untuk menonton?</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('d F Y'); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-label">Total Pemesanan</div>
                        <div class="stat-icon blue"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <div class="stat-value"><?= $userTotalOrder; ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <div class="stat-label">Voucher Aktif</div>
                        <div class="stat-icon yellow"><i class="fas fa-tag"></i></div>
                    </div>
                    <div class="stat-value"><?= $userVoucher; ?></div>
                </div>
            </div>

            <?php if($notifikasiList && $notifikasiList->num_rows > 0): ?>
                <div class="notif-container">
                    <div class="notif-header"><i class="fas fa-bell"></i> Notifikasi Terbaru</div>
                    <?php while($n = $notifikasiList->fetch_assoc()): 
                        $isRefund = ($n['tipe'] === 'refund');
                    ?>
                        <div class="notif-item" style="<?= $isRefund ? 'background:rgba(16,185,129,0.06); border-left:3px solid #10B981;' : ''; ?>">
                            <div class="notif-icon" style="<?= $isRefund ? 'background:rgba(16,185,129,0.2); color:#34D399;' : ''; ?>">
                                <i class="fas <?= $isRefund ? 'fa-hand-holding-usd' : 'fa-info-circle'; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <h4 style="<?= $isRefund ? 'color:#34D399;' : ''; ?>"><?= htmlspecialchars($n['judul'] ?? 'Notifikasi'); ?></h4>
                                <p><?= htmlspecialchars($n['pesan']); ?></p>
                                <small><i class="far fa-clock"></i> <?= date('d M Y, H:i', strtotime($n['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php $conn->query("UPDATE notifikasi SET is_read = 1 WHERE id_user = $userId AND is_read = 0"); ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title"><i class="fas fa-history"></i> Riwayat Pemesanan</h2>
            <div class="table-container">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Film</th>
                                <th>Jadwal</th>
                                <th>Kursi</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($orders && $orders->num_rows > 0): ?>
                                <?php while($o = $orders->fetch_assoc()): 
                                    $jadwal_dt    = strtotime($o['tanggal'] . ' ' . $o['jam']);
                                    $now          = time();
                                    $selisih      = $now - $jadwal_dt;
                                    $grace_detik  = 24 * 60 * 60;
                                    $belumTayang      = ($selisih < 0);
                                    $sedangTayang     = ($selisih >= 0 && $selisih <= $grace_detik);
                                    $sudahLewatSehari = ($selisih > $grace_detik);
                                    $bolehCetak  = $belumTayang || $sedangTayang;
                                    $bolehRefund = $belumTayang;

                                    if ($belumTayang) { $jadwalLabel = 'Akan tayang'; } 
                                    elseif ($sedangTayang) { $jadwalLabel = 'Selesai tayang'; } 
                                    else { $jadwalLabel = 'Telah lewat'; }
                                ?>
                                <tr>
                                    <td><span class="code-text"><?= htmlspecialchars($o['kode_booking'] ?? '-'); ?></span></td>
                                    <td>
                                        <div class="movie-cell">
                                            <img src="assets/img/<?= htmlspecialchars($o['poster'] ?? 'default.jpg'); ?>" class="movie-thumb" onerror="this.src='assets/img/default.jpg'">
                                            <span class="movie-title"><?= htmlspecialchars($o['judul'] ?? '-'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="schedule-date"><?= date('d M Y', strtotime($o['tanggal'])); ?></span>
                                        <span class="schedule-time"><?= date('H:i', strtotime($o['jam'])); ?> WIB &middot; <?= $jadwalLabel; ?></span>
                                    </td>
                                    <td><span class="movie-title"><?= htmlspecialchars($o['kursi'] ?? '-'); ?></span></td>
                                    <td><span class="movie-title">Rp <?= number_format($o['total_harga'] ?? 0, 0, ',', '.'); ?></span></td>
                                    <td>
                                        <?php
                                        $status = $o['status'] ?? 'Unknown';
                                        $badgeClass = 'badge-neutral';
                                        if ($status === 'Lunas') $badgeClass = 'badge-success';
                                        elseif ($status === 'Pending') $badgeClass = 'badge-warning';
                                        elseif ($status === 'Batal') $badgeClass = 'badge-danger';
                                        elseif ($status === 'Refunded') $badgeClass = 'badge-info';
                                        ?>
                                        <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($status); ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <?php if($status === 'Lunas' || $status === 'Pending'): ?>
                                                <?php if($bolehCetak): ?>
                                                    <a href="struk_film.php?id=<?= $o['id_pemesanan']; ?>" class="btn-action"><i class="fas fa-qrcode"></i> Tiket</a>
                                                <?php else: ?>
                                                    <span class="btn-action btn-disabled"><i class="fas fa-lock"></i> Tiket</span>
                                                <?php endif; ?>

                                                <?php if($bolehRefund): ?>
                                                    <a href="refund.php?id=<?= $o['id_pemesanan']; ?>" class="btn-action"><i class="fas fa-undo"></i> Refund</a>
                                                <?php else: ?>
                                                    <span class="btn-action btn-disabled"><i class="fas fa-lock"></i> Refund</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if($status === 'Lunas'): ?>
                                                <a href="detail_film.php?id=<?= $o['id_film']; ?>" class="btn-action btn-primary"><i class="fas fa-star"></i> Ulasan</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-ticket-alt"></i>
                                            <p>Belum ada riwayat pemesanan.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- =========================================
             ADMIN DASHBOARD
             ========================================= -->
        <?php elseif($role === 'admin'): ?>
            
            <div class="page-header">
                <div>
                    <h1>Dashboard Admin</h1>
                    <p>Ringkasan operasional sistem WizCinema.</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('d F Y'); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-label">Total Film</div>
                        <div class="stat-icon blue"><i class="fas fa-film"></i></div>
                    </div>
                    <div class="stat-value"><?= $countFilm; ?></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-label">Total Pemesanan</div>
                        <div class="stat-icon green"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <div class="stat-value"><?= $countOrder; ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <div class="stat-label">Ulasan Masuk</div>
                        <div class="stat-icon yellow"><i class="fas fa-star"></i></div>
                    </div>
                    <div class="stat-value"><?= $countUlasan; ?></div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Total Pengguna</div>
                        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-value"><?= $countUser; ?></div>
                </div>
            </div>

            <h2 class="section-title"><i class="fas fa-th-large"></i> Menu Pengelolaan</h2>
            <div class="menu-grid">
                <a href="admin_films.php" class="menu-card">
                    <div class="menu-icon ic-film"><i class="fas fa-film"></i></div>
                    <div class="menu-content">
                        <h3>Kelola Film</h3>
                        <p>Atur daftar film yang sedang tayang dan akan datang.</p>
                    </div>
                </a>
                <a href="admin_jadwal.php" class="menu-card">
                    <div class="menu-icon ic-jadwal"><i class="fas fa-calendar-alt"></i></div>
                    <div class="menu-content">
                        <h3>Jadwal Tayang</h3>
                        <p>Konfigurasi jam tayang, studio, dan harga tiket.</p>
                    </div>
                </a>
                <a href="admin_studio.php" class="menu-card">
                    <div class="menu-icon ic-studio"><i class="fas fa-video"></i></div>
                    <div class="menu-content">
                        <h3>Kelola Studio</h3>
                        <p>Atur denah kursi dan kapasitas ruangan bioskop.</p>
                    </div>
                </a>
                <a href="admin_promo.php" class="menu-card">
                    <div class="menu-icon ic-promo"><i class="fas fa-tags"></i></div>
                    <div class="menu-content">
                        <h3>Promo & Voucher</h3>
                        <p>Buat strategi diskon dan pembagian tiket gratis.</p>
                    </div>
                </a>
                <a href="admin_ulasan.php" class="menu-card">
                    <div class="menu-icon ic-ulasan"><i class="fas fa-comments"></i></div>
                    <div class="menu-content">
                        <h3>Moderasi Ulasan</h3>
                        <p>Pantau dan kelola feedback dari pengguna.</p>
                    </div>
                </a>
                <a href="admin_suara.php" class="menu-card">
                    <div class="menu-icon ic-suara"><i class="fas fa-volume-up"></i></div>
                    <div class="menu-content">
                        <h3>Sistem Suara</h3>
                        <p>Pengaturan tingkat volume audio aplikasi.</p>
                    </div>
                </a>
            </div>

        <!-- =========================================
             KASIR DASHBOARD
             ========================================= -->
        <?php elseif($role === 'kasir'): ?>
            
            <div class="page-header">
                <div>
                    <h1>Dashboard Kasir</h1>
                    <p>Pantau pesanan tiket dan konfirmasi refund pengguna.</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('d F Y'); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-label">Pesanan Hari Ini</div>
                        <div class="stat-icon green"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <div class="stat-value"><?= $kasirTodayOrder; ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <div class="stat-label">Refund Menunggu</div>
                        <div class="stat-icon yellow"><i class="fas fa-undo"></i></div>
                    </div>
                    <div class="stat-value"><?= $pendingRefund; ?></div>
                </div>
            </div>

            <!-- ====== PANEL NOTIFIKASI KASIR ====== -->
            <?php 
            $kasirNotifs = $conn->query("
                SELECT * FROM notifikasi 
                WHERE id_user = $userId 
                  AND is_read = 0
                  AND tipe IN ('refund_request','refund')
                ORDER BY created_at DESC
            ");
            ?>
            <?php if ($kasirNotifs && $kasirNotifs->num_rows > 0): ?>
                <div style="background:#111A2E; border:1px solid #1E293B; border-left:4px solid #EF4444; border-radius:16px; padding:20px 24px; margin-bottom:24px;">
                    <div style="display:flex; align-items:center; gap:10px; font-weight:800; color:#FCA5A5; font-size:0.95rem; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid #1E293B;">
                        <i class="fas fa-bell"></i> Notifikasi Baru
                        <span style="margin-left:auto; background:rgba(239,68,68,0.15); color:#F87171; padding:3px 12px; border-radius:999px; font-size:0.7rem;"><?= $kasirNotifs->num_rows; ?> notifikasi</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php while ($n = $kasirNotifs->fetch_assoc()): ?>
                            <div style="background:#0B1120; border:1px solid #1E293B; border-radius:12px; padding:14px 18px;">
                                <div style="color:#fff; font-weight:800; font-size:0.88rem; margin-bottom:4px;">
                                    <i class="fas fa-hand-holding-usd" style="color:#F87171; font-size:0.75rem;"></i> <?= htmlspecialchars($n['judul']); ?>
                                </div>
                                <div style="color:#94A3B8; font-size:0.82rem; line-height:1.5; margin-bottom:6px;"><?= htmlspecialchars($n['pesan']); ?></div>
                                <div style="color:#64748B; font-size:0.72rem;"><i class="far fa-clock"></i> <?= date('d M Y, H:i', strtotime($n['created_at'])); ?></div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php $conn->query("UPDATE notifikasi SET is_read = 1 WHERE id_user = $userId AND tipe IN ('refund_request','refund') AND is_read = 0"); ?>
            <?php endif; ?>

            <!-- ====== PANEL REFUND MENUNGGU PERSETUJUAN ====== -->
            <?php 
            $refundRequests = $conn->query("
                SELECT p.*, u.nama AS user_nama, f.judul, j.tanggal, j.jam
                FROM pemesanan p
                JOIN users u ON p.id_user = u.id_user
                JOIN jadwal j ON p.id_jadwal = j.id_jadwal
                JOIN film f ON j.id_film = f.id_film
                WHERE p.status = 'Batal'
                ORDER BY p.tanggal_pesan DESC
            ");
            ?>
            <?php if ($refundRequests && $refundRequests->num_rows > 0): ?>
                <div style="background:#111A2E; border:1px solid #1E293B; border-left:4px solid #EF4444; border-radius:16px; padding:20px 24px; margin-bottom:28px;">
                    <div style="display:flex; align-items:center; gap:10px; font-weight:800; color:#FCA5A5; font-size:0.95rem; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid #1E293B;">
                        <i class="fas fa-undo"></i> Refund Menunggu Persetujuan
                        <span style="margin-left:auto; background:rgba(239,68,68,0.15); color:#F87171; padding:3px 12px; border-radius:999px; font-size:0.7rem;"><?= $refundRequests->num_rows; ?> permintaan</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php while ($r = $refundRequests->fetch_assoc()): 
                            $metodeAsli = strtoupper($r['metode'] ?? 'Cash');
                            $labelMetode = $metodeAsli;
                            if (in_array(strtolower($metodeAsli), ['dana','gopay','shopeepay'])) $labelMetode = "Akun " . $metodeAsli;
                            elseif (in_array(strtolower($metodeAsli), ['bca','mandiri','bri','bsi'])) $labelMetode = "Rek. " . $metodeAsli;
                            else $labelMetode = "Tunai";
                        ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:15px; background:#0B1120; border:1px solid #1E293B; border-radius:12px; padding:14px 18px; flex-wrap:wrap;">
                                <div style="flex:1; min-width:220px;">
                                    <div style="color:#fff; font-weight:800; font-size:0.9rem; margin-bottom:4px;">
                                        <i class="fas fa-user" style="color:#F87171; font-size:0.75rem;"></i> <?= htmlspecialchars($r['user_nama']); ?> — <span style="color:#FCD34D;">Rp <?= number_format($r['total_harga'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div style="color:#94A3B8; font-size:0.78rem; margin-bottom:4px;">
                                        <i class="fas fa-film" style="color:#60A5FA; font-size:0.7rem;"></i> <?= htmlspecialchars($r['judul']); ?>
                                        · <?= date('d M Y', strtotime($r['tanggal'])); ?> <?= date('H:i', strtotime($r['jam'])); ?>
                                    </div>
                                    <div style="color:#34D399; font-size:0.75rem; font-weight:600;">
                                        <i class="fas fa-credit-card" style="font-size:0.7rem;"></i> Dana kembali ke: <?= htmlspecialchars($labelMetode); ?>
                                    </div>
                                </div>
                                <a href="konfirmasi_refund.php?id=<?= $r['id_pemesanan']; ?>"
                                   onclick="return confirm('Setujui refund Rp <?= number_format($r['total_harga'], 0, ',', '.'); ?> untuk <?= htmlspecialchars($r['user_nama']); ?>?');"
                                   style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:linear-gradient(135deg,#10B981,#059669); color:#fff; border:none; border-radius:10px; font-weight:800; font-size:0.82rem; text-decoration:none; transition:0.2s; box-shadow:0 6px 16px rgba(16,185,129,0.35);">
                                    <i class="fas fa-check-circle"></i> Setujui Refund
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="section-title"><i class="fas fa-bolt"></i> Akses Cepat</h2>
            <div class="menu-grid" style="margin-bottom: 32px;">
                <a href="kasir_laporan.php" class="menu-card">
                    <div class="menu-icon ic-film"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="menu-content">
                        <h3>Laporan Keuangan</h3>
                        <p>Lihat rekapitulasi pendapatan tiket dan menu.</p>
                    </div>
                </a>
                <a href="kasir_menu.php" class="menu-card">
                    <div class="menu-icon ic-studio"><i class="fas fa-utensils"></i></div>
                    <div class="menu-content">
                        <h3>Kelola Menu Resto</h3>
                        <p>Perbarui stok dan harga makanan/minuman.</p>
                    </div>
                </a>
            </div>

            <h2 class="section-title"><i class="fas fa-list"></i> Pesanan Terbaru</h2>
            <div class="table-container">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Film</th>
                                <th>Pemesan</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ordersTiket && $ordersTiket->num_rows > 0): ?>
                                <?php while($t = $ordersTiket->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="code-text"><?= htmlspecialchars($t['kode_booking'] ?? '-'); ?></span></td>
                                    <td><span class="movie-title"><?= htmlspecialchars($t['judul'] ?? '-'); ?></span></td>
                                    <td><?= htmlspecialchars($t['user_nama'] ?? '-'); ?></td>
                                    <td><span class="movie-title">Rp <?= number_format($t['total_harga'] ?? 0, 0, ',', '.'); ?></span></td>
                                    <td>
                                        <?php
                                        $statusT = $t['status'] ?? 'Unknown';
                                        $badgeT = 'badge-neutral';
                                        if ($statusT === 'Lunas') $badgeT = 'badge-success';
                                        elseif ($statusT === 'Pending') $badgeT = 'badge-warning';
                                        elseif ($statusT === 'Batal') $badgeT = 'badge-danger';
                                        elseif ($statusT === 'Refunded') $badgeT = 'badge-info';
                                        ?>
                                        <span class="badge <?= $badgeT; ?>"><?= htmlspecialchars($statusT); ?></span>
                                    </td>
                                    <td>
                                        <?php if($statusT === 'Batal'): ?>
                                            <a href="konfirmasi_refund.php?id=<?= $t['id_pemesanan']; ?>" class="btn-action btn-primary" onclick="return confirm('Approve refund untuk pesanan ini?');">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                        <?php elseif($statusT === 'Refunded'): ?>
                                            <span style="color: var(--green); font-size: 0.8rem; font-weight: 800;"><i class="fas fa-check-double"></i> Selesai</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary); font-size:0.8rem; font-weight: 600;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-ticket-alt"></i>
                                            <p>Belum ada pesanan.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>