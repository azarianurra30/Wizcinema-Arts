<?php
session_start();
include 'config.php';

// Auto-migration: tambah kolom id_pemesanan kalau belum ada
$cekKolom = $conn->query("SHOW COLUMNS FROM ulasan LIKE 'id_pemesanan'");
if ($cekKolom && $cekKolom->num_rows === 0) {
    $conn->query("ALTER TABLE ulasan ADD COLUMN id_pemesanan INT DEFAULT NULL");
}

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Hanya user yang dapat memberikan ulasan.";
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_user      = (int)$_SESSION['id_user'];
$id_film      = isset($_POST['id_film']) ? (int)$_POST['id_film'] : 0;
$id_pemesanan = isset($_POST['id_pemesanan']) ? (int)$_POST['id_pemesanan'] : 0;
$rating       = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$komentar_raw = trim($_POST['komentar'] ?? '');

if (!$id_film) {
    $_SESSION['error'] = "Data film tidak valid.";
    header('Location: index.php');
    exit;
}

if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Rating harus antara 1 sampai 5 bintang.";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

if (empty($komentar_raw)) {
    $_SESSION['error'] = "Komentar tidak boleh kosong.";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

if (mb_strlen($komentar_raw) > 1000) {
    $_SESSION['error'] = "Komentar terlalu panjang (maksimal 1000 karakter).";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

$film = $conn->query("SELECT id_film, judul FROM film WHERE id_film = $id_film")->fetch_assoc();
if (!$film) {
    $_SESSION['error'] = "Film tidak ditemukan.";
    header('Location: index.php');
    exit;
}

// Cek duplikat
$cekUlasan = $conn->prepare("SELECT id_ulasan FROM ulasan WHERE id_user = ? AND id_film = ? LIMIT 1");
$cekUlasan->bind_param("ii", $id_user, $id_film);
$cekUlasan->execute();
$sudahUlasan = $cekUlasan->get_result()->num_rows > 0;
$cekUlasan->close();

if ($sudahUlasan) {
    $_SESSION['error'] = "Anda sudah pernah memberikan ulasan untuk film ini.";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

// Validasi pemesanan
$stmt = $conn->prepare("
    SELECT p.id_pemesanan, j.tanggal, j.jam 
    FROM pemesanan p 
    JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
    WHERE p.id_user = ? 
      AND j.id_film = ? 
      AND p.status = 'Lunas'
    ORDER BY p.id_pemesanan DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $id_user, $id_film);
$stmt->execute();
$pemesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pemesanan) {
    $_SESSION['error'] = "Anda belum menonton film \"{$film['judul']}\" — tidak bisa memberi ulasan.";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

$id_pemesanan_valid = (int)$pemesanan['id_pemesanan'];

// Validasi jadwal
$jadwal_dt = strtotime($pemesanan['tanggal'] . ' ' . $pemesanan['jam']);
if (time() < $jadwal_dt) {
    $_SESSION['error'] = "Ulasan hanya bisa diberikan setelah film ditayangkan. Jadwal Anda: " . date('d M Y H:i', $jadwal_dt) . " WIB.";
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}

// Insert
$stmt = $conn->prepare("
    INSERT INTO ulasan (id_film, id_user, id_pemesanan, rating, komentar, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiiis", $id_film, $id_user, $id_pemesanan_valid, $rating, $komentar_raw);

if ($stmt->execute()) {
    $_SESSION['message'] = "Terima kasih! Ulasan Anda untuk \"{$film['judul']}\" berhasil dikirim.";
    $stmt->close();
    header('Location: detail_film.php?id=' . $id_film);
    exit;
} else {
    $_SESSION['error'] = "Gagal menyimpan ulasan: " . $stmt->error;
    $stmt->close();
    header('Location: detail_film.php?id=' . $id_film);
    exit;
}
?>