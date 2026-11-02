<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Auto-tambah kolom trailer kalau belum ada
$cekTrailer = $conn->query("SHOW COLUMNS FROM film LIKE 'trailer'");
if ($cekTrailer->num_rows == 0) {
    $conn->query("ALTER TABLE film ADD COLUMN trailer VARCHAR(200) DEFAULT NULL");
}

// ===== AUTO-FIX: perpanjang kolom biar tidak error "Data too long" =====
$conn->query("ALTER TABLE film MODIFY COLUMN judul VARCHAR(200) DEFAULT NULL");
$conn->query("ALTER TABLE film MODIFY COLUMN genre VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE film MODIFY COLUMN durasi VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE film MODIFY COLUMN rating_usia VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE film MODIFY COLUMN status VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE film MODIFY COLUMN trailer VARCHAR(200) DEFAULT NULL");

function uploadPosterFile($posterInput) {
    if (!isset($_FILES[$posterInput]) || $_FILES[$posterInput]['error'] !== UPLOAD_ERR_OK) return null;
    $fileName = basename($_FILES[$posterInput]['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if (!in_array($fileExt, $allowed, true)) return null;
    $targetDir = __DIR__ . '/assets/img/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $newName = 'poster_' . uniqid() . '.' . $fileExt;
    if (move_uploaded_file($_FILES[$posterInput]['tmp_name'], $targetDir . $newName)) return $newName;
    return null;
}

function deletePosterFile($posterName, $targetDir = null) {
    if (empty($posterName)) return;
    if ($targetDir === null) $targetDir = __DIR__ . '/assets/img/';
    $safePoster = basename($posterName);
    $fullPath = $targetDir . $safePoster;
    if (file_exists($fullPath) && is_file($fullPath)) @unlink($fullPath);
}

// ===== TAMBAH FILM =====
if (isset($_POST['add_film'])) {
    $judul    = $_POST['judul'];
    $genre    = $_POST['genre'];
    $durasi   = $_POST['durasi'];
    $rating   = $_POST['rating'];
    $sinopsis = $_POST['sinopsis'];
    $status   = $_POST['status'];
    $trailer  = $_POST['trailer'] ?? '';
    $poster   = uploadPosterFile('poster_file');

    if (!$poster) {
        $error = 'Unggah poster harus berupa file JPG, PNG, WEBP, atau GIF.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO film (judul, genre, durasi, rating_usia, sinopsis, poster, status, trailer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare gagal: " . $conn->error);
            $stmt->bind_param("ssssssss", $judul, $genre, $durasi, $rating, $sinopsis, $poster, $status, $trailer);
            if (!$stmt->execute()) throw new Exception("Execute gagal: " . $stmt->error);
            $stmt->close();
            header('Location: admin_films.php?success=1');
            exit;
        } catch (Exception $e) {
            $error = "Gagal menambah film: " . $e->getMessage();
        }
    }
}

// ===== UPDATE FILM =====
if (isset($_POST['update_film']) && is_numeric($_POST['id_film'])) {
    $id_film  = (int)$_POST['id_film'];
    $judul    = $_POST['judul'];
    $genre    = $_POST['genre'];
    $durasi   = $_POST['durasi'];
    $rating   = $_POST['rating'];
    $sinopsis = $_POST['sinopsis'];
    $status   = $_POST['status'];
    $trailer  = $_POST['trailer'] ?? '';
    $poster   = uploadPosterFile('poster_file');
    if (!$poster) $poster = $_POST['current_poster'];

    try {
        $stmt = $conn->prepare("UPDATE film SET judul=?, genre=?, durasi=?, rating_usia=?, sinopsis=?, poster=?, status=?, trailer=? WHERE id_film=?");
        if (!$stmt) throw new Exception("Prepare gagal: " . $conn->error);
        $stmt->bind_param("ssssssssi", $judul, $genre, $durasi, $rating, $sinopsis, $poster, $status, $trailer, $id_film);
        if (!$stmt->execute()) throw new Exception("Execute gagal: " . $stmt->error);
        $stmt->close();
        header('Location: admin_films.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = "Gagal update film: " . $e->getMessage();
    }
}

// ===== AMBIL DATA EDIT =====
$editFilm = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id_film = (int)$_GET['edit'];
    $editFilm = $conn->query("SELECT * FROM film WHERE id_film = $id_film")->fetch_assoc();
}

// ===== HAPUS FILM =====
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_film = (int)$_GET['delete'];
    $filmData = $conn->query("SELECT poster FROM film WHERE id_film = $id_film")->fetch_assoc();
    $posterToDelete = $filmData['poster'] ?? null;

    $jadwalIds = [];
    $jadwalResult = $conn->query("SELECT id_jadwal FROM jadwal WHERE id_film = $id_film");
    while ($row = $jadwalResult->fetch_assoc()) $jadwalIds[] = $row['id_jadwal'];

    if (!empty($jadwalIds)) {
        $jadwalList = implode(',', array_map('intval', $jadwalIds));
        $conn->query("DELETE dp FROM detail_pemesanan dp JOIN pemesanan p ON dp.id_pemesanan = p.id_pemesanan WHERE p.id_jadwal IN ($jadwalList)");
        $conn->query("DELETE FROM pemesanan WHERE id_jadwal IN ($jadwalList)");
        $conn->query("DELETE FROM kursi WHERE id_jadwal IN ($jadwalList)");
    }
    $conn->query("DELETE FROM jadwal WHERE id_film = $id_film");
    $conn->query("DELETE FROM film WHERE id_film = $id_film");
    deletePosterFile($posterToDelete);
    header('Location: admin_films.php?deleted=1');
    exit;
}

$films = $conn->query("SELECT * FROM film ORDER BY judul ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Film - Admin</title>
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
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(59,130,246,0.35);
        }
        .btn-add-film {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.25s;
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
        }
        .btn-add-film:hover { transform: translateY(-2px); color: #fff; box-shadow: 0 12px 25px rgba(59,130,246,0.5); }

        /* ===== ALERTS ===== */
        .alert-el {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; border-radius: 10px;
            font-size: 0.85rem; margin-bottom: 22px;
        }
        .alert-success-el { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6EE7B7; }
        .alert-error-el { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; }

        /* ===== MOVIE GRID (HALAMAN DEPAN) ===== */
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .movie-card {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        .movie-card:hover {
            transform: translateY(-5px);
            border-color: #3B82F6;
            box-shadow: 0 15px 30px rgba(59,130,246,0.2);
        }
        .movie-poster {
            width: 100%;
            height: 280px;
            object-fit: cover;
            background: #0B1120;
            display: block;
        }
        .movie-info { padding: 16px 18px; }
        .movie-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .movie-meta {
            display: flex;
            gap: 6px;
            margin-bottom: 14px;
        }
        .movie-meta span {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .badge-genre { background: rgba(148,163,184,0.1); color: #CBD5E1; }
        .badge-status { background: rgba(59,130,246,0.15); color: #60A5FA; }
        
        .btn-group-action {
            display: flex;
            gap: 8px;
        }
        .btn-edit {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 8px;
            background: rgba(59,130,246,0.15);
            color: #60A5FA;
            border: 1.5px solid rgba(59,130,246,0.3);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-edit:hover { background: #3B82F6; color: #fff; }
        
        .btn-delete {
            padding: 8px 12px;
            background: rgba(239,68,68,0.1);
            color: #FCA5A5;
            border: 1.5px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-delete:hover { background: #EF4444; color: #fff; }

        /* ===== FORM VIEW (TAMBAH/EDIT) ===== */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #94A3B8;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .back-link:hover { color: #60A5FA; transform: translateX(-3px); }

        .form-container {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 30px;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-header {
            border-bottom: 1px solid #1E293B;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .form-header h5 {
            font-weight: 800;
            color: #fff;
            margin: 0;
            font-size: 1.2rem;
        }

        .form-label-el {
            display: block; color: #94A3B8;
            font-size: 0.8rem; font-weight: 600;
            margin-bottom: 7px;
        }
        .form-input-el, .form-select-el, .form-textarea-el {
            width: 100%; padding: 12px 15px;
            background: #0B1120; border: 1.5px solid #1E293B;
            border-radius: 10px; color: #F1F5F9;
            font-size: 0.9rem; font-family: inherit;
            transition: 0.25s; outline: none; margin-bottom: 18px;
        }
        .form-input-el:focus, .form-select-el:focus, .form-textarea-el:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
        }
        .form-textarea-el { resize: vertical; min-height: 100px; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .hint-trailer {
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.25);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.75rem;
            color: #FCD34D;
            margin-top: -10px;
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }
        .hint-trailer i { color: #FBBF24; flex-shrink: 0; margin-top: 2px; }

        .btn-submit-el {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff; border: none; border-radius: 10px;
            padding: 14px; font-weight: 700; font-size: 0.95rem;
            width: 100%; cursor: pointer; transition: 0.25s;
            font-family: inherit;
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
            margin-top: 10px;
        }
        .btn-submit-el:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(59,130,246,0.45); }
        .btn-cancel-el {
            display: block; text-align: center; padding: 12px;
            background: transparent; border: 1.5px solid #1E293B;
            color: #94A3B8; border-radius: 10px; text-decoration: none;
            font-weight: 600; font-size: 0.9rem; margin-top: 12px;
            transition: 0.25s;
        }
        .btn-cancel-el:hover { background: #1E293B; color: #fff; }

        .poster-preview {
            display: flex; align-items: center; gap: 15px;
            padding: 12px; background: #0B1120;
            border: 1px solid #1E293B; border-radius: 10px;
            margin-bottom: 15px;
        }
        .poster-preview img {
            width: 60px; height: 85px; object-fit: cover;
            border-radius: 8px; border: 1px solid #1E293B;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .movie-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .movie-poster { height: 220px; }
            .row-2 { grid-template-columns: 1fr; gap: 0; }
            .form-container { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        
        <?php if ($editFilm || isset($_GET['add'])): ?>
            <!-- ==============================================
                 TAMPILAN 2: FORM TAMBAH / EDIT FILM
                 ============================================== -->
            <a href="admin_films.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Film</a>

            <div class="form-container">
                <div class="form-header">
                    <h5><?= $editFilm ? 'Edit Film' : 'Tambah Film Baru'; ?></h5>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert-el alert-error-el"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php if ($editFilm): ?>
                        <input type="hidden" name="id_film" value="<?= htmlspecialchars($editFilm['id_film']); ?>">
                        <input type="hidden" name="current_poster" value="<?= htmlspecialchars($editFilm['poster']); ?>">
                    <?php endif; ?>

                    <label class="form-label-el">Judul Film</label>
                    <input name="judul" type="text" class="form-input-el" 
                           value="<?= $editFilm ? htmlspecialchars($editFilm['judul']) : ''; ?>" 
                           required placeholder="Contoh: Avengers: Endgame">

                    <div class="row-2">
                        <div>
                            <label class="form-label-el">Genre</label>
                            <input name="genre" type="text" class="form-input-el" 
                                   value="<?= $editFilm ? htmlspecialchars($editFilm['genre']) : ''; ?>" 
                                   required placeholder="Aksi, Petualangan">
                        </div>
                        <div>
                            <label class="form-label-el">Durasi</label>
                            <input name="durasi" type="text" class="form-input-el" 
                                   value="<?= $editFilm ? htmlspecialchars($editFilm['durasi']) : ''; ?>" 
                                   required placeholder="2 jam 15 menit">
                        </div>
                    </div>

                    <div class="row-2">
                        <div>
                            <label class="form-label-el">Rating Usia</label>
                            <input name="rating" type="text" class="form-input-el" 
                                   value="<?= $editFilm ? htmlspecialchars($editFilm['rating_usia']) : ''; ?>" 
                                   required placeholder="Contoh: 13+">
                        </div>
                        <div>
                            <label class="form-label-el">Status</label>
                            <select name="status" class="form-input-el">
                                <option value="Now Showing" <?= ($editFilm && $editFilm['status'] === 'Now Showing') ? 'selected' : ''; ?>>Now Showing</option>
                                <option value="Coming Soon" <?= ($editFilm && $editFilm['status'] === 'Coming Soon') ? 'selected' : ''; ?>>Coming Soon</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($editFilm && !empty($editFilm['poster']) && file_exists('assets/img/'.$editFilm['poster'])): ?>
                        <label class="form-label-el">Poster Saat Ini</label>
                        <div class="poster-preview">
                            <img src="assets/img/<?= htmlspecialchars($editFilm['poster']); ?>" alt="Poster">
                            <span style="font-size:0.85rem; color:#94A3B8;"><?= htmlspecialchars($editFilm['poster']); ?></span>
                        </div>
                    <?php endif; ?>

                    <label class="form-label-el"><?= $editFilm ? 'Ganti Poster (Opsional)' : 'Upload Poster'; ?></label>
                    <input name="poster_file" type="file" class="form-input-el" accept="image/*" <?= $editFilm ? '' : 'required'; ?>>

                    <label class="form-label-el"><i class="fab fa-youtube" style="color:#EF4444; margin-right:4px;"></i> ID Trailer YouTube</label>
                    <input name="trailer" type="text" class="form-input-el" 
                           value="<?= $editFilm ? htmlspecialchars($editFilm['trailer'] ?? '') : ''; ?>" 
                           placeholder="Contoh: ksj69JaBrAo">
                    <div class="hint-trailer">
                        <i class="fas fa-info-circle"></i>
                        <span>Ambil <strong>ID</strong> dari link YouTube. Contoh: <code>youtu.be/<strong>ksj69JaBrAo</strong></code> → isi <strong>ksj69JaBrAo</strong> saja.</span>
                    </div>

                    <label class="form-label-el">Sinopsis</label>
                    <textarea name="sinopsis" class="form-textarea-el" rows="4" required placeholder="Ceritakan alur film secara singkat..."><?= $editFilm ? htmlspecialchars($editFilm['sinopsis']) : ''; ?></textarea>

                    <button type="submit" name="<?= $editFilm ? 'update_film' : 'add_film'; ?>" class="btn-submit-el">
                        <i class="fas fa-save"></i> <?= $editFilm ? 'Simpan Perubahan' : 'Tambah Film'; ?>
                    </button>
                    <a href="admin_films.php" class="btn-cancel-el">Batal</a>
                </form>
            </div>

        <?php else: ?>
            <!-- ==============================================
                 TAMPILAN 1: DAFTAR FILM (GRID)
                 ============================================== -->
            <div class="page-header">
                <h4><i class="fas fa-film"></i> Kelola Film</h4>
                <a href="admin_films.php?add=1" class="btn-add-film">
                    <i class="fas fa-plus"></i> Tambah Film Baru
                </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-el alert-success-el"><i class="fas fa-check-circle"></i> Data film berhasil disimpan!</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert-el alert-error-el"><i class="fas fa-trash"></i> Film berhasil dihapus!</div>
            <?php endif; ?>

            <div class="movie-grid">
                <?php if ($films && $films->num_rows > 0): ?>
                    <?php while ($f = $films->fetch_assoc()): ?>
                        <div class="movie-card">
                            <img src="assets/img/<?= htmlspecialchars($f['poster'] ?? 'default.jpg'); ?>" class="movie-poster" onerror="this.src='assets/img/default.jpg'" alt="<?= htmlspecialchars($f['judul']); ?>">
                            <div class="movie-info">
                                <div class="movie-title" title="<?= htmlspecialchars($f['judul']); ?>"><?= htmlspecialchars($f['judul']); ?></div>
                                <div class="movie-meta">
                                    <span class="badge-genre"><?= htmlspecialchars($f['genre']); ?></span>
                                    <span class="badge-status"><?= htmlspecialchars($f['status']); ?></span>
                                </div>
                                <div class="btn-group-action">
                                    <a href="admin_films.php?edit=<?= $f['id_film']; ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="admin_films.php?delete=<?= $f['id_film']; ?>" class="btn-delete" onclick="return confirm('Hapus film ini beserta jadwalnya?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #64748B;">
                        <i class="fas fa-film" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                        Belum ada film. Klik tombol Tambah Film Baru.
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>