<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'User';
$userId = (int)$_SESSION['id_user'];

// Ambil data user
$user = $conn->query("SELECT * FROM users WHERE id_user = $userId")->fetch_assoc();

// Ambil volume (default 70)
$volume = isset($_SESSION['volume']) ? (int)$_SESSION['volume'] : 70;
$saved = false;
$error = '';
$saved_msg = '';

// ===== PROSES SIMPAN PENGATURAN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Simpan Volume
    if (isset($_POST['save_volume'])) {
        $volume = isset($_POST['volume']) ? (int)$_POST['volume'] : 70;
        if ($volume < 0) $volume = 0;
        if ($volume > 100) $volume = 100;
        $_SESSION['volume'] = $volume;
        $saved = true;
        $saved_msg = 'Volume berhasil diperbarui!';
    }
    
    // 2. Ganti Password
    if (isset($_POST['save_password'])) {
        $pass_lama = $_POST['pass_lama'] ?? '';
        $pass_baru = $_POST['pass_baru'] ?? '';
        $pass_konfirm = $_POST['pass_konfirm'] ?? '';
        
        if (empty($pass_lama) || empty($pass_baru) || empty($pass_konfirm)) {
            $error = 'Semua kolom password wajib diisi!';
        } elseif (strlen($pass_baru) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($pass_baru !== $pass_konfirm) {
            $error = 'Konfirmasi password tidak cocok!';
        } elseif (!password_verify($pass_lama, $user['password']) && $pass_lama !== $user['password']) {
            $error = 'Password lama salah!';
        } else {
            $hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id_user=?");
            $stmt->bind_param("si", $hash, $userId);
            $stmt->execute();
            $stmt->close();
            $saved = true;
            $saved_msg = 'Password berhasil diubah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0B1120;
            --card-bg: #111A2E;
            --card-border: #1E293B;
            --blue: #3B82F6;
            --yellow: #FBBF24;
            --green: #10B981;
            --purple: #8B5CF6;
            --text-muted: #94A3B8;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-dark);
            color: #F1F5F9;
            display: flex;
            min-height: 100vh;
        }
        .main-content { flex: 1; padding: 32px 38px; min-width: 0; }

        /* ===== HEADER ===== */
        .page-header { margin-bottom: 30px; }
        .page-header h4 {
            font-weight: 800; font-size: 1.8rem; color: #fff; letter-spacing: -0.5px;
            display: flex; align-items: center; gap: 12px; margin: 0 0 8px 0;
        }
        .page-header h4 i {
            width: 46px; height: 46px; border-radius: 14px;
            background: linear-gradient(135deg, var(--purple), #7C3AED);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(139,92,246,0.35);
        }
        .page-header .sub { color: var(--text-muted); font-size: 0.95rem; margin-left: 58px; }

        /* ===== ALERTS ===== */
        .alert-custom {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px; border-radius: 14px; margin-bottom: 25px;
            font-size: 0.9rem; font-weight: 500; animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6EE7B7; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; }

        /* ===== GRID LAYOUT ===== */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ===== CARD STYLES ===== */
        .card-el {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        .card-el:hover { border-color: #2A3A5C; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }

        .card-header-el {
            display: flex; align-items: center; gap: 14px;
            padding-bottom: 18px; margin-bottom: 24px;
            border-bottom: 1px solid var(--card-border);
        }
        .card-header-icon {
            width: 46px; height: 46px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff; flex-shrink: 0;
        }
        .icon-purple { background: linear-gradient(135deg, var(--purple), #7C3AED); box-shadow: 0 6px 15px rgba(139,92,246,0.3); }
        .icon-blue   { background: linear-gradient(135deg, var(--blue), #2563EB); box-shadow: 0 6px 15px rgba(59,130,246,0.3); }
        .icon-green  { background: linear-gradient(135deg, var(--green), #059669); box-shadow: 0 6px 15px rgba(16,185,129,0.3); }
        .icon-gold   { background: linear-gradient(135deg, var(--yellow), #F59E0B); box-shadow: 0 6px 15px rgba(251,191,36,0.3); }

        .card-header-text { flex: 1; min-width: 0; }
        .card-header-title { font-size: 1.05rem; font-weight: 800; color: #fff; }
        .card-header-sub { font-size: 0.8rem; color: #64748B; margin-top: 3px; }

        /* ===== PROFIL SAYA ===== */
        .profile-summary {
            display: flex; align-items: center; gap: 20px;
            padding: 20px; background: #0F1729;
            border: 1px solid var(--card-border); border-radius: 16px; margin-bottom: 20px;
        }
        .profile-avatar-lg {
            width: 80px; height: 80px; border-radius: 50%;
            overflow: hidden; flex-shrink: 0;
            border: 3px solid var(--purple);
            background: #111A2E;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; color: var(--purple);
            box-shadow: 0 0 0 5px rgba(139,92,246,0.12);
        }
        .profile-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { min-width: 0; flex: 1; }
        .profile-name { font-size: 1.2rem; font-weight: 800; color: #fff; margin-bottom: 6px; }
        
        .profile-role-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.7rem; font-weight: 800; color: #A78BFA;
            background: rgba(139,92,246,0.15); padding: 5px 12px;
            border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .profile-username { font-size: 0.85rem; color: #64748B; margin-top: 8px; }

        .profile-links { display: flex; gap: 10px; }
        .btn-mini {
            flex: 1; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 12px 16px; background: transparent;
            border: 1.5px solid var(--card-border); color: var(--text-muted);
            border-radius: 10px; text-decoration: none; font-size: 0.85rem;
            font-weight: 600; font-family: inherit; transition: all 0.25s; cursor: pointer;
        }
        .btn-mini:hover { background: #1E293B; color: #fff; }
        .btn-mini.primary {
            background: linear-gradient(135deg, var(--purple), #7C3AED);
            border-color: transparent; color: #fff; box-shadow: 0 6px 15px rgba(139,92,246,0.3);
        }
        .btn-mini.primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139,92,246,0.5); }

        /* ===== VOLUME SLIDER ===== */
        .volume-display {
            text-align: center; padding: 24px 20px; background: #0F1729;
            border: 1px solid var(--card-border); border-radius: 16px; margin-bottom: 24px;
        }
        .volume-icon-lg { font-size: 2rem; color: var(--blue); margin-bottom: 10px; display: inline-block; }
        .volume-value { font-size: 3rem; font-weight: 800; color: #60A5FA; line-height: 1; letter-spacing: -1px; }
        .volume-value span { font-size: 1.3rem; color: #475569; font-weight: 700; }

        .form-range {
            width: 100%; height: 8px; border-radius: 999px; background: #0B1120;
            outline: none; -webkit-appearance: none; appearance: none;
            cursor: pointer; border: 1px solid var(--card-border); margin-bottom: 12px;
        }
        .form-range::-webkit-slider-thumb {
            -webkit-appearance: none; width: 22px; height: 22px; border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), #2563EB); cursor: pointer;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2), 0 4px 12px rgba(59,130,246,0.5); transition: 0.2s;
        }
        .form-range::-webkit-slider-thumb:hover { transform: scale(1.1); }
        .form-range::-moz-range-thumb {
            width: 22px; height: 22px; border-radius: 50%; border: none;
            background: linear-gradient(135deg, var(--blue), #2563EB); cursor: pointer;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2);
        }
        .slider-labels {
            display: flex; justify-content: space-between; font-size: 0.75rem;
            color: #64748B; font-weight: 600; margin-bottom: 20px;
        }

        /* ===== FORM INPUTS ===== */
        .form-label-el {
            display: block; color: var(--text-muted); font-size: 0.85rem;
            font-weight: 600; margin-bottom: 8px;
        }
        .form-label-el i { color: var(--purple); margin-right: 6px; font-size: 0.85rem; }
        
        .input-wrapper { position: relative; margin-bottom: 18px; }
        .form-input-el {
            width: 100%; background: #0B1120; border: 1.5px solid var(--card-border);
            color: #F1F5F9; border-radius: 12px; padding: 14px 16px;
            font-size: 0.9rem; font-family: inherit; outline: none;
            transition: 0.25s; padding-right: 45px;
        }
        .form-input-el::placeholder { color: #475569; }
        .form-input-el:focus { border-color: var(--purple); box-shadow: 0 0 0 4px rgba(139,92,246,0.15); }
        
        .pass-toggle {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            background: transparent; border: none; color: #64748B;
            cursor: pointer; padding: 4px; font-size: 1rem; transition: 0.2s;
        }
        .pass-toggle:hover { color: var(--yellow); }

        /* ===== BUTTONS ===== */
        .btn-submit-el {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-weight: 800; font-size: 0.95rem; font-family: inherit;
            color: #fff; cursor: pointer; transition: all 0.25s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit-el.blue { background: linear-gradient(135deg, var(--blue), #2563EB); box-shadow: 0 8px 20px rgba(59,130,246,0.3); }
        .btn-submit-el.purple { background: linear-gradient(135deg, var(--purple), #7C3AED); box-shadow: 0 8px 20px rgba(139,92,246,0.3); }
        .btn-submit-el:hover { transform: translateY(-2px); }
        .btn-submit-el.blue:hover { box-shadow: 0 12px 25px rgba(59,130,246,0.45); }
        .btn-submit-el.purple:hover { box-shadow: 0 12px 25px rgba(139,92,246,0.45); }

        /* ===== INFO AKUN ===== */
        .info-list { display: flex; flex-direction: column; gap: 12px; }
        .info-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 16px; background: #0F1729;
            border: 1px solid var(--card-border); border-radius: 12px; font-size: 0.9rem;
        }
        .info-item .label { color: var(--text-muted); display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .info-item .label i { color: var(--blue); width: 16px; text-align: center; }
        .info-item .value { color: #fff; font-weight: 700; }

        .hint-text {
            font-size: 0.78rem; color: #64748B; margin-top: -8px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 6px;
        }
        .hint-text i { color: var(--yellow); }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .page-header .sub { margin-left: 0; margin-top: 8px; }
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <!-- Header -->
        <div class="page-header">
            <h4><i class="fas fa-sliders-h"></i> Pengaturan</h4>
            <div class="sub">Kelola preferensi akun dan keamanan Anda</div>
        </div>

        <!-- Alerts -->
        <?php if($saved): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($saved_msg); ?>
            </div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert-custom alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Grid Atas: Profil & Volume -->
        <div class="settings-grid">

            <!-- CARD 1: PROFIL SAYA -->
            <div class="card-el">
                <div class="card-header-el">
                    <div class="card-header-icon icon-purple"><i class="fas fa-user-circle"></i></div>
                    <div class="card-header-text">
                        <div class="card-header-title">Profil Saya</div>
                        <div class="card-header-sub">Informasi akun Anda saat ini</div>
                    </div>
                </div>

                <div class="profile-summary">
                    <div class="profile-avatar-lg">
                        <?php 
                        $foto = $user['foto_profil'] ?? 'default.png';
                        if (!empty($foto) && $foto !== 'default.png' && file_exists('assets/img/'.$foto)): ?>
                            <img src="assets/img/<?= htmlspecialchars($foto); ?>" alt="Avatar">
                        <?php else: ?>
                            <?= strtoupper(substr($user['nama'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($user['nama']); ?></div>
                        <div class="profile-role-badge">
                            <i class="fas <?= $role === 'admin' ? 'fa-shield-halved' : ($role === 'kasir' ? 'fa-cash-register' : 'fa-user'); ?>"></i>
                            <?= ucfirst($role); ?>
                        </div>
                        <div class="profile-username">@<?= htmlspecialchars($user['username']); ?></div>
                    </div>
                </div>

                <div class="profile-links">
                    <?php if($role === 'user'): ?>
                        <a href="profile.php" class="btn-mini primary">
                            <i class="fas fa-edit"></i> Edit Profil
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-mini">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- CARD 2: VOLUME SUARA -->
            <div class="card-el">
                <div class="card-header-el">
                    <div class="card-header-icon icon-blue"><i class="fas fa-volume-up"></i></div>
                    <div class="card-header-text">
                        <div class="card-header-title">Volume Suara</div>
                        <div class="card-header-sub">Atur tingkat volume aplikasi</div>
                    </div>
                </div>

                <form method="POST">
                    <div class="volume-display">
                        <i class="fas fa-volume-up volume-icon-lg" id="volIcon"></i>
                        <div class="volume-value">
                            <span id="volLabel"><?= $volume; ?></span><span>%</span>
                        </div>
                    </div>
                    
                    <input type="range" name="volume" class="form-range" min="0" max="100" 
                           value="<?= $volume; ?>" id="volumeSlider"
                           oninput="updateVolume(this.value)">
                           
                    <div class="slider-labels">
                        <span>0% (Mute)</span>
                        <span>50%</span>
                        <span>100% (Max)</span>
                    </div>

                    <button type="submit" name="save_volume" class="btn-submit-el blue">
                        <i class="fas fa-save"></i> Simpan Volume
                    </button>
                </form>
            </div>
        </div>

        <!-- Grid Bawah: Password & Info -->
        <div class="settings-grid">
            
            <!-- CARD 3: UBAH PASSWORD -->
            <div class="card-el">
                <div class="card-header-el">
                    <div class="card-header-icon icon-gold"><i class="fas fa-key"></i></div>
                    <div class="card-header-text">
                        <div class="card-header-title">Ubah Password</div>
                        <div class="card-header-sub">Perbarui kata sandi keamanan akun</div>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <label class="form-label-el"><i class="fas fa-lock"></i> Password Lama</label>
                    <div class="input-wrapper">
                        <input type="password" name="pass_lama" id="passLama" class="form-input-el" placeholder="Masukkan password lama" required>
                        <button type="button" class="pass-toggle" onclick="togglePass('passLama', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <label class="form-label-el"><i class="fas fa-lock-open"></i> Password Baru</label>
                    <div class="input-wrapper">
                        <input type="password" name="pass_baru" id="passBaru" class="form-input-el" placeholder="Minimal 6 karakter" required minlength="6">
                        <button type="button" class="pass-toggle" onclick="togglePass('passBaru', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <label class="form-label-el"><i class="fas fa-check-circle"></i> Konfirmasi Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="pass_konfirm" id="passKonfirm" class="form-input-el" placeholder="Ulangi password baru" required minlength="6">
                        <button type="button" class="pass-toggle" onclick="togglePass('passKonfirm', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="hint-text">
                        <i class="fas fa-info-circle"></i>
                        Gunakan kombinasi huruf, angka, dan simbol untuk keamanan ekstra.
                    </div>

                    <button type="submit" name="save_password" class="btn-submit-el purple">
                        <i class="fas fa-shield-alt"></i> Ubah Password
                    </button>
                </form>
            </div>

            <!-- CARD 4: INFO AKUN -->
            <div class="card-el">
                <div class="card-header-el">
                    <div class="card-header-icon icon-green"><i class="fas fa-info-circle"></i></div>
                    <div class="card-header-text">
                        <div class="card-header-title">Informasi Akun</div>
                        <div class="card-header-sub">Detail identitas akun Anda</div>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user"></i> Nama Lengkap</span>
                        <span class="value"><?= htmlspecialchars($user['nama']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-at"></i> Username</span>
                        <span class="value"><?= htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user-tag"></i> Hak Akses (Role)</span>
                        <span class="value" style="color: #A78BFA; text-transform: capitalize;"><?= ucfirst($role); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-id-badge"></i> ID User</span>
                        <span class="value">#<?= $userId; ?></span>
                    </div>
                    <?php if(!empty($user['bio'])): ?>
                    <div class="info-item" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                        <span class="label"><i class="fas fa-quote-left"></i> Bio</span>
                        <span class="value" style="font-weight: 500; font-style: italic; color: #94A3B8; font-size: 0.85rem; line-height: 1.5;">
                            "<?= htmlspecialchars($user['bio']); ?>"
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePass(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Dynamic Volume Icon
        function updateVolume(val) {
            const v = parseInt(val);
            document.getElementById('volLabel').textContent = v;

            const icon = document.getElementById('volIcon');
            if (v === 0) icon.className = 'fas fa-volume-mute volume-icon-lg';
            else if (v < 40) icon.className = 'fas fa-volume-down volume-icon-lg';
            else if (v < 75) icon.className = 'fas fa-volume-up volume-icon-lg';
            else icon.className = 'fas fa-bullhorn volume-icon-lg';
        }

        // Init on load
        updateVolume(<?= $volume; ?>);
    </script>
</body>
</html>