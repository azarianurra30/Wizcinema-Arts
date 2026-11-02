<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

function uploadStudioImage($fileInput) {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) return null;
    $fileName = basename($_FILES[$fileInput]['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($fileExt, $allowed, true)) return null;
    $targetDir = __DIR__ . '/assets/img/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $newName = 'studio_' . uniqid() . '.' . $fileExt;
    if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetDir . $newName)) return $newName;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ FIX #6: Hapus real_escape_string
    $nama_studio = $_POST['nama_studio'];
    $jumlah_baris = (int)$_POST['jumlah_baris'];
    $jumlah_kursi_per_baris = (int)$_POST['jumlah_kursi_per_baris'];
    $gambar = null;

    if (!empty($_FILES['gambar']['name'])) $gambar = uploadStudioImage('gambar');

    if (isset($_POST['id_studio']) && is_numeric($_POST['id_studio'])) {
        $id_studio = (int)$_POST['id_studio'];
        if ($gambar) {
            $stmt = $conn->prepare("UPDATE studio SET nama_studio=?, jumlah_baris=?, jumlah_kursi_per_baris=?, gambar=? WHERE id_studio=?");
            $stmt->bind_param("siisi", $nama_studio, $jumlah_baris, $jumlah_kursi_per_baris, $gambar, $id_studio);
        } else {
            $stmt = $conn->prepare("UPDATE studio SET nama_studio=?, jumlah_baris=?, jumlah_kursi_per_baris=? WHERE id_studio=?");
            $stmt->bind_param("siii", $nama_studio, $jumlah_baris, $jumlah_kursi_per_baris, $id_studio);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        if (!$gambar) {
            $error = "Gambar studio wajib diupload!";
        } else {
            $stmt = $conn->prepare("INSERT INTO studio (nama_studio, jumlah_baris, jumlah_kursi_per_baris, gambar) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $nama_studio, $jumlah_baris, $jumlah_kursi_per_baris, $gambar);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (!isset($error)) { header('Location: admin_studio.php'); exit; }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $res = $conn->query("SELECT gambar FROM studio WHERE id_studio=$id");
    $data = $res->fetch_assoc();
    if ($data && $data['gambar']) @unlink(__DIR__ . '/assets/img/' . $data['gambar']);
    $conn->query("DELETE FROM studio WHERE id_studio=$id");
    header('Location: admin_studio.php');
    exit;
}

$editStudio = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editStudio = $conn->query("SELECT * FROM studio WHERE id_studio=$id")->fetch_assoc();
}

$studios = $conn->query("SELECT * FROM studio ORDER BY nama_studio");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Studio - Admin</title>
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

        .page-header { margin-bottom: 26px; }
        .page-header h4 {
            font-weight: 800; font-size: 1.55rem; color: #fff;
            display: flex; align-items: center; gap: 12px;
            letter-spacing: -0.3px;
        }
        .page-header h4 i {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(139,92,246,0.35);
        }

        .layout-grid {
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
        .card-el-title i { color: #A78BFA; }

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
            background: #111A2E; color: #A78BFA;
            padding: 13px 16px; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;
            border-bottom: 1px solid #1E293B; text-align: left; white-space: nowrap;
        }
        .table-wrap tbody td {
            padding: 13px 16px; border-bottom: 1px solid #1E293B;
            font-size: 0.85rem; vertical-align: middle;
        }
        .table-wrap tbody tr:last-child td { border-bottom: none; }
        .table-wrap tbody tr:hover td { background: rgba(139,92,246,0.04); }

        .studio-thumb {
            width: 70px; height: 52px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #1E293B;
        }

        .btn-el {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            border: 1.5px solid transparent;
            cursor: pointer;
            font-family: inherit;
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
        .form-input-el::placeholder { color: #475569; }
        .form-input-el:focus {
            border-color: #8B5CF6;
            box-shadow: 0 0 0 4px rgba(139,92,246,0.15);
        }

        .btn-submit-el {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
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
            box-shadow: 0 8px 20px rgba(139,92,246,0.3);
            margin-top: 4px;
        }
        .btn-submit-el:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(139,92,246,0.45);
        }
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
        .btn-cancel-el:hover { background: #1E293B; color: #fff; border-color: #2A3854; }

        .alert-error-el {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #FCA5A5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .empty-row { text-align: center; padding: 45px; color: #64748B; }

        @media (max-width: 992px) { .layout-grid { grid-template-columns: 1fr; } }
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
            <h4><i class="fas fa-video"></i> Kelola Studio</h4>
        </div>

        <div class="layout-grid">
            <div class="card-el">
                <div class="card-el-title">
                    <i class="fas fa-list"></i> Daftar Studio
                    <span style="margin-left:auto; font-size:0.75rem; color:#64748B; font-weight:600;"><?= $studios->num_rows; ?> studio</span>
                </div>
                <div class="table-wrap">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama Studio</th>
                                    <th>Baris</th>
                                    <th>Kursi/Baris</th>
                                    <th>Total Kursi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($studios->num_rows > 0): ?>
                                    <?php while($s = $studios->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if(!empty($s['gambar'])): ?>
                                                <img src="assets/img/<?= htmlspecialchars($s['gambar']); ?>" class="studio-thumb" alt="Studio">
                                            <?php else: ?>
                                                <span style="color:#475569;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong style="color:#fff;"><?= htmlspecialchars($s['nama_studio']); ?></strong></td>
                                        <td><?= $s['jumlah_baris']; ?></td>
                                        <td><?= $s['jumlah_kursi_per_baris']; ?></td>
                                        <td>
                                            <span style="color:#A78BFA; font-weight:700;">
                                                <?= (int)$s['jumlah_baris'] * (int)$s['jumlah_kursi_per_baris']; ?> kursi
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <a href="admin_studio.php?edit=<?= $s['id_studio']; ?>" class="btn-el btn-edit-el"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="admin_studio.php?delete=<?= $s['id_studio']; ?>" class="btn-el btn-danger-el" onclick="return confirm('Hapus studio ini?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="empty-row">Belum ada studio.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card-el">
                <div class="card-el-title">
                    <i class="fas <?= $editStudio ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?= $editStudio ? 'Edit Studio' : 'Tambah Studio' ?>
                </div>
                <?php if(isset($error)): ?><div class="alert-error-el"><?= $error; ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?php if($editStudio): ?><input type="hidden" name="id_studio" value="<?= $editStudio['id_studio']; ?>"><?php endif; ?>
                    
                    <label class="form-label-el">Nama Studio</label>
                    <input name="nama_studio" type="text" class="form-input-el" value="<?= $editStudio['nama_studio'] ?? ''; ?>" required placeholder="Contoh: Studio 1">
                    
                    <label class="form-label-el">Jumlah Baris</label>
                    <input name="jumlah_baris" type="number" class="form-input-el" value="<?= $editStudio['jumlah_baris'] ?? 4; ?>" required min="1" max="26">
                    
                    <label class="form-label-el">Jumlah Kursi per Baris</label>
                    <input name="jumlah_kursi_per_baris" type="number" class="form-input-el" value="<?= $editStudio['jumlah_kursi_per_baris'] ?? 6; ?>" required min="1" max="50">
                    
                    <?php if($editStudio && !empty($editStudio['gambar'])): ?>
                        <label class="form-label-el">Gambar Saat Ini</label>
                        <div style="margin-bottom:14px;">
                            <img src="assets/img/<?= $editStudio['gambar']; ?>" style="width:120px; border-radius:10px; border:1px solid #1E293B;">
                        </div>
                    <?php endif; ?>
                    
                    <label class="form-label-el">Gambar Interior Studio</label>
                    <input name="gambar" type="file" class="form-input-el" accept="image/*" <?= $editStudio ? '' : 'required'; ?>>
                    
                    <button type="submit" class="btn-submit-el">
                        <i class="fas fa-save"></i> <?= $editStudio ? 'Update Studio' : 'Simpan Studio' ?>
                    </button>
                    <?php if($editStudio): ?><a href="admin_studio.php" class="btn-cancel-el">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</body>
</html>