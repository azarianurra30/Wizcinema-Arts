<?php
// ===== Secure session cookie params SEBELUM session_start =====
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

if (!empty($_SESSION['id_user'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/config.php';
include 'csrf.php';

$errors = [];
$old = ['nama' => '', 'username' => '', 'email' => '', 'phone' => ''];
$method = $_POST['method'] ?? 'email';

// ===== PERBAIKAN: Rate limit — max 1 percobaan / 5 detik =====
$now = time();
$last_attempt = $_SESSION['register_last_attempt'] ?? 0;
if ($now - $last_attempt < 5) {
    $errors['general'] = 'Terlalu cepat. Tunggu ' . (5 - ($now - $last_attempt)) . ' detik lagi.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register' && empty($errors['general'])) {
    // ===== PERBAIKAN: CSRF verifikasi =====
    verify_csrf();

    $_SESSION['register_last_attempt'] = $now;

    $nama_lengkap = trim($_POST['nama'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';
    $terms        = isset($_POST['terms']);

    $old = ['nama' => $nama_lengkap, 'username' => $username, 'email' => $email, 'phone' => $phone];

    // ===== PERBAIKAN: Validasi panjang nama =====
    if ($nama_lengkap === '') {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama_lengkap) < 3) {
        $errors['nama'] = 'Nama minimal 3 karakter.';
    } elseif (mb_strlen($nama_lengkap) > 100) {
        $errors['nama'] = 'Nama maksimal 100 karakter.';
    }

    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
        $errors['username'] = 'Username 3–30 karakter (huruf/angka/titik/_).';
    }

    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);

    if ($method === 'email') {
        if ($email === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        } elseif (mb_strlen($email) > 100) {
            $errors['email'] = 'Email maksimal 100 karakter.';
        }
    } else {
        if ($phone === '') {
            $errors['phone'] = 'Nomor HP wajib diisi.';
        } elseif (!preg_match('/^(\+?62|0)8[0-9]{8,12}$/', $cleanPhone)) {
            $errors['phone'] = 'Nomor HP tidak valid.';
        }
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    } elseif (strlen($password) > 100) {
        $errors['password'] = 'Password maksimal 100 karakter.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password harus ada huruf & angka.';
    }

    if ($confirm !== $password) {
        $errors['confirm_password'] = 'Konfirmasi password tidak sama.';
    }
    if (!$terms) {
        $errors['terms'] = 'Setujui syarat & ketentuan.';
    }

    // ===== PERBAIKAN: Password tidak boleh sama dengan username =====
    if (empty($errors['password']) && strtolower($password) === strtolower($username)) {
        $errors['password'] = 'Password tidak boleh sama dengan username.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) $errors['username'] = 'Username sudah dipakai.';

            if ($method === 'email' && $email !== '') {
                $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) $errors['email'] = 'Email sudah terdaftar.';
            }
            if ($method === 'phone' && $cleanPhone !== '') {
                $stmt = $pdo->prepare("SELECT id_user FROM users WHERE phone = ? LIMIT 1");
                $stmt->execute([$cleanPhone]);
                if ($stmt->fetch()) $errors['phone'] = 'Nomor HP sudah terdaftar.';
            }

            if (empty($errors)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (nama, username, email, phone, password, role) 
                     VALUES (?, ?, ?, ?, ?, 'user')"
                );
                $stmt->execute([
                    $nama_lengkap,
                    $username,
                    $method === 'email' ? $email : null,
                    $method === 'phone' ? $cleanPhone : null,
                    $hash,
                ]);

                $newId = (int) $pdo->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['id_user']    = $newId;
                $_SESSION['nama']       = $nama_lengkap;
                $_SESSION['username']   = $username;
                $_SESSION['role']       = 'user';
                $_SESSION['foto']       = 'default.png';
                $_SESSION['bio']        = '';
                $_SESSION['login_time'] = time();

                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            // ===== PERBAIKAN: Jangan bocorkan detail DB ke user =====
            error_log('Register error: ' . $e->getMessage());
            $errors['general'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$hasGoogle = file_exists(__DIR__ . '/google-oauth-start.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar | WizCinema</title>
    <link rel="icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --navy:#091426;--navy-2:#0d1a2e;--blue:#3987ff;--yellow:#ffd447;
            --gold:#f5b921;--green:#37d6a0;--white:#f7f9ff;--muted:#9aaac1;
            --line:rgba(255,255,255,.10);--input:#0f1e33;--input-b:#243a50;
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;display:grid;place-items:center;padding:24px;font-family:'DM Sans',sans-serif;color:var(--white);background:var(--navy)}
        .register-card{width:min(980px,100%);display:grid;grid-template-columns:.88fr 1.12fr;overflow:hidden;border:1px solid var(--line);border-radius:22px;background:var(--navy-2);box-shadow:0 28px 70px rgba(0,0,0,.28)}
        .brand-panel{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:space-between;padding:34px;overflow:hidden;background:linear-gradient(155deg,#173b69,#10213a 65%,#0c192c)}
        .brand-panel::before{content:"";position:absolute;z-index:-1;width:300px;height:300px;right:-130px;top:120px;border:1px solid rgba(255,255,255,.14);border-radius:50%;box-shadow:0 0 0 25px rgba(255,255,255,.025),0 0 0 50px rgba(255,255,255,.02)}
        .brand-panel::after{content:"";position:absolute;z-index:-1;width:210px;height:210px;right:-80px;top:165px;border-radius:50%;background:rgba(57,135,255,.17)}
        .brand{display:flex;align-items:center;gap:11px}
        .brand-logo{width:44px;height:44px;display:grid;place-items:center;flex-shrink:0;overflow:hidden;border-radius:12px;background:var(--blue)}
        .brand-logo img{width:100%;height:100%;object-fit:cover}
        .brand-name{font-size:20px;font-weight:800;letter-spacing:-.7px}
        .brand-name span{color:var(--yellow)}
        .brand-caption{margin-top:4px;color:#a7bad5;font-size:9px;letter-spacing:1.5px}
        .brand-content{position:relative;z-index:1;margin:40px 0}
        .tagline{color:var(--yellow);font-size:10px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase}
        .brand-content h1{margin-top:15px;font-size:clamp(28px,3.5vw,38px);line-height:1.2;letter-spacing:-1.2px}
        .brand-content h1 span{color:var(--yellow)}
        .brand-content p{max-width:280px;margin-top:15px;color:#b1bfd2;font-size:13px;line-height:1.9}
        .brand-features{position:relative;z-index:1;display:flex;flex-direction:column;gap:14px;margin-top:28px}
        .brand-features .feat{display:flex;align-items:center;gap:12px;color:#c6d3e6;font-size:12px}
        .brand-features .feat i{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:rgba(255,212,71,.10);color:var(--yellow);font-size:12px;flex-shrink:0}
        .panel-footer{color:#8094b0;font-size:10px}
        .form-panel{display:flex;flex-direction:column;justify-content:center;padding:42px}
        .form-head{margin-bottom:18px}
        .form-head h2{font-size:26px;line-height:1.25;letter-spacing:-.8px}
        .form-head h2 span{color:var(--yellow)}
        .alert-error{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;margin-bottom:18px;border:1px solid rgba(255,84,84,.30);border-radius:10px;background:rgba(255,84,84,.08);color:#ff9b9b;font-size:12px;line-height:1.5}
        .field{margin-bottom:15px}
        .field label{display:block;margin-bottom:8px;font-size:11px;font-weight:700;letter-spacing:.6px;color:#c6d3e6;text-transform:uppercase}
        .field.has-error .input-wrap input{border-color:rgba(255,84,84,.55);background:rgba(255,84,84,.04)}
        .field .err-msg{margin-top:6px;color:#ff9b9b;font-size:11px;line-height:1.4}
        .input-wrap{position:relative;display:flex;align-items:center}
        .input-wrap > i.lead{position:absolute;left:14px;color:#7c8fab;font-size:14px;pointer-events:none;transition:color .2s}
        .input-wrap:focus-within > i.lead{color:var(--yellow)}
        .input-wrap input{width:100%;height:48px;padding:0 46px 0 42px;border:1.5px solid var(--input-b);border-radius:11px;background:var(--input);color:var(--white);font-family:inherit;font-size:13.5px;outline:none;transition:border-color .2s,box-shadow .2s}
        .input-wrap input::placeholder{color:#66788f}
        .input-wrap input:focus{border-color:var(--yellow);box-shadow:0 0 0 4px rgba(255,212,71,.10)}
        .toggle-pass{position:absolute;right:10px;width:34px;height:34px;display:grid;place-items:center;border:none;border-radius:8px;background:transparent;color:#7c8fab;cursor:pointer;font-size:14px;transition:color .2s,background .2s}
        .toggle-pass:hover{color:var(--yellow);background:rgba(255,212,71,.08)}
        .strength{display:flex;gap:4px;margin-top:8px}
        .strength span{flex:1;height:3px;border-radius:999px;background:var(--input-b);transition:background .2s}
        .strength.s1 span:nth-child(1){background:#ff5454}
        .strength.s2 span:nth-child(-n+2){background:#ff9b4a}
        .strength.s3 span:nth-child(-n+3){background:#ffd447}
        .strength.s4 span{background:var(--green)}
        .method-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:4px;margin-bottom:18px;background:var(--input);border:1.5px solid var(--input-b);border-radius:12px}
        .method-toggle label{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 12px;border-radius:9px;font-size:12.5px;font-weight:700;color:var(--muted);cursor:pointer;user-select:none;transition:all .2s}
        .method-toggle input{position:absolute;opacity:0;width:0;height:0}
        .method-toggle input:checked + label{background:linear-gradient(135deg,var(--yellow),var(--gold));color:#091426;box-shadow:0 6px 14px rgba(245,185,33,.25)}
        .checkbox{display:inline-flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:12px;color:#c6d3e6;user-select:none;line-height:1.5;margin:6px 0 20px}
        .checkbox input{position:absolute;opacity:0;width:0;height:0}
        .checkbox .box{margin-top:1px;width:18px;height:18px;display:grid;place-items:center;border:1.5px solid var(--input-b);border-radius:5px;background:var(--input);color:transparent;font-size:10px;flex-shrink:0;transition:all .2s}
        .checkbox input:checked + .box{background:var(--yellow);border-color:var(--yellow);color:#091426}
        .checkbox a{color:var(--yellow);font-weight:600;text-decoration:none}
        .btn-register{width:100%;min-height:52px;display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 18px;border:none;border-radius:11px;background:linear-gradient(135deg,var(--yellow),var(--gold));color:#091426;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:transform .15s,box-shadow .2s,filter .2s;box-shadow:0 12px 26px rgba(245,185,33,.28)}
        .btn-register:hover{filter:brightness(1.05);transform:translateY(-1px)}
        .divider{display:flex;align-items:center;gap:12px;margin:18px 0 14px;color:#66788f;font-size:11px}
        .divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--line)}
        .btn-google{width:100%;min-height:48px;display:flex;align-items:center;justify-content:center;gap:11px;padding:12px 18px;border:1.5px solid var(--input-b);border-radius:11px;background:transparent;color:#d8e2f1;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:border-color .2s,background .2s}
        .btn-google:hover{border-color:#3987ff;background:rgba(57,135,255,.08)}
        .btn-google .g-icon{width:22px;height:22px;display:grid;place-items:center;border-radius:50%;background:#fff;flex-shrink:0}
        .btn-google .g-icon svg{width:14px;height:14px}
        .login-note{text-align:center;margin-top:18px;font-size:13px;color:var(--muted)}
        .login-note a{color:var(--yellow);font-weight:700;text-decoration:none}
        .back-link{display:inline-flex;align-items:center;gap:8px;margin-top:14px;color:#8fa0b8;font-size:11.5px;text-decoration:none}
        @media (max-width:860px){
            .register-card{grid-template-columns:1fr;max-width:460px}
            .brand-panel{padding:26px 24px}
            .brand-content{margin:26px 0 10px}
            .brand-content h1{font-size:26px}
            .brand-features,.panel-footer{display:none}
            .form-panel{padding:28px 22px 30px}
            .form-head h2{font-size:23px}
        }
    </style>
</head>
<body>
<main class="register-card">
    <section class="brand-panel">
        <div class="brand">
            <div class="brand-logo">
                <img src="logo.png" alt="Logo" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-solid fa-play&quot;></i>';">
            </div>
            <div>
                <div class="brand-name">WIZ<span>CINEMA</span></div>
                <div class="brand-caption">YOUR MOVIE DESTINATION</div>
            </div>
        </div>
        <div class="brand-content">
            <div class="tagline">GABUNG SEKARANG</div>
            <h1>Mulai<br><span>petualanganmu.</span></h1>
            <p>Buat akun WizCinema gratis dan nikmati kemudahan memesan tiket serta promo eksklusif.</p>
            <div class="brand-features">
                <div class="feat"><i class="fa-solid fa-ticket"></i><span>Pesan tiket lebih cepat</span></div>
                <div class="feat"><i class="fa-solid fa-gift"></i><span>Promo & voucher member</span></div>
                <div class="feat"><i class="fa-solid fa-shield-halved"></i><span>Data akun aman</span></div>
            </div>
        </div>
        <div class="panel-footer">&copy; <?= date('Y'); ?> WizCinema</div>
    </section>

    <section class="form-panel">
        <div class="form-head">
            <h2>Daftar akun <span>WizCinema</span></h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-error" role="alert" style="flex-direction:column; align-items:flex-start;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <strong>Ada <?= count($errors); ?> kesalahan:</strong>
                </div>
                <ul style="margin: 0 0 0 22px; padding: 0; font-size:12px; line-height:1.6;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate autocomplete="on">
            <input type="hidden" name="action" value="register">
            <?= csrf_field(); ?>

            <!-- NAMA LENGKAP -->
            <div class="field <?= isset($errors['nama']) ? 'has-error' : ''; ?>">
                <label for="nama">Nama Lengkap</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-id-card lead"></i>
                    <input type="text" id="nama" name="nama" placeholder="Nama Lengkap"
                           value="<?= htmlspecialchars($old['nama']); ?>" required maxlength="100">
                </div>
                <?php if (isset($errors['nama'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['nama']); ?></div>
                <?php endif; ?>
            </div>

            <!-- USERNAME -->
            <div class="field <?= isset($errors['username']) ? 'has-error' : ''; ?>">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-user lead"></i>
                    <input type="text" id="username" name="username" placeholder="Username"
                           autocapitalize="none" spellcheck="false" maxlength="30"
                           value="<?= htmlspecialchars($old['username']); ?>" required>
                </div>
                <?php if (isset($errors['username'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['username']); ?></div>
                <?php endif; ?>
            </div>

            <!-- TOGGLE EMAIL / PHONE -->
            <div class="method-toggle">
                <input type="radio" id="mEmail" name="method" value="email" <?= $method === 'email' ? 'checked' : ''; ?>>
                <label for="mEmail"><i class="fa-regular fa-envelope"></i> Email</label>
                <input type="radio" id="mPhone" name="method" value="phone" <?= $method === 'phone' ? 'checked' : ''; ?>>
                <label for="mPhone"><i class="fa-solid fa-mobile-screen"></i> No. HP</label>
            </div>

            <div class="field <?= isset($errors['email']) ? 'has-error' : ''; ?>" id="fieldEmail">
                <label for="email">Email</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope lead"></i>
                    <input type="email" id="email" name="email" placeholder="nama@email.com" maxlength="100"
                           value="<?= htmlspecialchars($old['email']); ?>">
                </div>
                <?php if (isset($errors['email'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="field <?= isset($errors['phone']) ? 'has-error' : ''; ?>" id="fieldPhone">
                <label for="phone">No. HP</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-mobile-screen lead"></i>
                    <input type="tel" id="phone" name="phone" placeholder="08xxxxxxxxxx" maxlength="20"
                           value="<?= htmlspecialchars($old['phone']); ?>">
                </div>
                <?php if (isset($errors['phone'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['phone']); ?></div>
                <?php endif; ?>
            </div>

            <div class="field <?= isset($errors['password']) ? 'has-error' : ''; ?>">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock lead"></i>
                    <input type="password" id="password" name="password" placeholder="Min. 8 karakter, huruf + angka"
                           autocomplete="new-password" maxlength="100" required>
                    <button type="button" class="toggle-pass" data-target="password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <div class="strength" id="strength"><span></span><span></span><span></span><span></span></div>
                <?php if (isset($errors['password'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="field <?= isset($errors['confirm_password']) ? 'has-error' : ''; ?>">
                <label for="confirm_password">Konfirmasi Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock lead"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password"
                           autocomplete="new-password" maxlength="100" required>
                    <button type="button" class="toggle-pass" data-target="confirm_password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="err-msg"><?= htmlspecialchars($errors['confirm_password']); ?></div>
                <?php endif; ?>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : ''; ?>>
                <span class="box"><i class="fa-solid fa-check"></i></span>
                <span>Setuju <a href="terms.php" target="_blank">Syarat & Ketentuan</a></span>
            </label>
            <?php if (isset($errors['terms'])): ?>
                <div class="err-msg" style="margin-top:-14px;margin-bottom:14px;"><?= htmlspecialchars($errors['terms']); ?></div>
            <?php endif; ?>

            <button type="submit" class="btn-register">
                <i class="fa-solid fa-user-plus"></i> Daftar Sekarang
            </button>
        </form>

        <?php if ($hasGoogle): ?>
            <div class="divider">atau</div>
            <form method="POST" action="google-oauth-start.php">
                <button type="submit" class="btn-google">
                    <span class="g-icon">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                            <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.4-11.3-8.1l-6.5 5C9.6 39.6 16.2 44 24 44z"/>
                            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                        </svg>
                    </span>
                    Daftar dengan Google
                </button>
            </form>
        <?php endif; ?>

        <div class="login-note">Sudah punya akun? <a href="login.php">Masuk</a></div>
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    </section>
</main>

<script>
    (function () {
        const mEmail = document.getElementById('mEmail');
        const mPhone = document.getElementById('mPhone');
        const fEmail = document.getElementById('fieldEmail');
        const fPhone = document.getElementById('fieldPhone');
        function sync() {
            const isEmail = mEmail.checked;
            fEmail.style.display = isEmail ? '' : 'none';
            fPhone.style.display = isEmail ? 'none' : '';
            document.getElementById('email').required = isEmail;
            document.getElementById('phone').required = !isEmail;
        }
        mEmail.addEventListener('change', sync);
        mPhone.addEventListener('change', sync);
        sync();
    })();
    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.innerHTML = hidden ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    });
    (function () {
        const p = document.getElementById('password');
        const b = document.getElementById('strength');
        p.addEventListener('input', function () {
            const v = p.value;
            let s = 0;
            if (v.length >= 8) s++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
            if (/[0-9]/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            b.classList.remove('s1','s2','s3','s4');
            if (v.length === 0) return;
            b.classList.add('s' + Math.max(1, s));
        });
    })();
</script>
</body>
</html>