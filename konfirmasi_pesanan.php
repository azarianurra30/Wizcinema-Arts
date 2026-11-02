<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$id_jadwal = isset($_POST['id_jadwal']) ? (int)$_POST['id_jadwal'] : 0;
$selectedSeats = $_POST['selected_seats'] ?? '';
$seatArray = array_filter(array_map('trim', explode(',', $selectedSeats)));

if ($id_jadwal <= 0 || empty($seatArray)) {
    header('Location: pilih_kursi.php?id_jadwal=' . $id_jadwal);
    exit;
}

$jadwal = $conn->query("SELECT j.*, f.judul FROM jadwal j JOIN film f ON j.id_film = f.id_film WHERE j.id_jadwal = $id_jadwal")->fetch_assoc();
if (!$jadwal) {
    header('Location: index.php');
    exit;
}

$total_harga = count($seatArray) * $jadwal['harga'];
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_booking'])) {
    $metode = $conn->real_escape_string($_POST['metode'] ?? '');
    $nama_pemesan = $conn->real_escape_string($_POST['nama_pemesan'] ?? '');
    $nomor_pembayaran = $conn->real_escape_string($_POST['nomor_pembayaran'] ?? '');

    if (empty($metode)) $errorMsg = 'Pilih metode pembayaran dulu!';
    elseif (empty($nama_pemesan)) $errorMsg = 'Nama pemesan wajib diisi!';
    elseif (in_array($metode, ['dana','gopay','shopeepay','bca','mandiri','bri','bsi']) && empty($nomor_pembayaran)) {
        $errorMsg = 'Nomor pembayaran wajib diisi!';
    }

    if (empty($errorMsg)) {
        $kode_booking = 'BK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $status = 'Lunas';

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO pemesanan 
                (kode_booking, id_user, id_jadwal, total_harga, status, metode, nama_pemesan, nomor_pembayaran, tanggal_pesan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("siidssss", 
                $kode_booking, 
                $_SESSION['id_user'], 
                $id_jadwal, 
                $total_harga, 
                $status, 
                $metode, 
                $nama_pemesan, 
                $nomor_pembayaran
            );
            $stmt->execute();
            $id_pemesanan = $conn->insert_id;
            $stmt->close();

            // Insert kursi yang dipesan
            $stmtSeat = $conn->prepare("INSERT INTO detail_pemesanan (id_pemesanan, kode_kursi) VALUES (?, ?)");
            $stmtUpdate = $conn->prepare("UPDATE kursi SET status='dipesan' WHERE id_jadwal=? AND kode_kursi=?");
            
            foreach ($seatArray as $seat) {
                $stmtSeat->bind_param("is", $id_pemesanan, $seat);
                $stmtSeat->execute();
                $stmtUpdate->bind_param("is", $id_jadwal, $seat);
                $stmtUpdate->execute();
            }
            $stmtSeat->close();
            $stmtUpdate->close();

            $conn->commit();
            $_SESSION['notif_kasir'][] = [
                'kode'   => $kode_booking,
                'pesan'  => "Tiket baru! {$nama_pemesan} - {$jadwal['judul']} - " . count($seatArray) . " kursi",
                'waktu'  => date('Y-m-d H:i:s'),
            ];

            header("Location: konfirmasi_pesanan.php?sukses=1&kode=$kode_booking&id=$id_pemesanan");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Gagal: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top, rgba(30, 136, 229, 0.22), transparent 28%),
                radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.14), transparent 22%),
                linear-gradient(180deg, #050b16 0%, #0b1120 35%, #0f172a 100%);
            color: #F1F5F9;
            min-height: 100vh;
            padding: 30px 15px;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(120deg, rgba(15, 23, 42, 0.78), rgba(15, 23, 42, 0.3));
            pointer-events: none;
            z-index: 0;
        }
        .wrap, .success-wrap, form {
            position: relative;
            z-index: 1;
        }
        .wrap { max-width: 520px; margin: 0 auto; }

        .page-title { text-align: center; margin-bottom: 25px; }
        .page-title h1 { font-size: 1.4rem; font-weight: 700; color: #fff; }
        .page-title p { color: #64748B; font-size: 0.85rem; margin-top: 4px; }

        .card { background: #111A2E; border: 1px solid #1f2937; border-radius: 14px; padding: 24px; margin-bottom: 16px; }

        .info-list { display: flex; flex-direction: column; gap: 12px; }
        .info-item { display: flex; justify-content: space-between; align-items: center; gap: 15px; font-size: 0.88rem; }
        .info-item .label { color: #94a3b8; display: flex; align-items: center; gap: 8px; }
        .info-item .label i { color: #1E88E5; width: 14px; font-size: 0.85rem; }
        .info-item .value { color: #fff; font-weight: 600; text-align: right; max-width: 60%; word-break: break-word; }
        .info-item .value.seats { color: #64B5F6; }

        .total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 16px; border-top: 1px solid #1f2937; }
        .total-row .label { color: #94a3b8; font-size: 0.85rem; font-weight: 600; }
        .total-row .value { font-size: 1.3rem; font-weight: 800; color: #64B5F6; }

        .section-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .section-label i { color: #1E88E5; }

        .metode-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .metode-item { position: relative; }
        .metode-item input { display: none; }
        .metode-item label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 6px;
            background: #0B1120;
            border: 1.5px solid #1f2937;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 78px;
            position: relative;
        }
        .metode-item label:hover { border-color: #374151; transform: translateY(-2px); }
        .metode-item input:checked + label {
            border-color: #1E88E5;
            background: #0f1f33;
            box-shadow: 0 0 0 3px rgba(30,136,229,0.15);
        }
        .metode-item input:checked + label::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 5px; right: 5px;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: #1E88E5;
            color: #fff;
            font-size: 0.55rem;
            display: flex; align-items: center; justify-content: center;
        }
        .metode-logo {
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.55rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.3px;
        }
        .metode-logo.cash { background: linear-gradient(135deg, #16a34a, #15803d); }
        .metode-logo.dana { background: linear-gradient(135deg, #118EEA, #0F6FBF); }
        .metode-logo.gopay { background: linear-gradient(135deg, #00AED6, #0089AB); }
        .metode-logo.shopeepay { background: linear-gradient(135deg, #EE4D2D, #C43A1E); }
        .metode-logo.bca { background: linear-gradient(135deg, #0060AF, #004080); }
        .metode-logo.mandiri { background: linear-gradient(135deg, #003D79, #F7A800); }
        .metode-logo.bri { background: linear-gradient(135deg, #00529C, #003A6E); }
        .metode-logo.bsi { background: linear-gradient(135deg, #00A39D, #00766F); }
        .metode-name { font-size: 0.7rem; font-weight: 700; color: #e5e7eb; text-align: center; }

        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #94a3b8; margin-bottom: 8px; }
        .form-label i { color: #1E88E5; margin-right: 6px; }
        .form-input {
            width: 100%;
            background: #0B1120;
            border: 1.5px solid #1f2937;
            color: #fff;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus { border-color: #1E88E5; box-shadow: 0 0 0 3px rgba(30,136,229,0.15); }

        .alert-error {
            background: rgba(220,53,69,0.1);
            border: 1px solid rgba(220,53,69,0.3);
            color: #ff8a9a;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary-full {
            width: 100%;
            background: linear-gradient(135deg, #1E88E5, #1565C0);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
            box-shadow: 0 8px 22px rgba(30,136,229,0.35);
        }
        .btn-primary-full:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(30,136,229,0.5); color: #fff; }

        .btn-outline-full {
            width: 100%;
            background: transparent;
            color: #94a3b8;
            border: 1.5px solid #1f2937;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            transition: 0.2s;
        }
        .btn-outline-full:hover { background: #131a2a; color: #fff; border-color: #374151; }

        .hidden { display: none !important; }

        /* Success page */
        .success-wrap {
            max-width: 480px;
            margin: 60px auto;
            background: #111A2E;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
        }
        .success-icon {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10B981, #059669);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: #fff;
            box-shadow: 0 10px 30px rgba(16,185,129,0.4);
        }
        .success-wrap h2 { font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
        .success-wrap .sub { color: #94a3b8; font-size: 0.85rem; margin-bottom: 24px; }
        .kode-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 8px; }
        .kode-value {
            background: #0B1120;
            border: 1px dashed #1E88E5;
            border-radius: 10px;
            padding: 14px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #64B5F6;
            letter-spacing: 2px;
            margin-bottom: 24px;
        }

        @media (max-width: 480px) {
            .card { padding: 18px; }
            .info-item { font-size: 0.82rem; }
            .metode-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php if(isset($_GET['sukses'])): ?>
    <div class="success-wrap">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h2>Pembayaran Berhasil</h2>
        <p class="sub">Tiket Anda sudah terkonfirmasi</p>
        <div class="kode-label">Kode Booking</div>
        <div class="kode-value"><?= htmlspecialchars($_GET['kode']); ?></div>
        <a href="barcode.php?id=<?= (int)$_GET['id']; ?>" class="btn-primary-full">
            <i class="fas fa-qrcode"></i> Lihat Barcode Tiket
        </a>
        <a href="dashboard.php" class="btn-outline-full">
            <i class="fas fa-home"></i> Kembali ke Dashboard
        </a>
    </div>
<?php else: ?>
    <div class="wrap">
        <div class="page-title">
            <h1>Konfirmasi Pembayaran</h1>
            <p>Lengkapi data untuk menyelesaikan pemesanan</p>
        </div>

        <?php if(!empty($errorMsg)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <!-- Info Pesanan -->
        <div class="card">
            <div class="info-list">
                <div class="info-item">
                    <span class="label"><i class="fas fa-film"></i> Film</span>
                    <span class="value"><?= htmlspecialchars($jadwal['judul']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-calendar"></i> Jadwal</span>
                    <span class="value"><?= date('d M Y', strtotime($jadwal['tanggal'])); ?>, <?= date('H:i', strtotime($jadwal['jam'])); ?> WIB</span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-couch"></i> Kursi</span>
                    <span class="value seats"><?= htmlspecialchars(implode(', ', $seatArray)); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-ticket-alt"></i> Jumlah</span>
                    <span class="value"><?= count($seatArray); ?> tiket</span>
                </div>
            </div>
            <div class="total-row">
                <span class="label">Total Bayar</span>
                <span class="value">Rp <?= number_format($total_harga, 0, ',', '.'); ?></span>
            </div>
        </div>

        <form method="POST" id="formBayar">
            <input type="hidden" name="id_jadwal" value="<?= $id_jadwal; ?>">
            <input type="hidden" name="selected_seats" value="<?= htmlspecialchars($selectedSeats); ?>">

            <div class="card">
                <div class="section-label"><i class="fas fa-wallet"></i> Metode Pembayaran</div>
                <div class="metode-grid">

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-cash" value="Cash" checked onchange="pilihMetode('Cash')">
                        <label for="m-cash">
                            <div class="metode-logo cash">CASH</div>
                            <div class="metode-name">Cash</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-dana" value="dana" onchange="pilihMetode('dana')">
                        <label for="m-dana">
                            <div class="metode-logo dana">DANA</div>
                            <div class="metode-name">DANA</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-gopay" value="gopay" onchange="pilihMetode('gopay')">
                        <label for="m-gopay">
                            <div class="metode-logo gopay">GOPAY</div>
                            <div class="metode-name">GoPay</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-shopeepay" value="shopeepay" onchange="pilihMetode('shopeepay')">
                        <label for="m-shopeepay">
                            <div class="metode-logo shopeepay">SPAY</div>
                            <div class="metode-name">ShopeePay</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-bca" value="bca" onchange="pilihMetode('bca')">
                        <label for="m-bca">
                            <div class="metode-logo bca">BCA</div>
                            <div class="metode-name">BCA</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-mandiri" value="mandiri" onchange="pilihMetode('mandiri')">
                        <label for="m-mandiri">
                            <div class="metode-logo mandiri">MDR</div>
                            <div class="metode-name">Mandiri</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-bri" value="bri" onchange="pilihMetode('bri')">
                        <label for="m-bri">
                            <div class="metode-logo bri">BRI</div>
                            <div class="metode-name">BRI</div>
                        </label>
                    </div>

                    <div class="metode-item">
                        <input type="radio" name="metode" id="m-bsi" value="bsi" onchange="pilihMetode('bsi')">
                        <label for="m-bsi">
                            <div class="metode-logo bsi">BSI</div>
                            <div class="metode-name">BSI</div>
                        </label>
                    </div>

                </div>

                <div id="fieldNomor" class="form-group hidden" style="margin-top:18px;">
                    <label class="form-label" id="labelNomor"><i class="fas fa-mobile-alt"></i> Nomor HP</label>
                    <input type="text" name="nomor_pembayaran" id="inputNomor" class="form-input" placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group" style="margin-top:18px;">
                    <label class="form-label"><i class="fas fa-user"></i> Nama Pemesan</label>
                    <input type="text" name="nama_pemesan" class="form-input" placeholder="Masukkan nama lengkap" required value="<?= htmlspecialchars($_SESSION['nama'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" name="complete_booking" class="btn-primary-full">
                <i class="fas fa-lock"></i> Bayar Sekarang
            </button>
            <a href="pilih_kursi.php?id_jadwal=<?= $id_jadwal; ?>" class="btn-outline-full">
                <i class="fas fa-arrow-left"></i> Ubah Kursi
            </a>
        </form>
    </div>

    <script>
        function pilihMetode(val) {
            var fieldNomor = document.getElementById('fieldNomor');
            var labelNomor = document.getElementById('labelNomor');
            var inputNomor = document.getElementById('inputNomor');

            var ewallet = ['dana', 'gopay', 'shopeepay'];
            var bank = ['bca', 'mandiri', 'bri', 'bsi'];

            if (ewallet.includes(val)) {
                fieldNomor.classList.remove('hidden');
                labelNomor.innerHTML = '<i class="fas fa-mobile-alt"></i> Nomor HP';
                inputNomor.placeholder = '08xxxxxxxxxx';
                inputNomor.required = true;
                inputNomor.value = '';
            } else if (bank.includes(val)) {
                fieldNomor.classList.remove('hidden');
                labelNomor.innerHTML = '<i class="fas fa-university"></i> Nomor Rekening';
                inputNomor.placeholder = '1234567890';
                inputNomor.required = true;
                inputNomor.value = '';
            } else {
                fieldNomor.classList.add('hidden');
                inputNomor.required = false;
                inputNomor.value = '';
            }
        }
    </script>
<?php endif; ?>
</body>
</html>