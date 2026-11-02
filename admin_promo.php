<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ===== CEK APAKAH KOLOM 'diskon' MASIH ADA =====
$hasDiskonCol = false;
$cekDiskonCol = $conn->query("SHOW COLUMNS FROM promo LIKE 'diskon'");
if ($cekDiskonCol && $cekDiskonCol->num_rows > 0) {
    $hasDiskonCol = true;
}

// ===== TAMBAH PROMO =====
if (isset($_POST['add_promo'])) {
    $judul          = $_POST['judul'];
    $deskripsi      = $_POST['deskripsi'];
    $kode_promo     = strtoupper(trim($_POST['kode_promo']));
    $tipe_diskon    = $_POST['tipe_diskon'];
    $nilai_diskon   = (float)$_POST['nilai_diskon'];
    $min_pembelian  = (float)($_POST['min_pembelian'] ?? 0);
    $max_diskon     = (float)($_POST['max_diskon'] ?? 0);
    $kuota          = (int)($_POST['kuota'] ?? 0);
    $tanggal_mulai  = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_akhir  = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
    $status         = $_POST['status'];

    try {
        if ($hasDiskonCol) {
            $stmt = $conn->prepare("INSERT INTO promo 
                (judul, deskripsi, kode_promo, tipe_diskon, nilai_diskon, min_pembelian, max_diskon, kuota, tanggal_mulai, tanggal_berakhir, status, created_at, diskon) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("ssssdddisssd",
                $judul, $deskripsi, $kode_promo, $tipe_diskon,
                $nilai_diskon, $min_pembelian, $max_diskon, $kuota,
                $tanggal_mulai, $tanggal_akhir, $status, $nilai_diskon
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO promo 
                (judul, deskripsi, kode_promo, tipe_diskon, nilai_diskon, min_pembelian, max_diskon, kuota, tanggal_mulai, tanggal_berakhir, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssdddisss",
                $judul, $deskripsi, $kode_promo, $tipe_diskon,
                $nilai_diskon, $min_pembelian, $max_diskon, $kuota,
                $tanggal_mulai, $tanggal_akhir, $status
            );
        }
        $stmt->execute();
        $stmt->close();
        header('Location: admin_promo.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = "Gagal menambah promo: " . $e->getMessage();
    }
}

// ===== UPDATE PROMO =====
if (isset($_POST['update_promo']) && is_numeric($_POST['id_promo'])) {
    $id_promo       = (int)$_POST['id_promo'];
    $judul          = $_POST['judul'];
    $deskripsi      = $_POST['deskripsi'];
    $kode_promo     = strtoupper(trim($_POST['kode_promo']));
    $tipe_diskon    = $_POST['tipe_diskon'];
    $nilai_diskon   = (float)$_POST['nilai_diskon'];
    $min_pembelian  = (float)($_POST['min_pembelian'] ?? 0);
    $max_diskon     = (float)($_POST['max_diskon'] ?? 0);
    $kuota          = (int)($_POST['kuota'] ?? 0);
    $tanggal_mulai  = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_akhir  = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
    $status         = $_POST['status'];

    try {
        if ($hasDiskonCol) {
            $stmt = $conn->prepare("UPDATE promo SET 
                judul=?, deskripsi=?, kode_promo=?, tipe_diskon=?, nilai_diskon=?, 
                min_pembelian=?, max_diskon=?, kuota=?, tanggal_mulai=?, tanggal_berakhir=?, status=?, diskon=? 
                WHERE id_promo=?");
            $stmt->bind_param("ssssdddisssdi",
                $judul, $deskripsi, $kode_promo, $tipe_diskon,
                $nilai_diskon, $min_pembelian, $max_diskon, $kuota,
                $tanggal_mulai, $tanggal_akhir, $status, $nilai_diskon, $id_promo
            );
        } else {
            $stmt = $conn->prepare("UPDATE promo SET 
                judul=?, deskripsi=?, kode_promo=?, tipe_diskon=?, nilai_diskon=?, 
                min_pembelian=?, max_diskon=?, kuota=?, tanggal_mulai=?, tanggal_berakhir=?, status=? 
                WHERE id_promo=?");
            $stmt->bind_param("ssssdddisssi",
                $judul, $deskripsi, $kode_promo, $tipe_diskon,
                $nilai_diskon, $min_pembelian, $max_diskon, $kuota,
                $tanggal_mulai, $tanggal_akhir, $status, $id_promo
            );
        }
        $stmt->execute();
        $stmt->close();
        header('Location: admin_promo.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = "Gagal update promo: " . $e->getMessage();
    }
}

// ===== AMBIL DATA EDIT =====
$editPromo = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editPromo = $conn->query("SELECT * FROM promo WHERE id_promo = $id")->fetch_assoc();
}

// ===== HAPUS PROMO =====
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM user_promo WHERE id_promo = $id");
    $conn->query("DELETE FROM promo WHERE id_promo = $id");
    header('Location: admin_promo.php?deleted=1');
    exit;
}

// ===== AMBIL DAFTAR PROMO =====
$promos = $conn->query("
    SELECT p.*,
        (SELECT COUNT(*) FROM user_promo WHERE id_promo = p.id_promo) AS total_klaim,
        (SELECT COUNT(*) FROM user_promo WHERE id_promo = p.id_promo AND status='used') AS total_pakai,
        (SELECT COUNT(*) FROM voucher_gratis WHERE id_promo = p.id_promo) AS total_voucher
    FROM promo p
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Promo - Admin</title>
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

        /* ===== HEADER ===== */
        .page-header { 
            margin-bottom: 26px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 15px; 
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
        .btn-add-promo {
            background: linear-gradient(135deg, #FBBF24, #F59E0B);
            color: #1F2937;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.25s;
            box-shadow: 0 6px 16px rgba(251,191,36,0.3);
        }
        .btn-add-promo:hover { transform: translateY(-2px); color: #1F2937; box-shadow: 0 10px 20px rgba(251,191,36,0.5); }

        /* ===== ALERTS ===== */
        .alert-el {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; border-radius: 10px;
            font-size: 0.85rem; margin-bottom: 22px;
        }
        .alert-success-el { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6EE7B7; }
        .alert-error-el { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; }

        /* ===== PROMO GRID (UKURAN DIPERKECIL) ===== */
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); /* Ukuran diperkecil dari 320px */
            gap: 16px; /* Jarak diperkecil */
        }
        .promo-card {
            background: linear-gradient(135deg, #4C1D95 0%, #6D28D9 50%, #7C3AED 100%);
            border-radius: 14px; /* Radius sedikit lebih kecil */
            padding: 18px 20px; /* Padding dikurangi */
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(109,40,217,0.25);
            transition: 0.3s;
        }
        .promo-card.voucher-card {
            background: linear-gradient(135deg, #065F46 0%, #059669 50%, #10B981 100%);
            box-shadow: 0 8px 20px rgba(16,185,129,0.25);
        }
        .promo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.4);
        }
        .promo-card::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 120px; height: 120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.2), transparent 70%);
            pointer-events: none;
        }

        .promo-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; position: relative; z-index: 1; }
        .promo-badge {
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 0.6rem; font-weight: 800; color: #fff; text-transform: uppercase;
        }
        .promo-status-badge {
            padding: 4px 10px; border-radius: 999px; font-size: 0.6rem; font-weight: 800;
        }
        .promo-status-badge.aktif { background: rgba(16,185,129,0.3); color: #fff; border: 1px solid rgba(16,185,129,0.5); }
        .promo-status-badge.nonaktif { background: rgba(239,68,68,0.3); color: #fff; border: 1px solid rgba(239,68,68,0.5); }

        .promo-percent {
            font-size: 1.8rem; /* Ukuran teks diskon diperkecil */
            font-weight: 800; color: #FCD34D;
            line-height: 1; margin-bottom: 8px; position: relative; z-index: 1;
            text-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }
        .promo-percent small { font-size: 0.8rem; font-weight: 700; }

        .promo-title { font-size: 0.95rem; font-weight: 800; color: #fff; margin-bottom: 4px; position: relative; z-index: 1; }
        .promo-desc { font-size: 0.75rem; color: rgba(255,255,255,0.8); line-height: 1.4; margin-bottom: 12px; position: relative; z-index: 1;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .promo-stats {
            display: flex; gap: 10px; margin-bottom: 14px; position: relative; z-index: 1;
            font-size: 0.7rem; color: rgba(255,255,255,0.9); font-weight: 600;
        }
        .promo-stats span { display: flex; align-items: center; gap: 4px; }

        .promo-code-box {
            background: #fff; color: #4C1D95; padding: 4px 10px; border-radius: 6px;
            font-family: 'Courier New', monospace; font-weight: 800; font-size: 0.75rem;
            letter-spacing: 0.5px; display: inline-block; margin-bottom: 14px; position: relative; z-index: 1;
        }
        .voucher-card .promo-code-box { color: #065F46; }

        .promo-actions {
            display: flex; gap: 8px; position: relative; z-index: 1;
        }
        .btn-edit-promo {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px;
            padding: 8px; background: rgba(255,255,255,0.15); color: #fff;
            border: 1.5px solid rgba(255,255,255,0.3); border-radius: 6px;
            font-weight: 700; font-size: 0.75rem; text-decoration: none; transition: 0.2s;
        }
        .btn-edit-promo:hover { background: #fff; color: #4C1D95; }
        .btn-delete-promo {
            padding: 8px 12px; background: rgba(239,68,68,0.2); color: #FCA5A5;
            border: 1.5px solid rgba(239,68,68,0.4); border-radius: 6px;
            font-size: 0.75rem; transition: 0.2s; text-decoration: none;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-delete-promo:hover { background: #EF4444; color: #fff; }

        /* ===== FORM VIEW (TAMBAH/EDIT) ===== */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: #94A3B8; text-decoration: none; font-weight: 600;
            font-size: 0.88rem; margin-bottom: 20px; transition: 0.2s;
        }
        .back-link:hover { color: #FBBF24; transform: translateX(-3px); }

        .form-container {
            background: #111A2E; border: 1px solid #1E293B; border-radius: 16px;
            padding: 30px; max-width: 800px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-header {
            border-bottom: 1px solid #1E293B; padding-bottom: 16px; margin-bottom: 24px;
        }
        .form-header h5 { font-weight: 800; color: #fff; margin: 0; font-size: 1.2rem; }

        .form-label-el { display: block; color: #94A3B8; font-size: 0.8rem; font-weight: 600; margin-bottom: 7px; }
        .form-input-el, .form-select-el, .form-textarea-el {
            width: 100%; padding: 12px 15px; background: #0B1120; border: 1.5px solid #1E293B;
            border-radius: 10px; color: #F1F5F9; font-size: 0.9rem; font-family: inherit;
            transition: 0.25s; outline: none; margin-bottom: 18px;
        }
        .form-input-el:focus, .form-select-el:focus, .form-textarea-el:focus {
            border-color: #FBBF24; box-shadow: 0 0 0 4px rgba(251,191,36,0.15);
        }
        .form-textarea-el { resize: vertical; min-height: 80px; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }

        .hint-box {
            background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3);
            border-radius: 8px; padding: 12px 16px; font-size: 0.75rem; color: #6EE7B7;
            margin-bottom: 18px; display: flex; align-items: flex-start; gap: 8px; line-height: 1.5;
        }
        .hint-box.hidden { display: none; }

        .btn-submit-el {
            background: linear-gradient(135deg, #FBBF24, #F59E0B); color: #1F2937;
            border: none; border-radius: 10px; padding: 14px; font-weight: 800;
            font-size: 0.95rem; width: 100%; cursor: pointer; transition: 0.25s;
            font-family: inherit; box-shadow: 0 8px 20px rgba(251,191,36,0.3); margin-top: 10px;
        }
        .btn-submit-el:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(251,191,36,0.45); }
        
        .btn-cancel-el {
            display: block; text-align: center; padding: 12px; background: transparent;
            border: 1.5px solid #1E293B; color: #94A3B8; border-radius: 10px;
            text-decoration: none; font-weight: 600; font-size: 0.9rem; margin-top: 12px; transition: 0.25s;
        }
        .btn-cancel-el:hover { background: #1E293B; color: #fff; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .promo-grid { grid-template-columns: 1fr; }
            .row-2, .row-3 { grid-template-columns: 1fr; gap: 0; }
            .form-container { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        
        <?php if ($editPromo || isset($_GET['add'])): ?>
            <!-- ==============================================
                 TAMPILAN 2: FORM TAMBAH / EDIT PROMO
                 ============================================== -->
            <a href="admin_promo.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Promo</a>

            <div class="form-container">
                <div class="form-header">
                    <h5><?= $editPromo ? 'Edit Promo' : 'Tambah Promo Baru'; ?></h5>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert-el alert-error-el"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php if ($editPromo): ?>
                        <input type="hidden" name="id_promo" value="<?= (int)$editPromo['id_promo']; ?>">
                    <?php endif; ?>

                    <label class="form-label-el">Judul Promo</label>
                    <input name="judul" type="text" class="form-input-el" 
                           value="<?= $editPromo ? htmlspecialchars($editPromo['judul']) : ''; ?>" 
                           required placeholder="Contoh: Diskon Akhir Pekan">

                    <label class="form-label-el">Deskripsi Singkat</label>
                    <textarea name="deskripsi" class="form-textarea-el" rows="2" required placeholder="Jelaskan detail promo..."><?= $editPromo ? htmlspecialchars($editPromo['deskripsi']) : ''; ?></textarea>

                    <div class="row-2">
                        <div>
                            <label class="form-label-el">Kode Promo</label>
                            <input name="kode_promo" type="text" class="form-input-el" 
                                   value="<?= $editPromo ? htmlspecialchars($editPromo['kode_promo']) : ''; ?>" 
                                   required style="text-transform:uppercase;" placeholder="WEEKEND20">
                        </div>
                        <div>
                            <label class="form-label-el">Status</label>
                            <select name="status" class="form-select-el">
                                <option value="Aktif" <?= ($editPromo && $editPromo['status'] === 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Nonaktif" <?= ($editPromo && $editPromo['status'] === 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <label class="form-label-el">Tipe Promo</label>
                    <select name="tipe_diskon" id="tipeDiskon" class="form-select-el" required>
                        <option value="persen" <?= ($editPromo && $editPromo['tipe_diskon'] === 'persen') ? 'selected' : ''; ?>>Persen (%)</option>
                        <option value="nominal" <?= ($editPromo && $editPromo['tipe_diskon'] === 'nominal') ? 'selected' : ''; ?>>Nominal (Rp)</option>
                        <option value="buy_x_get_y" <?= ($editPromo && $editPromo['tipe_diskon'] === 'buy_x_get_y') ? 'selected' : ''; ?>>Beli X Gratis Y (Voucher)</option>
                    </select>

                    <div class="hint-box <?= ($editPromo && $editPromo['tipe_diskon'] === 'buy_x_get_y') ? '' : 'hidden'; ?>" id="hintVoucher">
                        <i class="fas fa-info-circle"></i>
                        <span>Promo <strong>Voucher</strong> tidak memberi potongan harga. Setelah user bayar, mereka akan mendapatkan <strong>voucher tiket gratis</strong> yang bisa dipakai untuk film apa saja.</span>
                    </div>

                    <div class="row-2">
                        <div>
                            <label class="form-label-el" id="labelNilai">Nilai Diskon / Jumlah Voucher</label>
                            <input name="nilai_diskon" type="number" id="inputNilai" class="form-input-el" 
                                   value="<?= $editPromo ? (int)$editPromo['nilai_diskon'] : ''; ?>" 
                                   required placeholder="20">
                        </div>
                        <div>
                            <label class="form-label-el">Max. Diskon (Rp)</label>
                            <input name="max_diskon" type="number" class="form-input-el" 
                                   value="<?= $editPromo ? (int)$editPromo['max_diskon'] : '0'; ?>" placeholder="0">
                        </div>
                    </div>

                    <div class="row-3">
                        <div>
                            <label class="form-label-el">Min. Belanja (Rp)</label>
                            <input name="min_pembelian" type="number" class="form-input-el" 
                                   value="<?= $editPromo ? (int)$editPromo['min_pembelian'] : '0'; ?>" placeholder="0">
                        </div>
                        <div>
                            <label class="form-label-el">Kuota Klaim (0 = Unli)</label>
                            <input name="kuota" type="number" class="form-input-el" 
                                   value="<?= $editPromo ? (int)$editPromo['kuota'] : '0'; ?>" placeholder="0">
                        </div>
                        <div>
                            <label class="form-label-el">Tanggal Mulai</label>
                            <input name="tanggal_mulai" type="date" class="form-input-el" 
                                   value="<?= $editPromo && $editPromo['tanggal_mulai'] ? $editPromo['tanggal_mulai'] : ''; ?>">
                        </div>
                    </div>

                    <label class="form-label-el">Tanggal Berakhir</label>
                    <input name="tanggal_berakhir" type="date" class="form-input-el" 
                           value="<?= $editPromo && $editPromo['tanggal_berakhir'] ? $editPromo['tanggal_berakhir'] : ''; ?>">

                    <button type="submit" name="<?= $editPromo ? 'update_promo' : 'add_promo'; ?>" class="btn-submit-el">
                        <i class="fas fa-save"></i> <?= $editPromo ? 'Simpan Perubahan' : 'Tambah Promo'; ?>
                    </button>
                    <a href="admin_promo.php" class="btn-cancel-el">Batal</a>
                </form>
            </div>

        <?php else: ?>
            <!-- ==============================================
                 TAMPILAN 1: DAFTAR PROMO (GRID)
                 ============================================== -->
            <div class="page-header">
                <h4><i class="fas fa-tags"></i> Kelola Promo</h4>
                <a href="admin_promo.php?add=1" class="btn-add-promo">
                    <i class="fas fa-plus"></i> Tambah Promo
                </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-el alert-success-el"><i class="fas fa-check-circle"></i> Data promo berhasil disimpan!</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert-el alert-error-el"><i class="fas fa-trash"></i> Promo berhasil dihapus!</div>
            <?php endif; ?>

            <div class="promo-grid">
                <?php if ($promos && $promos->num_rows > 0): ?>
                    <?php while ($p = $promos->fetch_assoc()): 
                        $isVoucher = ($p['tipe_diskon'] ?? '') === 'buy_x_get_y';
                    ?>
                        <div class="promo-card <?= $isVoucher ? 'voucher-card' : ''; ?>">
                            <div class="promo-top">
                                <span class="promo-badge">
                                    <?php if ($isVoucher): ?>
                                        <i class="fas fa-gift"></i> Tiket Gratis
                                    <?php elseif ($p['tipe_diskon'] === 'persen'): ?>
                                        <i class="fas fa-fire"></i> Diskon
                                    <?php else: ?>
                                        <i class="fas fa-fire"></i> Potongan
                                    <?php endif; ?>
                                </span>
                                <span class="promo-status-badge <?= $p['status'] === 'Aktif' ? 'aktif' : 'nonaktif'; ?>">
                                    <?= htmlspecialchars($p['status']); ?>
                                </span>
                            </div>

                            <div class="promo-percent">
                                <?php 
                                if ($isVoucher) {
                                    echo '🎁<small> ' . (int)($p['nilai_diskon'] ?? 1) . ' VOUCHER</small>';
                                } elseif ($p['tipe_diskon'] === 'persen') {
                                    echo (int)($p['nilai_diskon'] ?? 0) . '<small>%</small>';
                                } else {
                                    echo 'Rp ' . number_format($p['nilai_diskon'] ?? 0, 0, ',', '.');
                                }
                                ?>
                            </div>

                            <div class="promo-title"><?= htmlspecialchars($p['judul']); ?></div>
                            <div class="promo-desc"><?= htmlspecialchars($p['deskripsi']); ?></div>

                            <div class="promo-stats">
                                <span><i class="fas fa-users"></i> <?= (int)$p['total_pakai']; ?>/<?= (int)$p['total_klaim']; ?> Klaim</span>
                                <?php if (($p['kuota'] ?? 0) > 0): ?>
                                    <span><i class="fas fa-ticket-alt"></i> Kuota: <?= (int)$p['kuota']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="promo-code-box"><?= htmlspecialchars($p['kode_promo']); ?></div>

                            <div class="promo-actions">
                                <a href="admin_promo.php?edit=<?= $p['id_promo']; ?>" class="btn-edit-promo">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="admin_promo.php?delete=<?= $p['id_promo']; ?>" class="btn-delete-promo" onclick="return confirm('Hapus promo ini?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #64748B;">
                        <i class="fas fa-tags" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                        Belum ada promo. Klik tombol Tambah Promo.
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // JavaScript untuk Form Dinamis (Tipe Promo)
        const tipeSelect = document.getElementById('tipeDiskon');
        if (tipeSelect) {
            const hintEl = document.getElementById('hintVoucher');
            const labelEl = document.getElementById('labelNilai');
            const inputEl = document.getElementById('inputNilai');

            function updateForm() {
                if (tipeSelect.value === 'buy_x_get_y') {
                    hintEl.classList.remove('hidden');
                    labelEl.textContent = 'Jumlah Voucher Gratis';
                    inputEl.placeholder = '1';
                    inputEl.min = '1';
                    if (!inputEl.value || parseInt(inputEl.value) < 1) inputEl.value = '1';
                } else {
                    hintEl.classList.add('hidden');
                    labelEl.textContent = 'Nilai Diskon (Angka / Rp)';
                    inputEl.placeholder = '20';
                    inputEl.min = '';
                }
            }

            tipeSelect.addEventListener('change', updateForm);
            updateForm(); // Jalankan saat halaman dimuat
        }
    </script>
</body>
</html>