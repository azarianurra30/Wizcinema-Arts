<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();
include 'config.php';
include 'csrf.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kasir') {
    header('Location: index.php');
    exit;
}

$id_pemesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_pemesanan) {
    $_SESSION['error_kasir'] = "Data pesanan tidak valid.";
    header('Location: dashboard.php');
    exit;
}

// ===== CSRF WAJIB =====
if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    $_SESSION['error_kasir'] = "CSRF token tidak valid. Refresh halaman dashboard dan coba lagi.";
    header('Location: dashboard.php');
    exit;
}

// ===== AMBIL DATA PESANAN =====
$order = $conn->query("
    SELECT p.*, u.id_user AS user_id, u.nama AS user_nama, 
           f.judul, j.tanggal, j.jam, j.id_jadwal
    FROM pemesanan p 
    JOIN users u ON p.id_user = u.id_user 
    JOIN jadwal j ON p.id_jadwal = j.id_jadwal 
    JOIN film f ON j.id_film = f.id_film 
    WHERE p.id_pemesanan = $id_pemesanan
    LIMIT 1
")->fetch_assoc();

if (!$order) {
    $_SESSION['error_kasir'] = "Pesanan tidak ditemukan.";
    header('Location: dashboard.php');
    exit;
}

if ($order['status'] !== 'Batal') {
    $_SESSION['error_kasir'] = "Refund tidak dapat diproses. Status pesanan saat ini: {$order['status']}.";
    header('Location: dashboard.php');
    exit;
}

$user_id      = (int)$order['user_id'];
$judul_film   = $order['judul'];
$total        = number_format($order['total_harga'], 0, ',', '.');
$metode_asli  = strtoupper($order['metode'] ?? 'Cash');
$id_jadwal    = (int)$order['id_jadwal'];

$labelMetode = $metode_asli;
if (in_array(strtolower($metode_asli), ['dana','gopay','shopeepay'])) {
    $labelMetode = "akun " . $metode_asli;
} elseif (in_array(strtolower($metode_asli), ['bca','mandiri','bri','bsi'])) {
    $labelMetode = "rekening " . $metode_asli;
} else {
    $labelMetode = "tunai di kasir WizCinema";
}

$conn->begin_transaction();
try {
    // ===== 1. Update status pesanan =====
    $stmtUpd = $conn->prepare("UPDATE pemesanan SET status='Refunded' WHERE id_pemesanan = ? AND status = 'Batal'");
    if (!$stmtUpd) throw new Exception("Prepare update gagal: " . $conn->error);
    $stmtUpd->bind_param("i", $id_pemesanan);
    if (!$stmtUpd->execute()) throw new Exception("Update gagal: " . $stmtUpd->error);
    if ($stmtUpd->affected_rows === 0) {
        $stmtUpd->close();
        throw new Exception("Status pesanan sudah berubah. Mungkin sudah direfund sebelumnya.");
    }
    $stmtUpd->close();

    // ===== 2. BEBASKAN KURSI (PERBAIKAN UTAMA) =====
    $kursiList = [];
    $qKursi = $conn->query("SELECT kode_kursi FROM detail_pemesanan WHERE id_pemesanan = $id_pemesanan");
    if ($qKursi) {
        while ($k = $qKursi->fetch_assoc()) {
            $kursiList[] = $k['kode_kursi'];
        }
    }

    if (!empty($kursiList)) {
        $stmtKursi = $conn->prepare("UPDATE kursi SET status='tersedia' WHERE id_jadwal = ? AND kode_kursi = ?");
        if (!$stmtKursi) throw new Exception("Prepare kursi gagal: " . $conn->error);

        foreach ($kursiList as $kode) {
            $stmtKursi->bind_param("is", $id_jadwal, $kode);
            $stmtKursi->execute();
        }
        $stmtKursi->close();
    }

    // ===== 3. Notifikasi ke user =====
    $judul_notif = "Refund Disetujui";
    $pesan = "Refund Anda untuk film '{$judul_film}' sebesar Rp {$total} telah DISETUJUI oleh kasir. "
           . "Dana akan dikembalikan ke {$labelMetode} Anda dalam waktu maksimal 7 hari kerja.";

    $stmtN = $conn->prepare("INSERT INTO notifikasi (id_user, judul, pesan, tipe, is_read, created_at) VALUES (?, ?, ?, 'refund', 0, NOW())");
    if (!$stmtN) throw new Exception("Prepare notifikasi gagal: " . $conn->error);
    $stmtN->bind_param("iss", $user_id, $judul_notif, $pesan);
    if (!$stmtN->execute()) throw new Exception("Insert notifikasi gagal: " . $stmtN->error);
    $stmtN->close();

    $conn->commit();

    $jumlahKursi = count($kursiList);
    $_SESSION['notif_kasir'] = "Refund Rp {$total} untuk '{$judul_film}' (a/n {$order['user_nama']}) disetujui. "
                             . "{$jumlahKursi} kursi dibebaskan. Dana akan masuk ke {$labelMetode} pengguna.";
} catch (Exception $e) {
    $conn->rollback();
    error_log('[Refund Error] ' . $e->getMessage());
    $_SESSION['error_kasir'] = "Gagal memproses refund: " . $e->getMessage();
}

header('Location: dashboard.php');
exit;