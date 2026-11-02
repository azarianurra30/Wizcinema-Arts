<?php
session_start();
require __DIR__ . '/config.php';
include 'csrf.php';

if (!empty($_SESSION['id_user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$old_username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $old_username = $username;

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password']) {
            $_SESSION['id_user']  = (int) $user['id_user'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'] ?? 'user';
            $_SESSION['foto']     = $user['foto_profil'] ?? 'default.png';
            $_SESSION['bio']      = $user['bio'] ?? '';

            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | WizCinema</title>
    <link rel="icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{--navy:#091426;--navy-2:#0d1a2e;--blue:#3987ff;--yellow:#ffd447;--gold:#f5b921;--white:#f7f9ff;--muted:#9aaac1;--line:rgba(255,255,255,.10);--input:#0f1e33;--input-b:#243a50;}
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;display:grid;place-items:center;padding:24px;font-family:'DM Sans',sans-serif;color:var(--white);background:var(--navy)}
        .login-card{width:min(920px,100%);display:grid;grid-template-columns:.92fr 1.08fr;overflow:hidden;border:1px solid var(--line);border-radius:22px;background:var(--navy-2);box-shadow:0 28px 70px rgba(0,0,0,.28)}
        .brand-panel{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:space-between;min-height:620px;padding:34px;overflow:hidden;background:linear-gradient(155deg,#173b69,#10213a 65%,#0c192c)}
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
        .form-head{margin-bottom:24px}
        .form-head .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 11px;border:1px solid rgba(255,212,71,.25);border-radius:6px;background:rgba(255,212,71,.07);color:var(--yellow);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px}
        .form-head .eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--yellow)}
        .form-head h2{font-size:27px;line-height:1.25;letter-spacing:-.8px}
        .form-head h2 span{color:var(--yellow)}
        .form-head p{margin-top:10px;color:var(--muted);font-size:13px;line-height:1.7}
        .alert-error{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;margin-bottom:18px;border:1px solid rgba(255,84,84,.30);border-radius:10px;background:rgba(255,84,84,.08);color:#ff9b9b;font-size:12px;line-height:1.5}
        .field{margin-bottom:16px}
        .field label{display:block;margin-bottom:8px;font-size:11px;font-weight:700;letter-spacing:.6px;color:#c6d3e6;text-transform:uppercase}
        .input-wrap{position:relative;display:flex;align-items:center}
        .input-wrap > i.lead{position:absolute;left:14px;color:#7c8fab;font-size:14px;pointer-events:none;transition:color .2s}
        .input-wrap:focus-within > i.lead{color:var(--yellow)}
        .input-wrap input{width:100%;height:50px;padding:0 46px 0 42px;border:1.5px solid var(--input-b);border-radius:11px;background:var(--input);color:var(--white);font-family:inherit;font-size:13.5px;outline:none;transition:border-color .2s,box-shadow .2s}
        .input-wrap input::placeholder{color:#66788f}
        .input-wrap input:focus{border-color:var(--yellow);box-shadow:0 0 0 4px rgba(255,212,71,.10)}
        .toggle-pass{position:absolute;right:10px;width:34px;height:34px;display:grid;place-items:center;border:none;border-radius:8px;background:transparent;color:#7c8fab;cursor:pointer;font-size:14px;transition:color .2s,background .2s}
        .toggle-pass:hover{color:var(--yellow);background:rgba(255,212,71,.08)}
        .form-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 22px;flex-wrap:wrap}
        .checkbox{display:inline-flex;align-items:center;gap:9px;cursor:pointer;font-size:12px;color:#c6d3e6;user-select:none}
        .checkbox input{position:absolute;opacity:0;width:0;height:0}
        .checkbox .box{width:18px;height:18px;display:grid;place-items:center;border:1.5px solid var(--input-b);border-radius:5px;background:var(--input);color:transparent;font-size:10px;transition:all .2s}
        .checkbox input:checked + .box{background:var(--yellow);border-color:var(--yellow);color:#091426}
        .link-soft{color:var(--yellow);font-size:12px;font-weight:600;text-decoration:none}
        .link-soft:hover{text-decoration:underline}
        .btn-login{width:100%;min-height:52px;display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 18px;border:none;border-radius:11px;background:linear-gradient(135deg,var(--yellow),var(--gold));color:#091426;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:transform .15s,box-shadow .2s,filter .2s;box-shadow:0 12px 26px rgba(245,185,33,.28)}
        .btn-login:hover{filter:brightness(1.05);transform:translateY(-1px)}
        .register-note{text-align:center;margin-top:20px;font-size:13px;color:var(--muted)}
        .register-note a{color:var(--yellow);font-weight:700;text-decoration:none}
        .register-note a:hover{text-decoration:underline}
        .back-link{display:inline-flex;align-items:center;gap:8px;margin-top:18px;color:#8fa0b8;font-size:11.5px;text-decoration:none}
        .back-link:hover{color:var(--yellow)}
        @media (max-width:760px){body{padding:16px}.login-card{max-width:440px;grid-template-columns:1fr;border-radius:16px}.brand-panel{min-height:auto;padding:26px 24px}.brand-content{margin:26px 0 10px}.brand-content h1{font-size:26px}.brand-content p{font-size:12px}.brand-features,.panel-footer{display:none}.form-panel{padding:28px 22px 30px}.form-head h2{font-size:23px}}
    </style>
</head>
<body>
<main class="login-card">
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
            <div class="tagline">SELAMAT DATANG KEMBALI</div>
            <h1>Lanjutkan<br><span>cerita filmmu.</span></h1>
            <p>Masuk untuk melanjutkan menonton dan mengelola tiket bioskop favoritmu.</p>
            <div class="brand-features">
                <div class="feat"><i class="fa-solid fa-ticket"></i><span>Pesan tiket lebih cepat</span></div>
                <div class="feat"><i class="fa-solid fa-bookmark"></i><span>Simpan watchlist favoritmu</span></div>
                <div class="feat"><i class="fa-solid fa-shield-halved"></i><span>Data akun aman</span></div>
            </div>
        </div>
        <div class="panel-footer">&copy; <?= date('Y'); ?> WizCinema</div>
    </section>

    <section class="form-panel">
        <div class="form-head">
            <div class="eyebrow"><span class="dot"></span>Masuk Akun</div>
            <h2>Halo lagi! <span>Masuk</span> dulu yuk</h2>
            <p>Gunakan username dan password akun WizCinema kamu.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            <?= csrf_field(); ?>

            <div class="field">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-user lead"></i>
                    <input type="text" id="username" name="username" placeholder="Username"
                           autocapitalize="none" spellcheck="false"
                           value="<?= htmlspecialchars($old_username); ?>" required>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock lead"></i>
                    <input type="password" id="password" name="password" placeholder="Password"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" id="togglePass"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>

            <div class="form-row">
                <label class="checkbox">
                    <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : ''; ?>>
                    <span class="box"><i class="fa-solid fa-check"></i></span>
                    Ingat saya
                </label>
            </div>

            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
            </button>
        </form>

        <div class="register-note">
            Belum punya akun? <a href="register.php">Daftar gratis</a>
        </div>
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke beranda</a>
    </section>
</main>

<script>
    (function () {
        const btn = document.getElementById('togglePass');
        const input = document.getElementById('password');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.innerHTML = hidden ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    })();
</script>
</body>
</html>