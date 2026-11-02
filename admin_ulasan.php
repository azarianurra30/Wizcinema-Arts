<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kasir')) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM ulasan WHERE id_ulasan=$id");
    header('Location: admin_ulasan.php');
    exit;
}

$ulasan = $conn->query("SELECT u.*, f.judul AS film_judul, us.nama AS user_nama FROM ulasan u JOIN film f ON u.id_film = f.id_film JOIN users us ON u.id_user = us.id_user ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Ulasan</title>
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

        .page-header { margin-bottom: 26px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .page-header h4 {
            font-weight: 800; font-size: 1.55rem; color: #fff;
            display: flex; align-items: center; gap: 12px;
            letter-spacing: -0.3px;
        }
        .page-header h4 i {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(251,191,36,0.35);
        }
        .page-header .count-badge {
            background: #111A2E;
            border: 1px solid #1E293B;
            color: #94A3B8;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .card-el {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .table-wrap {
            background: #0F1729;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #1E293B;
        }
        .table-wrap table {
            margin: 0; color: #CBD5E1; width: 100%; border-collapse: collapse;
        }
        .table-wrap thead th {
            background: #111A2E; color: #FCD34D;
            padding: 13px 16px; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;
            border-bottom: 1px solid #1E293B; text-align: left; white-space: nowrap;
        }
        .table-wrap tbody td {
            padding: 14px 16px; border-bottom: 1px solid #1E293B;
            font-size: 0.85rem; vertical-align: middle;
        }
        .table-wrap tbody tr:last-child td { border-bottom: none; }
        .table-wrap tbody tr:hover td { background: rgba(251,191,36,0.04); }

        .film-name {
            color: #fff; font-weight: 700; font-size: 0.88rem;
        }
        .user-name {
            display: inline-flex; align-items: center; gap: 8px;
        }
        .user-avatar-mini {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 0.75rem; font-weight: 700;
            flex-shrink: 0;
        }
        .stars {
            color: #FBBF24;
            font-size: 0.95rem;
            letter-spacing: 2px;
            white-space: nowrap;
        }
        .komentar-text {
            color: #94A3B8;
            max-width: 320px;
            line-height: 1.5;
            font-size: 0.83rem;
        }
        .tanggal-text {
            color: #64748B;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        .btn-danger-el {
            background: rgba(239,68,68,0.1);
            color: #FCA5A5;
            border: 1.5px solid rgba(239,68,68,0.3);
            border-radius: 999px;
            padding: 5px 14px;
            font-weight: 600;
            font-size: 0.76rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
            cursor: pointer;
        }
        .btn-danger-el:hover { background: #EF4444; color: #fff; }

        .empty-row { text-align: center; padding: 55px 20px; color: #64748B; }
        .empty-row i { font-size: 2.5rem; opacity: 0.25; display: block; margin-bottom: 12px; }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h4><i class="fas fa-star"></i> Kelola Ulasan</h4>
            <span class="count-badge"><?= $ulasan->num_rows; ?> ulasan</span>
        </div>

        <div class="card-el">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Film</th>
                                <th>Pengguna</th>
                                <th>Rating</th>
                                <th>Komentar</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ulasan->num_rows > 0): ?>
                                <?php while($u = $ulasan->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="film-name"><?= htmlspecialchars($u['film_judul']); ?></span></td>
                                    <td>
                                        <span class="user-name">
                                            <span class="user-avatar-mini"><?= strtoupper(substr($u['user_nama'], 0, 1)); ?></span>
                                            <?= htmlspecialchars($u['user_nama']); ?>
                                        </span>
                                    </td>
                                    <td><span class="stars"><?= str_repeat('★', $u['rating']); ?><?= str_repeat('☆', 5 - $u['rating']); ?></span></td>
                                    <td><div class="komentar-text"><?= htmlspecialchars($u['komentar']); ?></div></td>
                                    <td><span class="tanggal-text"><?= date('d M Y', strtotime($u['created_at'])); ?></span></td>
                                    <td>
                                        <a href="admin_ulasan.php?delete=<?= $u['id_ulasan']; ?>" class="btn-danger-el" onclick="return confirm('Hapus ulasan ini?')"><i class="fas fa-trash"></i> Hapus</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">
                                    <i class="fas fa-comment-slash"></i>
                                    Belum ada ulasan.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>