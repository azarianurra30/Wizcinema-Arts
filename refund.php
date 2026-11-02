<?php
session_start();
include 'config.php';

// ===== DEBUG SEMENTARA — hapus setelah berhasil =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
// =====================================================

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$id_pemesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_user = (int)$_SESSION['id_user'];

if (!$id_pemesanan) {
    $_SESSION['error'] = "Data pemesanan tidak ditemukan.";
    header('Location: dashboard.php');
    exit;
}

$order = $conn->query("
    SELECT p.*, f.judul, f.poster, f.genre, 
           j.tanggal, j.jam, j.studio, 
           COALESCE(m.nama_mall, '-') AS nama_mall
    FROM pemesanan p
    JOIN jadwal j ON p.id_jadwal = j.id_jadwal
    JOIN film f ON j.id_film = f.id_film
    LEFT JOIN mall m ON j.id_mall = m.id_mall
    WHERE p.id_pemesanan = $id_pemesanan AND p.id_user = $id_user
    LIMIT 1
")->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = "Pemesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: dashboard.php');
    exit;
}

$jadwal_dt = strtotime($order['tanggal'] . ' ' . $order['jam']);

if (time() >= $jadwal_dt) {
    $_SESSION['error'] = "Refund tidak bisa dilakukan — film sudah mulai/selesai tayang.";
    header('Location: dashboard.php');
    exit;
}

if ($order['status'] !== 'Lunas') {
    $_SESSION['error'] = "Hanya pesanan berstatus Lunas yang bisa direfund. Status saat ini: {$order['status']}.";
    header('Location: dashboard.php');
    exit;
}

$kursiList = [];
$k = $conn->query("SELECT kode_kursi FROM detail_pemesanan WHERE id_pemesanan = $id_pemesanan ORDER BY kode_kursi");
while ($r = $k->fetch_assoc()) $kursiList[] = $r['kode_kursi'];
$kursiStr = implode(', ', $kursiList);

$metode = strtoupper($order['metode'] ?? 'Cash');
$labelMetode = $metode;
if (in_array(strtolower($metode), ['dana','gopay','shopeepay'])) {
    $labelMetode = "akun " . $metode;
} elseif (in_array(strtolower($metode), ['bca','mandiri','bri','bsi'])) {
    $labelMetode = "rekening " . $metode;
} else {
    $labelMetode = "tunai di kasir";
}

// ===== PROSES REFUND =====
if (isset($_POST['konfirmasi_refund'])) {
    $conn->begin_transaction();
    try {
        // Update status ke Batal
        $stmt = $conn->prepare("UPDATE pemesanan SET status='Batal' WHERE id_pemesanan=? AND id_user=?");
        $stmt->bind_param("ii", $id_pemesanan, $id_user);
        if (!$stmt->execute()) {
            throw new Exception("Execute gagal: " . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            // Coba cek status sekarang
            $cek = $conn->query("SELECT status FROM pemesanan WHERE id_pemesanan=$id_pemesanan AND id_user=$id_user")->fetch_assoc();
            if (!$cek) {
                throw new Exception("Pesanan tidak ditemukan di database.");
            }
            throw new Exception("Status tidak berubah. Status saat ini: " . $cek['status'] . ". Kemungkinan sudah pernah direfund.");
        }

        // Cek apakah ada kasir
        $kasirList = $conn->query("SELECT id_user, nama FROM users WHERE role='kasir'");
        $jumlahKasir = $kasirList ? $kasirList->num_rows : 0;

        if ($jumlahKasir === 0) {
            // Tidak ada kasir → tetap lanjutkan refund, tapi beri warning
            $conn->commit();
            $_SESSION['message'] = "Permintaan refund berhasil dicatat. Namun BELUM ADA KASIR di sistem, mohon hubungi admin untuk proses pengembalian dana Rp " . number_format($order['total_harga'], 0, ',', '.') . " ke {$labelMetode} Anda.";
            header('Location: dashboard.php');
            exit;
        }

        // Notifikasi ke SEMUA kasir
        $amount = number_format($order['total_harga'], 0, ',', '.');
        $judul_notif = "Permintaan Refund Baru";
        $pesan = "Pengguna {$order['nama_pemesan']} meminta refund tiket film '{$order['judul']}' sebesar Rp {$amount} untuk dikembalikan ke {$labelMetode}. Buka dashboard untuk menyetujui.";

        $stmtN = $conn->prepare("INSERT INTO notifikasi (id_user, judul, pesan, tipe, is_read, created_at) VALUES (?, ?, ?, 'refund_request', 0, NOW())");

        $kasirList->data_seek(0);
        while ($k = $kasirList->fetch_assoc()) {
            $kid = (int)$k['id_user'];
            $stmtN->bind_param("iss", $kid, $judul_notif, $pesan);
            if (!$stmtN->execute()) {
                throw new Exception("Gagal insert notifikasi untuk kasir #$kid: " . $stmtN->error);
            }
        }
        $stmtN->close();

        $conn->commit();
        $_SESSION['message'] = "Permintaan refund berhasil dikirim ke {$jumlahKasir} kasir. Dana akan dikembalikan ke {$labelMetode} Anda setelah disetujui.";
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal memproses refund: " . $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Refund - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0B1120; color: #F1F5F9; min-height: 100vh; padding: 30px 15px 60px; }
        .wrap { max-width: 480px; margin: 0 auto; }
        .page-title { text-align: center; margin-bottom: 26px; }
        .page-title h1 { font-size: 1.5rem; font-weight: 800; color: #fff; }
        .page-title p { color: #64748B; font-size: 0.88rem; margin-top: 6px; }
        .card { background: #111A2E; border: 1px solid #1E293B; border-radius: 18px; padding: 22px; margin-bottom: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .card-title { font-size: 0.9rem; font-weight: 800; color: #fff; padding-bottom: 14px; margin-bottom: 16px; border-bottom: 1px solid #1E293B; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: #3B82F6; }
        .movie-info { display: flex; gap: 14px; }
        .movie-poster { width: 66px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid #1E293B; flex-shrink: 0; }
        .movie-detail { flex: 1; min-width: 0; }
        .movie-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 6px; line-height: 1.2; }
        .movie-meta { font-size: 0.78rem; color: #94A3B8; display: flex; flex-direction: column; gap: 5px; }
        .movie-meta span { display: flex; align-items: center; gap: 6px; }
        .movie-meta i { color: #3B82F6; width: 13px; text-align: center; }
        .refund-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #1E293B; font-size: 0.88rem; }
        .refund-row:last-child { border-bottom: none; padding-bottom: 0; }
        .refund-row:first-child { padding-top: 0; }
        .refund-row .lbl { color: #94A3B8; }
        .refund-row .val { font-weight: 700; color: #fff; }
        .refund-row .val.amount { color: #FCD34D; font-size: 1.05rem; }
        .refund-row .val.dest { color: #34D399; }
        .alert-warning { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; color: #FCD34D; font-size: 0.82rem; line-height: 1.6; display: flex; gap: 10px; align-items: flex-start; }
        .alert-warning i { flex-shrink: 0; margin-top: 2px; }
        .btn-primary-action { width: 100%; background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; border: none; border-radius: 14px; padding: 16px; font-size: 0.95rem; font-weight: 800; font-family: inherit; cursor: pointer; transition: 0.25s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 25px rgba(239,68,68,0.35); margin-bottom: 12px; }
        .btn-primary-action:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(239,68,68,0.5); }
        .btn-outline-action { width: 100%; background: transparent; color: #94A3B8; border: 1.5px solid #1E293B; border-radius: 14px; padding: 14px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
        .btn-outline-action:hover { background: #1E293B; color: #fff; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="page-title">
            <h1>Konfirmasi Refund</h1>
            <p>Periksa detail sebelum mengajukan pengembalian dana</p>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-ticket-alt"></i> Detail Pesanan</div>
            <div class="movie-info">
                <img src="assets/img/<?= htmlspecialchars($order['poster'] ?? 'default.jpg'); ?>" class="movie-poster" onerror="this.src='assets/img/default.jpg'" alt="Poster">
                <div class="movie-detail">
                    <div class="movie-title"><?= htmlspecialchars($order['judul']); ?></div>
                    <div class="movie-meta">
                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($order['nama_mall']); ?></span>
                        <span><i class="fas fa-couch"></i> Studio <?= htmlspecialchars($order['studio']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($order['tanggal'])); ?></span>
                        <span><i class="fas fa-clock"></i> <?= date('H:i', strtotime($order['jam'])); ?> WIB</span>
                        <span><i class="fas fa-chair"></i> Kursi: <?= htmlspecialchars($kursiStr); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-undo"></i> Detail Pengembalian</div>
            <div class="refund-row">
                <span class="lbl">Kode Booking</span>
                <span class="val"><?= htmlspecialchars($order['kode_booking']); ?></span>
            </div>
            <div class="refund-row">
                <span class="lbl">Jumlah Refund</span>
                <span class="val amount">Rp <?= number_format($order['total_harga'], 0, ',', '.'); ?></span>
            </div>
            <div class="refund-row">
                <span class="lbl">Dikembalikan ke</span>
                <span class="val dest"><?= htmlspecialchars($labelMetode); ?></span>
            </div>
            <div class="refund-row">
                <span class="lbl">Estimasi Proses</span>
                <span class="val">Maks. 7 hari kerja</span>
            </div>
        </div>

        <div class="alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Setelah Anda klik <strong>Ajukan Refund</strong>, kasir akan menerima notifikasi untuk menyetujui pengembalian dana. Dana akan dikirim ke <strong><?= htmlspecialchars($labelMetode); ?></strong> Anda.</span>
        </div>

        <form method="POST" onsubmit="return confirm('Yakin ingin mengajukan refund? Tindakan ini tidak dapat dibatalkan.');">
            <button type="submit" name="konfirmasi_refund" class="btn-primary-action">
                <i class="fas fa-paper-plane"></i> Ajukan Refund Sekarang
            </button>
            <a href="dashboard.php" class="btn-outline-action">
                <i class="fas fa-arrow-left"></i> Batal
            </a>
        </form>
    </div>
</body>
</html>