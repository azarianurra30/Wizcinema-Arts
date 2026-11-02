<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

// ===== CEK LOGIN & ROLE =====
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

if ($_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Hanya user yang dapat mengklaim promo']);
    exit;
}

$id_user = (int)$_SESSION['id_user'];
$kode    = isset($_POST['kode']) ? strtoupper(trim($_POST['kode'])) : '';

if (empty($kode)) {
    echo json_encode(['success' => false, 'message' => 'Kode promo tidak valid']);
    exit;
}

// ===== CARI PROMO =====
$stmt = $conn->prepare("
    SELECT id_promo, judul, deskripsi, tipe_diskon, nilai_diskon, 
           min_pembelian, max_diskon, kuota, tanggal_mulai, tanggal_berakhir, status
    FROM promo 
    WHERE kode_promo = ? AND status = 'Aktif'
    LIMIT 1
");
$stmt->bind_param("s", $kode);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$promo) {
    echo json_encode(['success' => false, 'message' => 'Promo tidak ditemukan atau tidak aktif']);
    exit;
}

// ===== CEK PERIODE PROMO =====
$today = date('Y-m-d');
if (!empty($promo['tanggal_mulai']) && $today < $promo['tanggal_mulai']) {
    echo json_encode(['success' => false, 'message' => 'Promo belum dimulai']);
    exit;
}
if (!empty($promo['tanggal_berakhir']) && $today > $promo['tanggal_berakhir']) {
    echo json_encode(['success' => false, 'message' => 'Promo sudah kadaluarsa']);
    exit;
}

// ===== CEK KUOTA =====
$kuota = (int)$promo['kuota'];
if ($kuota > 0) {
    $terpakai = (int)$conn->query("
        SELECT COUNT(*) AS c FROM user_promo 
        WHERE id_promo = {$promo['id_promo']} AND status = 'used'
    ")->fetch_assoc()['c'];

    if ($terpakai >= $kuota) {
        echo json_encode(['success' => false, 'message' => 'Kuota promo sudah habis']);
        exit;
    }
}

// ===== CEK APAKAH USER SUDAH KLAIM =====
$stmt = $conn->prepare("SELECT id_user_promo, status FROM user_promo WHERE id_user = ? AND id_promo = ?");
$stmt->bind_param("ii", $id_user, $promo['id_promo']);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    if ($existing['status'] === 'used') {
        echo json_encode(['success' => false, 'message' => 'Anda sudah pernah pakai promo ini']);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Promo sudah ada di daftar Anda',
            'already' => true
        ]);
    }
    exit;
}

// ===== SIMPAN KLAIM =====
$stmt = $conn->prepare("
    INSERT INTO user_promo (id_user, id_promo, kode_promo, status, created_at) 
    VALUES (?, ?, ?, 'active', NOW())
");
$stmt->bind_param("iis", $id_user, $promo['id_promo'], $kode);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'success' => true,
        'message' => 'Promo "' . $promo['judul'] . '" berhasil diklaim!',
        'kode'    => $kode
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan klaim: ' . $stmt->error
    ]);
    $stmt->close();
}