<?php
// ===== Safety: pastikan session aktif =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loggedIn = isset($_SESSION['id_user']);
$nama     = $_SESSION['nama'] ?? 'User';
$username = $_SESSION['username'] ?? '';
$foto     = $_SESSION['foto'] ?? 'default.png';
$bio      = $_SESSION['bio'] ?? '';
$role     = $_SESSION['role'] ?? '';

// ===== PERBAIKAN #1: Whitelist role =====
$validRoles = ['user', 'admin', 'kasir'];
if (!$loggedIn) {
    $role = '';
} elseif (!in_array($role, $validRoles, true)) {
    // Role tidak dikenal → fallback ke 'user' (jangan tampilkan menu guest)
    $role = 'user';
}

// ===== PERBAIKAN #2: Sanitasi foto (cegah path traversal) =====
$foto = basename((string)$foto);
$fotoExt = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
$allowedFotoExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$fotoValid = in_array($fotoExt, $allowedFotoExt, true)
             && $foto !== 'default.png'
             && file_exists(__DIR__ . '/assets/img/' . $foto);

// ===== Halaman aktif =====
$current = basename($_SERVER['PHP_SELF'] ?? '');

// ===== PERBAIKAN #3: Hitung voucher aktif dengan safety =====
$jumlahVoucherAktif = 0;
if ($loggedIn && $role === 'user' && isset($conn) && $conn instanceof mysqli) {
    $uid = (int)$_SESSION['id_user'];
    try {
        $qV = $conn->query("SELECT COUNT(*) AS c FROM voucher_gratis WHERE id_user = $uid AND status = 'active'");
        if ($qV) {
            $jumlahVoucherAktif = (int)$qV->fetch_assoc()['c'];
        }
    } catch (Throwable $e) {
        // Diamkan saja — sidebar tidak boleh crash karena voucher
        $jumlahVoucherAktif = 0;
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   WIZCINEMA SIDEBAR
   ============================================ */
.wc-sidebar {
    width: 272px;
    background: linear-gradient(180deg, #0B1120 0%, #0F1729 100%);
    border-right: 1px solid #1E293B;
    padding: 24px 16px;
    height: 100vh;
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    overflow-y: auto;
    font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    z-index: 100;
}

/* ===== BRAND ===== */
.wc-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 8px 20px;
    border-bottom: 1px solid #1E293B;
    margin-bottom: 20px;
    text-decoration: none;
}
.wc-brand-logo {
    width: 40px; height: 40px;
    border-radius: 11px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem;
    box-shadow: 0 6px 16px rgba(59,130,246,0.4);
    flex-shrink: 0;
}
.wc-brand-text {
    font-size: 1.18rem;
    font-weight: 800;
    letter-spacing: 0.3px;
    color: #fff;
    line-height: 1;
}
.wc-brand-text span { color: #3B82F6; }

/* ===== PROFILE CARD ===== */
.wc-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(59,130,246,0.06);
    border: 1px solid rgba(59,130,246,0.15);
    border-radius: 14px;
    margin-bottom: 18px;
}
.wc-avatar {
    width: 52px; height: 52px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid #3B82F6;
    background: #111A2E;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}
.wc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.wc-profile-info { min-width: 0; flex: 1; }
.wc-profile-name {
    color: #fff;
    font-weight: 700;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.wc-profile-role {
    font-size: 0.65rem;
    color: #60A5FA;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 3px;
}
.wc-profile-bio {
    font-size: 0.72rem;
    color: #64748B;
    font-style: italic;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ===== SHOW & MENU BUTTONS ===== */
.wc-nav-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
}
.wc-nav-btn {
    flex: 1;
    text-align: center;
    padding: 12px 0;
    border-radius: 11px;
    font-weight: 700;
    font-size: 0.82rem;
    text-decoration: none;
    color: #94A3B8;
    background: #111A2E;
    border: 1px solid #1E293B;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.wc-nav-btn i { font-size: 0.9rem; }
.wc-nav-btn:hover {
    background: #1A2740;
    color: #fff;
    border-color: #3B82F6;
    transform: translateY(-1px);
}
.wc-nav-btn.active {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 18px rgba(59,130,246,0.4);
}

/* ===== SECTION LABEL ===== */
.wc-nav-section {
    font-size: 0.65rem;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 14px 12px 6px;
}

/* ===== NAV LIST ===== */
.wc-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    list-style: none;
    padding: 0;
    margin: 0;
}
.wc-nav-link {
    color: #94A3B8;
    padding: 11px 14px;
    border-radius: 10px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 500;
    position: relative;
}
.wc-nav-link i {
    width: 20px;
    text-align: center;
    font-size: 0.95rem;
    transition: transform 0.2s;
    flex-shrink: 0;
}
.wc-nav-link:hover {
    background: rgba(59,130,246,0.08);
    color: #fff;
}
.wc-nav-link:hover i { transform: scale(1.1); color: #60A5FA; }
.wc-nav-link.active {
    background: rgba(59,130,246,0.12);
    color: #60A5FA;
    font-weight: 700;
}
.wc-nav-link.active i { color: #60A5FA; }
.wc-nav-link.active::before {
    content: '';
    position: absolute;
    left: -16px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 22px;
    border-radius: 0 3px 3px 0;
    background: #3B82F6;
}
.wc-nav-link.danger { color: #F87171; }
.wc-nav-link.danger i { color: #F87171; }
.wc-nav-link.danger:hover {
    background: rgba(239,68,68,0.1);
    color: #F87171;
}

/* Badge jumlah voucher */
.wc-nav-badge {
    margin-left: auto;
    background: linear-gradient(135deg, #FBBF24, #F59E0B);
    color: #1F2937;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
    min-width: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(251,191,36,0.4);
    animation: badgePulse 2s ease-in-out infinite;
}
@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

/* Voucher link special color */
.wc-nav-link.voucher-link i { color: #FBBF24; }
.wc-nav-link.voucher-link:hover {
    background: rgba(251,191,36,0.08);
    color: #FCD34D;
}
.wc-nav-link.voucher-link.active {
    background: rgba(251,191,36,0.12);
    color: #FCD34D;
}
.wc-nav-link.voucher-link.active i { color: #FCD34D; }

/* ===== SCROLLBAR ===== */
.wc-sidebar::-webkit-scrollbar { width: 4px; }
.wc-sidebar::-webkit-scrollbar-track { background: transparent; }
.wc-sidebar::-webkit-scrollbar-thumb {
    background: #3B82F6;
    border-radius: 10px;
    opacity: 0.5;
}
.wc-sidebar::-webkit-scrollbar-thumb:hover { background: #2563EB; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .wc-sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 16px;
        border-right: none;
        border-bottom: 1px solid #1E293B;
    }
    .wc-brand {
        padding-bottom: 14px;
        margin-bottom: 14px;
    }
    .wc-profile { margin-bottom: 14px; }
    .wc-nav-buttons { margin-bottom: 14px; }
    .wc-nav { flex-direction: row; flex-wrap: wrap; }
    .wc-nav-link {
        flex: 1 1 auto;
        justify-content: center;
        padding: 10px 12px;
    }
    .wc-nav-link.active::before { display: none; }
    .wc-nav-section { display: none; }
    .wc-nav-badge { margin-left: 6px; }
}
</style>

<nav class="wc-sidebar">
    <!-- ===== BRAND ===== -->
    <a href="<?= ($role === 'admin' || $role === 'kasir') ? 'dashboard.php' : 'index.php'; ?>" class="wc-brand">
        <div class="wc-brand-logo"><i class="fas fa-film"></i></div>
        <div class="wc-brand-text">WIZ<span>CINEMA</span></div>
    </a>

    <!-- ===== PROFILE ===== -->
    <div class="wc-profile">
        <div class="wc-avatar">
            <?php if ($fotoValid): ?>
                <img src="assets/img/<?= htmlspecialchars($foto); ?>" alt="Avatar">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(mb_substr($nama, 0, 1))); ?>
            <?php endif; ?>
        </div>
        <div class="wc-profile-info">
            <div class="wc-profile-name"><?= htmlspecialchars($nama); ?></div>
            <div class="wc-profile-role"><?= htmlspecialchars(ucfirst($role ?: 'guest')); ?></div>
        </div>
    </div>

    <!-- ===== SHOW & MENU BUTTONS ===== -->
    <div class="wc-nav-buttons">
        <a href="index.php" class="wc-nav-btn <?= $current === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-film"></i> SHOW
        </a>
        <a href="menu.php" class="wc-nav-btn <?= $current === 'menu.php' ? 'active' : ''; ?>">
            <i class="fas fa-utensils"></i> MENU
        </a>
    </div>

    <?php if ($role === 'user'): ?>
        <!-- ===== USER MENU ===== -->
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li>
                <a class="wc-nav-link voucher-link <?= $current === 'voucher_saya.php' ? 'active' : ''; ?>" href="voucher_saya.php">
                    <i class="fas fa-gift"></i> Voucher Saya
                    <?php if ($jumlahVoucherAktif > 0): ?>
                        <span class="wc-nav-badge"><?= $jumlahVoucherAktif; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user"></i> Profil
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-sliders-h"></i> Pengaturan
                </a>
            </li>
            <li>
                <a class="wc-nav-link danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>

    <?php elseif ($role === 'admin'): ?>
        <!-- ===== ADMIN MENU ===== -->
        <div class="wc-nav-section">Menu Utama</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
        </ul>

        <div class="wc-nav-section">Kelola Konten</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'admin_films.php' ? 'active' : ''; ?>" href="admin_films.php">
                    <i class="fas fa-film"></i> Film
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'admin_jadwal.php' ? 'active' : ''; ?>" href="admin_jadwal.php">
                    <i class="fas fa-calendar"></i> Jadwal
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'admin_studio.php' ? 'active' : ''; ?>" href="admin_studio.php">
                    <i class="fas fa-video"></i> Studio
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'admin_promo.php' ? 'active' : ''; ?>" href="admin_promo.php">
                    <i class="fas fa-tags"></i> Promo
                </a>
            </li>
        </ul>

        <div class="wc-nav-section">Interaksi</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'admin_ulasan.php' ? 'active' : ''; ?>" href="admin_ulasan.php">
                    <i class="fas fa-star"></i> Ulasan
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'admin_suara.php' ? 'active' : ''; ?>" href="admin_suara.php">
                    <i class="fas fa-volume-up"></i> Suara
                </a>
            </li>
        </ul>

        <div class="wc-nav-section">Akun</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>

    <?php elseif ($role === 'kasir'): ?>
        <!-- ===== KASIR MENU ===== -->
        <div class="wc-nav-section">Menu Utama</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'kasir_menu.php' ? 'active' : ''; ?>" href="kasir_menu.php">
                    <i class="fas fa-utensils"></i> Menu Resto
                </a>
            </li>
            <li>
                <a class="wc-nav-link <?= $current === 'kasir_laporan.php' ? 'active' : ''; ?>" href="kasir_laporan.php">
                    <i class="fas fa-file-invoice"></i> Laporan
                </a>
            </li>
        </ul>

        <div class="wc-nav-section">Interaksi</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link <?= $current === 'admin_ulasan.php' ? 'active' : ''; ?>" href="admin_ulasan.php">
                    <i class="fas fa-star"></i> Ulasan
                </a>
            </li>
        </ul>

        <div class="wc-nav-section">Akun</div>
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>

    <?php else: ?>
        <!-- ===== GUEST ===== -->
        <ul class="wc-nav">
            <li>
                <a class="wc-nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </li>
            <li>
                <a class="wc-nav-link" href="register.php">
                    <i class="fas fa-user-plus"></i> Daftar
                </a>
            </li>
        </ul>
    <?php endif; ?>
</nav>