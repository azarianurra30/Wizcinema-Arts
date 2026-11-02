<?php
session_start();
include 'config.php';

// Kasir & Admin boleh akses
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] !== 'kasir' && $_SESSION['role'] !== 'admin')) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'];
$isKasir = ($role === 'kasir');
$isAdmin = ($role === 'admin');

// ===== HAPUS (HANYA ADMIN) =====
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $cekKolom = $conn->query("SHOW COLUMNS FROM detail_pesanan_menu LIKE 'id_menu'");
    if ($cekKolom && $cekKolom->num_rows > 0) {
        $conn->query("DELETE FROM detail_pesanan_menu WHERE id_menu = $id");
    }
    $menuData = $conn->query("SELECT gambar FROM menu WHERE id_menu = $id")->fetch_assoc();
    if ($menuData && !empty($menuData['gambar'])) {
        @unlink(__DIR__ . '/assets/img/' . $menuData['gambar']);
    }
    $conn->query("DELETE FROM menu WHERE id_menu = $id");
    header('Location: kasir_menu.php');
    exit;
}

// ===== TAMBAH / EDIT (Kasir + Admin) =====
if (isset($_POST['save_menu'])) {
    $nama = $conn->real_escape_string($_POST['nama_menu']);
    $harga = (int)$_POST['harga'];
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $status = isset($_POST['status']) ? 1 : 0;

    $gambar = null;
    if (!empty($_FILES['gambar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $gambar = 'menu_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['gambar']['tmp_name'], 'assets/img/' . $gambar);
        }
    }

    if (isset($_POST['id_menu'])) {
        // EDIT: kasir + admin boleh
        $id = (int)$_POST['id_menu'];
        if ($gambar) {
            $stmt = $conn->prepare("UPDATE menu SET nama_menu=?, harga=?, kategori=?, status=?, gambar=? WHERE id_menu=?");
            $stmt->bind_param("sisisi", $nama, $harga, $kategori, $status, $gambar, $id);
        } else {
            $stmt = $conn->prepare("UPDATE menu SET nama_menu=?, harga=?, kategori=?, status=? WHERE id_menu=?");
            $stmt->bind_param("sisii", $nama, $harga, $kategori, $status, $id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        // TAMBAH: kasir + admin boleh
        $stmt = $conn->prepare("INSERT INTO menu (nama_menu, harga, kategori, status, gambar) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisis", $nama, $harga, $kategori, $status, $gambar);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: kasir_menu.php');
    exit;
}

// Data
$menus = $conn->query("SELECT * FROM menu ORDER BY kategori, nama_menu");

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM menu WHERE id_menu = $id")->fetch_assoc();
}

$totalMakanan  = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE kategori='makanan'")->fetch_assoc()['c'];
$totalMinuman  = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE kategori='minuman'")->fetch_assoc()['c'];
$totalTersedia = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE status=1")->fetch_assoc()['c'];
$totalHabis    = $conn->query("SELECT COUNT(*) AS c FROM menu WHERE status=0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Kasir</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h4 {
            font-weight: 800;
            font-size: 1.6rem;
            color: #fff;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        .page-header h4 i {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10B981, #059669);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(16,185,129,0.35);
        }
        .page-header .sub {
            color: #94A3B8;
            font-size: 0.88rem;
            margin-top: 4px;
        }

        /* Stats mini */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 26px;
        }
        .stat-mini {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: 0.3s;
        }
        .stat-mini:hover { border-color: #10B981; transform: translateY(-3px); }
        .stat-mini-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: #fff;
            flex-shrink: 0;
        }
        .stat-mini-icon.green { background: linear-gradient(135deg, #10B981, #059669); }
        .stat-mini-icon.blue  { background: linear-gradient(135deg, #3B82F6, #2563EB); }
        .stat-mini-icon.gold  { background: linear-gradient(135deg, #FBBF24, #F59E0B); }
        .stat-mini-icon.red   { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .stat-mini-num {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .stat-mini-label {
            font-size: 0.72rem;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
            margin-top: 3px;
        }

        /* Layout */
        .menu-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .card-el {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-el-title {
            font-size: 1rem; font-weight: 700; color: #fff;
            padding-bottom: 14px; margin-bottom: 18px;
            border-bottom: 1px solid #1E293B;
            display: flex; align-items: center; gap: 10px;
        }
        .card-el-title i { color: #34D399; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .table-dark-custom {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: #0F1729;
        }
        .table-dark-custom thead th {
            background: #111A2E;
            color: #34D399;
            padding: 14px 16px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            border-bottom: 1px solid #1E293B;
            text-align: left;
            white-space: nowrap;
        }
        .table-dark-custom tbody td {
            background: #0F1729;
            color: #CBD5E1;
            padding: 13px 16px;
            border-bottom: 1px solid #1E293B;
            vertical-align: middle;
            font-size: 0.85rem;
        }
        .table-dark-custom tbody tr:last-child td { border-bottom: none; }
        .table-dark-custom tbody tr:hover td { background: rgba(16,185,129,0.05); }

        .menu-thumb {
            width: 55px; height: 55px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #1E293B;
        }
        .no-image {
            width: 55px; height: 55px;
            background: #1E293B;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }

        .menu-name {
            color: #fff;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .menu-kategori {
            font-size: 0.72rem;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }
        .menu-harga {
            color: #FCD34D;
            font-weight: 800;
            font-size: 0.92rem;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-tersedia { background: rgba(16,185,129,0.15); color: #34D399; }
        .badge-habis    { background: rgba(239,68,68,0.15); color: #F87171; }

        .btn-el {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            border: 1.5px solid transparent;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-edit-el {
            background: rgba(59,130,246,0.15);
            color: #60A5FA;
            border-color: rgba(59,130,246,0.3);
        }
        .btn-edit-el:hover { background: #3B82F6; color: #fff; }
        .btn-danger-el {
            background: rgba(239,68,68,0.1);
            color: #FCA5A5;
            border-color: rgba(239,68,68,0.3);
        }
        .btn-danger-el:hover { background: #EF4444; color: #fff; }

        /* Form */
        .form-label-el {
            display: block; color: #94A3B8;
            font-size: 0.8rem; font-weight: 600;
            margin-bottom: 7px;
        }
        .form-input-el {
            width: 100%; padding: 11px 14px;
            background: #0B1120;
            border: 1.5px solid #1E293B;
            border-radius: 10px;
            color: #F1F5F9;
            font-size: 0.88rem;
            font-family: inherit;
            transition: 0.25s;
            outline: none;
            margin-bottom: 14px;
        }
        .form-input-el:focus {
            border-color: #10B981;
            box-shadow: 0 0 0 4px rgba(16,185,129,0.15);
        }
        .btn-submit-el {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            width: 100%;
            cursor: pointer;
            transition: 0.25s;
            font-family: inherit;
            box-shadow: 0 8px 20px rgba(16,185,129,0.3);
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit-el:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(16,185,129,0.45); }
        .btn-cancel-el {
            display: block; text-align: center;
            padding: 11px;
            background: transparent;
            border: 1.5px solid #1E293B;
            color: #94A3B8;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            margin-top: 10px;
            transition: 0.25s;
        }
        .btn-cancel-el:hover { background: #1E293B; color: #fff; }

        .info-role {
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.25);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: #93C5FD;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty-cell {
            text-align: center;
            padding: 50px 20px;
            color: #64748B;
            background: #0F1729;
        }
        .empty-cell i { font-size: 2.2rem; opacity: 0.3; display: block; margin-bottom: 10px; }

        @media (max-width: 992px) {
            .menu-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h4><i class="fas fa-utensils"></i> Kelola Menu Resto</h4>
                <div class="sub">Tambah dan kelola daftar menu yang tersedia</div>
            </div>
        </div>

        <!-- Stats Mini -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-icon green"><i class="fas fa-utensils"></i></div>
                <div>
                    <div class="stat-mini-num"><?= $totalMakanan; ?></div>
                    <div class="stat-mini-label">Makanan</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon blue"><i class="fas fa-mug-hot"></i></div>
                <div>
                    <div class="stat-mini-num"><?= $totalMinuman; ?></div>
                    <div class="stat-mini-label">Minuman</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon gold"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-mini-num"><?= $totalTersedia; ?></div>
                    <div class="stat-mini-label">Tersedia</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon red"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-mini-num"><?= $totalHabis; ?></div>
                    <div class="stat-mini-label">Habis</div>
                </div>
            </div>
        </div>

        <div class="menu-layout">
            <!-- Daftar Menu -->
            <div class="card-el">
                <div class="card-el-title">
                    <i class="fas fa-list"></i> Daftar Menu
                    <span style="margin-left:auto; font-size:0.75rem; color:#64748B; font-weight:600;"><?= $menus->num_rows; ?> item</span>
                </div>
                <div class="table-responsive">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Menu</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($menus->num_rows > 0): ?>
                                <?php $menus->data_seek(0); while ($menu = $menus->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($menu['gambar']) && file_exists('assets/img/'.$menu['gambar'])): ?>
                                                <img src="assets/img/<?= htmlspecialchars($menu['gambar']); ?>" class="menu-thumb" alt="Menu">
                                            <?php else: ?>
                                                <div class="no-image">🍿</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="menu-name"><?= htmlspecialchars($menu['nama_menu']); ?></div>
                                            <div class="menu-kategori"><?= htmlspecialchars($menu['kategori']); ?></div>
                                        </td>
                                        <td><span class="menu-harga">Rp <?= number_format($menu['harga'], 0, ',', '.'); ?></span></td>
                                        <td>
                                            <span class="badge-status <?= $menu['status'] ? 'badge-tersedia' : 'badge-habis'; ?>">
                                                <?= $menu['status'] ? 'Tersedia' : 'Habis'; ?>
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <a href="kasir_menu.php?edit=<?= $menu['id_menu']; ?>" class="btn-el btn-edit-el"><i class="fas fa-edit"></i> Edit</a>
                                            <?php if($isAdmin): ?>
                                                <a href="kasir_menu.php?delete=<?= $menu['id_menu']; ?>" class="btn-el btn-danger-el" onclick="return confirm('Hapus menu ini?');"><i class="fas fa-trash"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-cell"><i class="fas fa-utensils"></i>Belum ada menu.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Form Tambah/Edit -->
            <div class="card-el">
                <div class="card-el-title">
                    <i class="fas <?= $edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?= $edit ? 'Edit Menu' : 'Tambah Menu Baru'; ?>
                </div>

                <div class="info-role">
                    <i class="fas <?= $isKasir ? 'fa-cash-register' : 'fa-shield-halved'; ?>"></i>
                    <span>Login sebagai <strong><?= ucfirst($role); ?></strong> — bisa tambah & edit menu.</span>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?php if($edit): ?><input type="hidden" name="id_menu" value="<?= htmlspecialchars($edit['id_menu']); ?>"><?php endif; ?>
                    
                    <label class="form-label-el">Nama Menu</label>
                    <input name="nama_menu" type="text" class="form-input-el" 
                           value="<?= $edit ? htmlspecialchars($edit['nama_menu']) : ''; ?>" 
                           required placeholder="Contoh: Popcorn Kecil">
                    
                    <label class="form-label-el">Harga</label>
                    <input name="harga" type="number" class="form-input-el" 
                           value="<?= $edit ? htmlspecialchars($edit['harga']) : ''; ?>" 
                           required placeholder="15000">
                    
                    <label class="form-label-el">Kategori</label>
                    <select name="kategori" class="form-input-el">
                        <option value="makanan" <?= $edit && $edit['kategori'] === 'makanan' ? 'selected' : ''; ?>>Makanan</option>
                        <option value="minuman" <?= $edit && $edit['kategori'] === 'minuman' ? 'selected' : ''; ?>>Minuman</option>
                    </select>
                    
                    <label class="form-label-el">Gambar Menu</label>
                    <?php if($edit && !empty($edit['gambar']) && file_exists('assets/img/'.$edit['gambar'])): ?>
                        <div style="margin-bottom:10px;">
                            <img src="assets/img/<?= htmlspecialchars($edit['gambar']); ?>" style="width:80px; height:80px; object-fit:cover; border-radius:10px; border:1px solid #1E293B;">
                        </div>
                    <?php endif; ?>
                    <input name="gambar" type="file" class="form-input-el" accept="image/*">
                    
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px; color:#CBD5E1; font-size:0.85rem;">
                        <input type="checkbox" name="status" id="statusAdd" <?= !$edit || $edit['status'] ? 'checked' : ''; ?> 
                               style="width:16px; height:16px; accent-color:#10B981; cursor:pointer;">
                        <label for="statusAdd" style="cursor:pointer;">Tersedia</label>
                    </div>
                    
                    <button type="submit" name="save_menu" class="btn-submit-el">
                        <i class="fas fa-save"></i> <?= $edit ? 'Simpan Perubahan' : 'Tambah Menu'; ?>
                    </button>
                    <?php if($edit): ?><a href="kasir_menu.php" class="btn-cancel-el">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>