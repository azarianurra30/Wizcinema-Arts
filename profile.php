<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header('Location: dashboard.php');
    exit;
}

$id_user = (int)$_SESSION['id_user'];
$user = $conn->query("SELECT * FROM users WHERE id_user = $id_user")->fetch_assoc();
$success = '';
$error = '';

// ===== PROSES UPDATE PROFIL =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $bio  = trim($_POST['bio'] ?? '');
    $foto = $user['foto_profil'] ?? 'default.png';

    if (empty($nama)) {
        $error = 'Nama tidak boleh kosong.';
    } elseif (mb_strlen($nama) > 100) {
        $error = 'Nama terlalu panjang (maks 100 karakter).';
    } elseif (mb_strlen($bio) > 500) {
        $error = 'Bio terlalu panjang (maks 500 karakter).';
    } else {
        // Upload foto baru
        if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];

            if (!in_array($ext, $allowed)) {
                $error = 'Format foto harus JPG, PNG, WEBP, atau GIF.';
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran foto maksimal 2MB.';
            } else {
                $newFoto = 'profile_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], 'assets/img/' . $newFoto)) {
                    if ($foto !== 'default.png' && file_exists('assets/img/' . $foto)) {
                        @unlink('assets/img/' . $foto);
                    }
                    $foto = $newFoto;
                } else {
                    $error = 'Gagal mengupload foto.';
                }
            }
        }

        if (empty($error)) {
            $nama_esc = $conn->real_escape_string($nama);
            $bio_esc  = $conn->real_escape_string($bio);
            $foto_esc = $conn->real_escape_string($foto);

            $stmt = $conn->prepare("UPDATE users SET nama=?, bio=?, foto_profil=? WHERE id_user=?");
            $stmt->bind_param("sssi", $nama_esc, $bio_esc, $foto_esc, $id_user);
            $stmt->execute();
            $stmt->close();

            $_SESSION['nama'] = $nama;
            $_SESSION['bio']  = $bio;
            $_SESSION['foto'] = $foto;

            $success = "Profil berhasil diperbarui!";
            $user = $conn->query("SELECT * FROM users WHERE id_user = $id_user")->fetch_assoc();
        }
    }
}

// ===== STATISTIK USER =====
$totalOrder  = $conn->query("SELECT COUNT(*) AS c FROM pemesanan WHERE id_user = $id_user")->fetch_assoc()['c'];
$totalLunas  = $conn->query("SELECT COUNT(*) AS c FROM pemesanan WHERE id_user = $id_user AND status='Lunas'")->fetch_assoc()['c'];
$totalUlasan = $conn->query("SELECT COUNT(*) AS c FROM ulasan WHERE id_user = $id_user")->fetch_assoc()['c'];
$totalSpent  = $conn->query("SELECT COALESCE(SUM(total_harga),0) AS t FROM pemesanan WHERE id_user = $id_user AND status='Lunas'")->fetch_assoc()['t'];

// ===== RIWAYAT PESANAN =====
$orders = $conn->query("
    SELECT p.*, f.judul, f.poster, j.tanggal, j.jam 
    FROM pemesanan p 
    JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
    JOIN film f ON j.id_film = f.id_film 
    WHERE p.id_user = $id_user 
    ORDER BY p.tanggal_pesan DESC 
    LIMIT 5
");

// Info foto user
$fotoUser = $user['foto_profil'] ?? 'default.png';
$hasFoto  = !empty($fotoUser) && $fotoUser !== 'default.png' && file_exists('assets/img/'.$fotoUser);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - WizCinema</title>
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
            --accent-blue: #3B82F6;
            --accent-gold: #FBBF24;
            --accent-green: #10B981;
            --accent-red: #EF4444;
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

        /* ===== HEADER ===== */
        .page-header { margin-bottom: 32px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin: 0 0 6px 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ===== ALERTS ===== */
        .alert-custom {
            padding: 16px 20px; border-radius: 8px; margin-bottom: 24px;
            font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 12px;
            border: 1px solid transparent;
        }
        .alert-success { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.3); color: #34D399; }
        .alert-error { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #F87171; }

        /* ===== LAYOUT GRID ===== */
        .profile-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ===== CARD STYLES ===== */
        .card-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 { font-size: 1.05rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: var(--accent-blue); }

        .card-body { padding: 24px; }

        /* ===== KOLOM KIRI: INFO USER ===== */
        .user-info-card { text-align: center; padding-top: 32px; }
        
        .avatar-container {
            position: relative;
            width: 110px; height: 110px;
            margin: 0 auto 20px;
        }
        .avatar-img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--border-color);
            background: var(--bg-body);
        }
        .avatar-initial {
            width: 100%; height: 100%;
            border-radius: 50%;
            background: var(--bg-body);
            border: 3px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; font-weight: 800; color: var(--accent-blue);
        }
        .role-badge {
            display: inline-block;
            background: rgba(59,130,246,0.15);
            color: var(--accent-blue);
            font-size: 0.7rem; font-weight: 800;
            padding: 4px 12px; border-radius: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .user-name-lg { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
        .user-username { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 24px; font-weight: 500; }
        
        .user-bio {
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-style: italic;
            line-height: 1.6;
            text-align: left;
            margin-bottom: 24px;
        }

        .user-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .stat-box {
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            text-align: left;
        }
        .stat-box .lbl { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-box .val { font-size: 1.1rem; font-weight: 800; color: #fff; }

        /* ===== KOLOM KANAN: FORM & RIWAYAT ===== */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px; }
        
        .form-control-custom {
            width: 100%;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 16px;
            color: #fff;
            font-size: 0.9rem;
            font-family: inherit;
            transition: 0.2s;
            outline: none;
        }
        .form-control-custom:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-control-custom::placeholder { color: #475569; }

        .upload-area {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-body);
            border: 1px dashed var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
        }
        .upload-area:hover { border-color: var(--accent-blue); }
        .upload-preview {
            width: 50px; height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--bg-surface);
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .upload-preview img { width: 100%; height: 100%; object-fit: cover; }
        .upload-preview span { font-size: 1.2rem; font-weight: 800; color: var(--accent-blue); }
        .upload-text { flex: 1; }
        .upload-text strong { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 4px; }
        .upload-text span { font-size: 0.75rem; color: var(--text-secondary); }
        .upload-input { display: none; }

        .btn-submit {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 700;
            font-size: 0.9rem;
            font-family: inherit;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit:hover { background: #2563EB; }

        /* Riwayat Pesanan */
        .order-list { display: flex; flex-direction: column; gap: 12px; }
        .order-item {
            display: flex; align-items: center; gap: 16px;
            background: var(--bg-body); border: 1px solid var(--border-color);
            padding: 12px 16px; border-radius: 8px; transition: 0.2s;
        }
        .order-item:hover { border-color: #334155; }
        
        .order-poster { width: 40px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); }
        .order-details { flex: 1; min-width: 0; }
        .order-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .order-meta { font-size: 0.75rem; color: var(--text-secondary); display: flex; gap: 12px; }
        
        .order-right { text-align: right; flex-shrink: 0; }
        .order-price { font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; }
        .status-badge { 
            font-size: 0.65rem; font-weight: 800; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;
        }
        .status-lunas { background: rgba(16,185,129,0.15); color: #34D399; }
        .status-pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
        .status-batal { background: rgba(239,68,68,0.15); color: #F87171; }
        .status-refund { background: rgba(59,130,246,0.15); color: #60A5FA; }

        .empty-state { text-align: center; padding: 32px 20px; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 24px; }
            .profile-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <h1>Pengaturan Profil</h1>
            <p>Kelola informasi akun dan lihat riwayat transaksi Anda.</p>
        </div>

        <?php if(!empty($success)): ?>
            <div class="alert-custom alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert-custom alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="profile-layout">
            
            <!-- KOLOM KIRI: KARTU IDENTITAS -->
            <div class="card-panel user-info-card">
                <div class="card-body">
                    <div class="avatar-container">
                        <?php if ($hasFoto): ?>
                            <img src="assets/img/<?= htmlspecialchars($fotoUser); ?>" class="avatar-img" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-initial"><?= strtoupper(substr($user['nama'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="role-badge">Member Premium</div>
                    <div class="user-name-lg"><?= htmlspecialchars($user['nama']); ?></div>
                    <div class="user-username">@<?= htmlspecialchars($user['username']); ?></div>

                    <div class="user-bio">
                        <?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Belum ada bio. Silakan edit profil untuk menambahkan deskripsi diri.' ?>
                    </div>

                    <div class="user-stats-grid">
                        <div class="stat-box">
                            <div class="lbl">Pesanan</div>
                            <div class="val"><?= $totalOrder; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="lbl">Lunas</div>
                            <div class="val"><?= $totalLunas; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="lbl">Ulasan</div>
                            <div class="val"><?= $totalUlasan; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="lbl">Pengeluaran</div>
                            <div class="val" style="font-size: 0.95rem;">Rp <?= number_format($totalSpent/1000, 0, ',', '.'); ?>K</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: FORM & RIWAYAT -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Form Edit Profil -->
                <div class="card-panel">
                    <div class="card-header">
                        <h2><i class="fas fa-user-edit"></i> Edit Informasi</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            
                            <div class="upload-area" onclick="document.getElementById('inputFoto').click()">
                                <div class="upload-preview" id="previewFoto">
                                    <?php if ($hasFoto): ?>
                                        <img src="assets/img/<?= htmlspecialchars($fotoUser); ?>" alt="Preview">
                                    <?php else: ?>
                                        <span><?= strtoupper(substr($user['nama'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="upload-text">
                                    <strong>Ganti Foto Profil</strong>
                                    <span>JPG, PNG, atau WEBP (Maks 2MB)</span>
                                </div>
                                <input type="file" name="foto" id="inputFoto" class="upload-input" accept="image/*" onchange="previewImage(event)">
                            </div>

                            <div class="form-group" style="margin-top: 24px;">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control-custom" value="<?= htmlspecialchars($user['nama']); ?>" required placeholder="Masukkan nama lengkap">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bio</label>
                                <textarea name="bio" class="form-control-custom" rows="3" placeholder="Ceritakan sedikit tentang dirimu..."><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Riwayat Pesanan -->
                <div class="card-panel">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Riwayat Pesanan Terakhir</h2>
                    </div>
                    <div class="card-body">
                        <div class="order-list">
                            <?php if($orders && $orders->num_rows > 0): ?>
                                <?php while($o = $orders->fetch_assoc()): 
                                    $status = strtolower($o['status'] ?? 'unknown');
                                    $statusClass = 'status-pending';
                                    if ($status === 'lunas') $statusClass = 'status-lunas';
                                    elseif ($status === 'batal') $statusClass = 'status-batal';
                                    elseif ($status === 'refunded') $statusClass = 'status-refund';
                                ?>
                                    <div class="order-item">
                                        <img src="assets/img/<?= htmlspecialchars($o['poster'] ?? 'default.jpg'); ?>" class="order-poster" onerror="this.src='assets/img/default.jpg'">
                                        <div class="order-details">
                                            <div class="order-title"><?= htmlspecialchars($o['judul']); ?></div>
                                            <div class="order-meta">
                                                <span><i class="far fa-calendar"></i> <?= date('d M Y', strtotime($o['tanggal'])); ?></span>
                                                <span><i class="far fa-clock"></i> <?= date('H:i', strtotime($o['jam'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="order-right">
                                            <div class="order-price">Rp <?= number_format($o['total_harga'], 0, ',', '.'); ?></div>
                                            <div class="status-badge <?= $statusClass; ?>"><?= htmlspecialchars($o['status']); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    Belum ada riwayat pemesanan.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran foto maksimal 2MB.');
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>