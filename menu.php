<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$isUser = ($role === 'user');
$id_user = (int)$_SESSION['id_user'];

// Auto-tambah kolom pesanan_menu
$kolomPesanan = [
    'kode_pesanan'      => "VARCHAR(50) DEFAULT NULL",
    'nama_pemesan'      => "VARCHAR(100) DEFAULT NULL",
    'total_harga'       => "INT DEFAULT 0",
    'metode_pembayaran' => "VARCHAR(50) DEFAULT 'Cash'",
    'nomor_pembayaran'  => "VARCHAR(50) DEFAULT NULL",
    'status'            => "VARCHAR(50) DEFAULT 'Pending'",
    'catatan'           => "TEXT DEFAULT NULL",
    'created_at'        => "DATETIME DEFAULT CURRENT_TIMESTAMP",
];
foreach ($kolomPesanan as $namaKolom => $tipe) {
    $cek = $conn->query("SHOW COLUMNS FROM pesanan_menu LIKE '$namaKolom'");
    if ($cek && $cek->num_rows == 0) {
        $conn->query("ALTER TABLE pesanan_menu ADD COLUMN $namaKolom $tipe");
    }
}

$kolomDetail = [
    'id_menu'       => "INT DEFAULT NULL",
    'nama_item'     => "VARCHAR(100) DEFAULT NULL",
    'harga'         => "INT DEFAULT 0",
    'jumlah'        => "INT DEFAULT 1",
    'subtotal'      => "INT DEFAULT 0",
    'opsi_tambahan' => "TEXT DEFAULT NULL",
];
foreach ($kolomDetail as $namaKolom => $tipe) {
    $cek = $conn->query("SHOW COLUMNS FROM detail_pesanan_menu LIKE '$namaKolom'");
    if ($cek && $cek->num_rows == 0) {
        $conn->query("ALTER TABLE detail_pesanan_menu ADD COLUMN $namaKolom $tipe");
    }
}

$conn->query("ALTER TABLE detail_pesanan_menu MODIFY COLUMN id_menu INT DEFAULT NULL");
$conn->query("ALTER TABLE detail_pesanan_menu MODIFY COLUMN harga INT DEFAULT 0");
$conn->query("ALTER TABLE pesanan_menu MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");

// Proses pemesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi_pesan']) && $isUser) {
    $id_menu = (int)$_POST['id_menu'];
    $jumlah = max(1, (int)$_POST['jumlah']);
    $catatan = $conn->real_escape_string($_POST['catatan'] ?? '');
    $metode = $conn->real_escape_string($_POST['metode'] ?? 'Cash');
    $nomor_pembayaran = $conn->real_escape_string($_POST['nomor_pembayaran'] ?? '');
    $nama_pemesan = $conn->real_escape_string($_POST['nama_pemesan'] ?? ($_SESSION['nama'] ?? 'User'));

    $menu = $conn->query("SELECT * FROM menu WHERE id_menu = $id_menu")->fetch_assoc();
    if ($menu) {
        $hargaSatuan = (int)$menu['harga'];
        $subtotal = $hargaSatuan * $jumlah;
        $kode = 'MN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $stmt = $conn->prepare("INSERT INTO pesanan_menu (id_user, kode_pesanan, nama_pemesan, total_harga, metode_pembayaran, nomor_pembayaran, status, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())");
        $stmt->bind_param("ississs", $id_user, $kode, $nama_pemesan, $subtotal, $metode, $nomor_pembayaran, $catatan);
        $stmt->execute();
        $id_pesanan = $conn->insert_id;
        $stmt->close();

        $stmt2 = $conn->prepare("INSERT INTO detail_pesanan_menu (id_pesanan_menu, id_menu, nama_item, harga, jumlah, subtotal, opsi_tambahan) VALUES (?, ?, ?, ?, ?, ?, '[]')");
        $stmt2->bind_param("iisiii", $id_pesanan, $id_menu, $menu['nama_menu'], $hargaSatuan, $jumlah, $subtotal);
        $stmt2->execute();
        $stmt2->close();

        header("Location: struk_menu.php?id=$id_pesanan");
        exit;
    }
}

$menus = $conn->query("SELECT * FROM menu ORDER BY kategori, nama_menu");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Resto - WizCinema</title>
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
            letter-spacing: -0.4px;
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
            color: #94A3B8; font-size: 0.88rem;
            margin-top: 4px;
        }

        /* ===== MENU GRID (UKURAN DIKEMBALIKAN KE SEMULA) ===== */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Ukuran kecil standar */
            gap: 18px;
        }
        .menu-card {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .menu-card:hover {
            border-color: #10B981;
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(16,185,129,0.15);
        }
        .menu-card img {
            width: 100%;
            height: 150px; /* Tinggi gambar ringkas */
            object-fit: cover;
            background: #0B1120;
            display: block;
        }
        .menu-card .info { padding: 14px 16px; }
        .menu-card .info .kategori {
            color: #64748B;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .menu-card .info .kategori i { color: #10B981; }
        .menu-card .info h6 {
            font-weight: 700;
            font-size: 0.92rem;
            color: #fff;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .menu-card .info .harga {
            color: #FCD34D;
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 12px;
        }
        .btn-pesan {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            width: 100%;
            transition: all 0.25s;
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-pesan:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16,185,129,0.3);
        }
        .btn-pesan:disabled {
            background: #1E293B;
            color: #94A3B8;
            cursor: not-allowed;
        }

        /* =========================================================
           DESAIN MODAL KONFIRMASI PESANAN (SANGAT PREMIUM & MENARIK)
           ========================================================= */
        #modalPesan .modal-dialog { max-width: 460px; margin: 1.75rem auto; }
        
        #modalPesan .modal-content {
            background: #0F1729;
            border: 1px solid #1E293B;
            border-radius: 24px;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
        }
        
        /* Modal Header */
        #modalPesan .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #1E293B;
            background: linear-gradient(to right, #111A2E, #0F1729);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        #modalPesan .header-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: rgba(16,185,129,0.15);
            color: #10B981;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            border: 1px solid rgba(16,185,129,0.3);
        }
        #modalPesan .header-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
            letter-spacing: -0.3px;
        }
        #modalPesan .header-sub {
            font-size: 0.75rem;
            color: #64748B;
            margin: 2px 0 0 0;
        }
        #modalPesan .btn-close {
            margin-left: auto;
            filter: invert(1) brightness(2);
            opacity: 0.5;
            transition: 0.2s;
        }
        #modalPesan .btn-close:hover { opacity: 1; }

        /* Modal Body */
        #modalPesan .modal-body {
            padding: 24px;
            max-height: 65vh;
            overflow-y: auto;
        }
        #modalPesan .modal-body::-webkit-scrollbar { width: 5px; }
        #modalPesan .modal-body::-webkit-scrollbar-thumb { background: #1E293B; border-radius: 10px; }

        /* Item Preview (Kartu Menu Terpilih) */
        .item-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #0B1120;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .item-preview::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: #10B981;
        }
        .item-preview img {
            width: 60px; height: 60px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .item-name { font-weight: 800; font-size: 1rem; color: #fff; margin-bottom: 4px; line-height: 1.2; }
        .item-price { font-weight: 700; font-size: 0.9rem; color: #FCD34D; }

        /* Form Elements */
        .form-group-custom { margin-bottom: 20px; }
        .form-label-custom {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #94A3B8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-label-custom i { color: #10B981; margin-right: 4px; }
        
        .form-input-custom {
            width: 100%;
            background: #0B1120;
            border: 1.5px solid #1E293B;
            color: #fff;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.9rem;
            outline: none;
            transition: 0.25s;
            font-family: inherit;
        }
        .form-input-custom:focus {
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
        }
        .form-input-custom::placeholder { color: #475569; }

        /* Quantity Control */
        .qty-control {
            display: flex;
            align-items: center;
            background: #0B1120;
            border: 1.5px solid #1E293B;
            border-radius: 12px;
            height: 48px;
            overflow: hidden;
        }
        .qty-btn {
            width: 50px;
            height: 100%;
            background: transparent;
            border: none;
            color: #10B981;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .qty-btn:hover { background: #1E293B; }
        .qty-input {
            flex: 1;
            height: 100%;
            background: transparent;
            border: none;
            border-left: 1px solid #1E293B;
            border-right: 1px solid #1E293B;
            color: #fff;
            text-align: center;
            font-weight: 800;
            font-size: 1rem;
            outline: none;
            font-family: inherit;
        }
        /* Hide arrows in input number */
        .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        /* Payment Grid */
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .payment-item { position: relative; }
        .payment-item input { display: none; }
        .payment-item label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 10px 4px;
            background: #0B1120;
            border: 1.5px solid #1E293B;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
            min-height: 64px;
        }
        .payment-item label:hover { border-color: #334155; }
        .payment-item input:checked + label {
            border-color: #10B981;
            background: rgba(16,185,129,0.1);
            box-shadow: 0 0 0 2px rgba(16,185,129,0.2);
        }
        .payment-logo {
            width: 32px; height: 32px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff;
        }
        .payment-item label span {
            font-size: 0.65rem;
            font-weight: 700;
            color: #CBD5E1;
        }

        /* Total Box */
        .total-box {
            background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
            border: 1.5px solid rgba(251,191,36,0.3);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
        }
        .total-box .lbl { color: #FCD34D; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .total-box .val { color: #fff; font-weight: 800; font-size: 1.5rem; text-shadow: 0 2px 10px rgba(251,191,36,0.3); }

        /* Modal Footer */
        #modalPesan .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #1E293B;
            background: #0F1729;
            display: flex;
            gap: 12px;
        }
        #modalPesan .btn-batal {
            flex: 1;
            padding: 14px;
            background: transparent;
            border: 1.5px solid #1E293B;
            color: #94A3B8;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            font-family: inherit;
            cursor: pointer;
        }
        #modalPesan .btn-batal:hover { background: #1E293B; color: #fff; }
        
        #modalPesan .btn-konfirmasi {
            flex: 2;
            background: linear-gradient(135deg, #10B981, #059669);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 14px;
            font-weight: 800;
            font-size: 0.95rem;
            transition: 0.25s;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 8px 20px rgba(16,185,129,0.3);
        }
        #modalPesan .btn-konfirmasi:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(16,185,129,0.45);
        }

        .hidden { display: none !important; }

        @media (max-width:900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .menu-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .menu-card img { height: 130px; }
            .menu-card .info { padding: 12px; }
            .payment-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width:480px) {
            .menu-grid { gap: 10px; }
            .menu-card img { height: 110px; }
            .page-header h4 { font-size: 1.3rem; }
            #modalPesan .modal-body { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h4><i class="fas fa-utensils"></i> Menu Resto</h4>
                <div class="sub">Pesan makanan & minuman favoritmu</div>
            </div>
        </div>

        <div class="menu-grid">
            <?php if ($menus && $menus->num_rows > 0): ?>
                <?php while($m = $menus->fetch_assoc()): 
                    $gambarMenu = !empty($m['gambar']) ? $m['gambar'] : 'default.jpg';
                    $kategoriIcon = ($m['kategori'] === 'minuman') ? 'fa-mug-hot' : 'fa-utensils';
                ?>
                <div class="menu-card">
                    <img src="assets/img/<?= htmlspecialchars($gambarMenu); ?>" onerror="this.src='assets/img/default.jpg'" alt="<?= htmlspecialchars($m['nama_menu']); ?>">
                    <div class="info">
                        <div class="kategori"><i class="fas <?= $kategoriIcon; ?>"></i> <?= htmlspecialchars($m['kategori'] ?? '-'); ?></div>
                        <h6><?= htmlspecialchars($m['nama_menu']); ?></h6>
                        <div class="harga">Rp <?= number_format($m['harga'] ?? 0, 0, ',', '.'); ?></div>
                        
                        <?php if($isUser): ?>
                            <button type="button" class="btn-pesan"
                                data-id="<?= $m['id_menu']; ?>"
                                data-nama="<?= htmlspecialchars($m['nama_menu']); ?>"
                                data-harga="<?= (int)$m['harga']; ?>"
                                data-gambar="<?= htmlspecialchars($gambarMenu); ?>"
                                onclick="bukaModal(this)">
                                <i class="fas fa-cart-plus"></i> Pesan
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-pesan" disabled>
                                <i class="fas fa-lock"></i> Hanya User
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #64748B;">
                    <i class="fas fa-utensils" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                    Belum ada menu tersedia saat ini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($isUser): ?>
    <!-- MODAL KONFIRMASI PESANAN -->
    <div class="modal fade" id="modalPesan" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="formPesan">
                    
                    <!-- Header -->
                    <div class="modal-header">
                        <div class="header-icon"><i class="fas fa-shopping-basket"></i></div>
                        <div>
                            <h5 class="header-title">Konfirmasi Pesanan</h5>
                            <p class="header-sub">Atur jumlah dan metode pembayaran</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <input type="hidden" name="id_menu" id="modalIdMenu">
                        <input type="hidden" name="jumlah" id="modalJumlahHidden">

                        <!-- Item Preview -->
                        <div class="item-preview">
                            <img id="modalGambar" src="assets/img/default.jpg" onerror="this.src='assets/img/default.jpg'" alt="Menu">
                            <div>
                                <div class="item-name" id="modalNama">-</div>
                                <div class="item-price" id="modalHarga">Rp 0</div>
                            </div>
                        </div>

                        <!-- Qty -->
                        <div class="form-group-custom">
                            <label class="form-label-custom"><i class="fas fa-sort-numeric-up"></i> Jumlah Pesanan</label>
                            <div class="qty-control">
                                <button type="button" class="qty-btn" onclick="ubahJumlah(-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" id="modalJumlah" class="qty-input" value="1" min="1" max="99" oninput="hitungTotal()">
                                <button type="button" class="qty-btn" onclick="ubahJumlah(1)"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>

                        <!-- Nama Pemesan -->
                        <div class="form-group-custom">
                            <label class="form-label-custom"><i class="fas fa-user"></i> Nama Pemesan</label>
                            <input type="text" name="nama_pemesan" class="form-input-custom" value="<?= htmlspecialchars($_SESSION['nama'] ?? ''); ?>" placeholder="Masukkan nama" required>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div class="form-group-custom">
                            <label class="form-label-custom"><i class="fas fa-wallet"></i> Metode Pembayaran</label>
                            <div class="payment-grid">
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-cash" value="Cash" checked onchange="cekMetode()">
                                    <label for="m-cash"><div class="payment-logo" style="background:#16a34a;"><i class="fas fa-money-bill-wave"></i></div><span>Cash</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-dana" value="dana" onchange="cekMetode()">
                                    <label for="m-dana"><div class="payment-logo" style="background:#118EEA;"><i class="fas fa-wallet"></i></div><span>DANA</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-gopay" value="gopay" onchange="cekMetode()">
                                    <label for="m-gopay"><div class="payment-logo" style="background:#00AED6;"><i class="fas fa-wallet"></i></div><span>GoPay</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-shopeepay" value="shopeepay" onchange="cekMetode()">
                                    <label for="m-shopeepay"><div class="payment-logo" style="background:#EE4D2D;"><i class="fas fa-wallet"></i></div><span>SPay</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-bca" value="bca" onchange="cekMetode()">
                                    <label for="m-bca"><div class="payment-logo" style="background:#0060AF;"><i class="fas fa-university"></i></div><span>BCA</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-mandiri" value="mandiri" onchange="cekMetode()">
                                    <label for="m-mandiri"><div class="payment-logo" style="background:#003D79;"><i class="fas fa-university"></i></div><span>Mandiri</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-bri" value="bri" onchange="cekMetode()">
                                    <label for="m-bri"><div class="payment-logo" style="background:#00529C;"><i class="fas fa-university"></i></div><span>BRI</span></label>
                                </div>
                                <div class="payment-item">
                                    <input type="radio" name="metode" id="m-bsi" value="bsi" onchange="cekMetode()">
                                    <label for="m-bsi"><div class="payment-logo" style="background:#00A39D;"><i class="fas fa-university"></i></div><span>BSI</span></label>
                                </div>
                            </div>
                        </div>

                        <!-- Nomor Pembayaran (Dinamis) -->
                        <div id="nomor-field" class="form-group-custom hidden">
                            <label class="form-label-custom" id="nomor-label"><i class="fas fa-mobile-alt"></i> Nomor HP</label>
                            <input type="text" name="nomor_pembayaran" id="inputNomor" class="form-input-custom" placeholder="08xxxxxxxxxx">
                        </div>

                        <!-- Catatan -->
                        <div class="form-group-custom">
                            <label class="form-label-custom"><i class="fas fa-comment-dots"></i> Catatan <span style="color:#475569; font-weight:400; text-transform:none;">(Opsional)</span></label>
                            <input type="text" name="catatan" class="form-input-custom" placeholder="Contoh: Tanpa es, extra pedas...">
                        </div>

                        <!-- Total -->
                        <div class="total-box">
                            <span class="lbl">Total Bayar</span>
                            <span class="val" id="modalTotal">Rp 0</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn-batal" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="konfirmasi_pesan" class="btn-konfirmasi">Konfirmasi Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if($isUser): ?>
    <script>
        let hargaSatuan = 0;
        function bukaModal(btn) {
            const id = btn.dataset.id;
            const nama = btn.dataset.nama;
            const gambar = btn.dataset.gambar;
            hargaSatuan = parseInt(btn.dataset.harga);
            
            document.getElementById('modalIdMenu').value = id;
            document.getElementById('modalNama').textContent = nama;
            document.getElementById('modalHarga').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(hargaSatuan);
            document.getElementById('modalGambar').src = 'assets/img/' + gambar;
            
            document.getElementById('modalJumlah').value = 1;
            document.getElementById('m-cash').checked = true;
            document.getElementById('inputNomor').value = '';
            
            cekMetode();
            hitungTotal();
            new bootstrap.Modal(document.getElementById('modalPesan')).show();
        }
        
        function ubahJumlah(delta) {
            const input = document.getElementById('modalJumlah');
            let val = parseInt(input.value) || 1;
            val = Math.max(1, Math.min(99, val + delta));
            input.value = val;
            hitungTotal();
        }
        
        function hitungTotal() {
            const input = document.getElementById('modalJumlah');
            let jumlah = parseInt(input.value) || 1;
            if (jumlah < 1) jumlah = 1;
            if (jumlah > 99) jumlah = 99;
            
            input.value = jumlah;
            document.getElementById('modalJumlahHidden').value = jumlah;
            
            const total = hargaSatuan * jumlah;
            document.getElementById('modalTotal').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
        }
        
        function cekMetode() {
            const metode = document.querySelector('input[name="metode"]:checked').value;
            const field = document.getElementById('nomor-field');
            const label = document.getElementById('nomor-label');
            const input = document.getElementById('inputNomor');
            
            const ewallet = ['dana', 'gopay', 'shopeepay'];
            const bank = ['bca', 'mandiri', 'bri', 'bsi'];

            if (ewallet.includes(metode)) {
                field.classList.remove('hidden');
                label.innerHTML = '<i class="fas fa-mobile-alt"></i> Nomor HP';
                input.placeholder = '08xxxxxxxxxx';
                input.required = true;
            } else if (bank.includes(metode)) {
                field.classList.remove('hidden');
                label.innerHTML = '<i class="fas fa-university"></i> Nomor Rekening';
                input.placeholder = '1234567890';
                input.required = true;
            } else {
                field.classList.add('hidden');
                input.required = false;
                input.value = '';
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>