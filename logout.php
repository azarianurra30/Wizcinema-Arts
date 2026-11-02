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

// Bersihkan session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Lax',
    ]);
}

if (!empty($_COOKIE['wizcinema_remember'])) {
    setcookie('wizcinema_remember', '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sampai Jumpa | WizCinema</title>
    <link rel="icon" href="logo.png">
    <meta http-equiv="refresh" content="10; url=login.php">
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

        .session-box{display:flex;align-items:center;gap:14px;padding:16px 18px;margin-bottom:22px;background:rgba(55,214,160,.06);border:1px solid rgba(55,214,160,.25);border-radius:12px}
        .session-icon{width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,var(--green),#059669);display:grid;place-items:center;color:#fff;font-size:1rem;flex-shrink:0;box-shadow:0 6px 16px rgba(55,214,160,.3)}
        .session-text{flex:1;min-width:0}
        .session-text .title{font-size:13px;font-weight:800;color:var(--green)}
        .session-text .sub{font-size:11.5px;color:var(--muted);margin-top:3px}

        .action-buttons{display:flex;flex-direction:column;gap:12px}

        .btn-login{width:100%;min-height:52px;display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 18px;border:none;border-radius:11px;background:linear-gradient(135deg,var(--yellow),var(--gold));color:#091426;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:transform .15s,box-shadow .2s,filter .2s;box-shadow:0 12px 26px rgba(245,185,33,.28);text-decoration:none}
        .btn-login:hover{filter:brightness(1.05);transform:translateY(-1px);color:#091426}

        .btn-secondary{width:100%;min-height:48px;display:flex;align-items:center;justify-content:center;gap:10px;padding:12px 18px;border:1.5px solid var(--input-b);border-radius:11px;background:transparent;color:#d8e2f1;font-family:inherit;font-size:13px;font-weight:600;text-decoration:none;transition:border-color .2s,background .2s}
        .btn-secondary:hover{border-color:#3987ff;background:rgba(57,135,255,.08);color:#fff}

        .redirect-loader{display:flex;align-items:center;gap:12px;padding:14px 16px;margin-top:20px;background:rgba(255,212,71,.05);border:1px solid rgba(255,212,71,.2);border-radius:10px;font-size:11.5px;color:var(--yellow)}
        .loader-ring{width:16px;height:16px;border-radius:50%;border:2px solid rgba(255,212,71,.25);border-top-color:var(--yellow);animation:spin .9s linear infinite;flex-shrink:0}
        @keyframes spin{to{transform:rotate(360deg)}}
        .progress-mini{flex:1;height:3px;background:rgba(255,212,71,.15);border-radius:999px;overflow:hidden;max-width:100px;margin-left:auto}
        .progress-mini::before{content:'';display:block;height:100%;width:100%;background:linear-gradient(90deg,var(--yellow),var(--gold));border-radius:999px;animation:countdown 10s linear forwards}
        @keyframes countdown{from{width:100%}to{width:0%}}

        @media (max-width:760px){
            body{padding:16px}
            .login-card{max-width:440px;grid-template-columns:1fr;border-radius:16px}
            .brand-panel{min-height:auto;padding:26px 24px}
            .brand-content{margin:26px 0 10px}
            .brand-content h1{font-size:26px}
            .brand-content p{font-size:12px}
            .brand-features,.panel-footer{display:none}
            .form-panel{padding:28px 22px 30px}
            .form-head h2{font-size:23px}
        }
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
            <div class="tagline">SESI BERAKHIR</div>
            <h1>Terima Kasih<br><span>Sudah Menonton.</span></h1>
            <p>Sampai jumpa di petualangan film berikutnya. Kami akan selalu menunggumu kembali.</p>
            <div class="brand-features">
                <div class="feat"><i class="fa-solid fa-clock-rotate-left"></i><span>Riwayat tiket tetap tersimpan</span></div>
                <div class="feat"><i class="fa-solid fa-gift"></i><span>Voucher Anda tetap aman</span></div>
                <div class="feat"><i class="fa-solid fa-shield-halved"></i><span>Data akun terlindungi</span></div>
            </div>
        </div>
        <div class="panel-footer">&copy; <?= date('Y'); ?> WizCinema</div>
    </section>

    <section class="form-panel">
        <div class="form-head">
            <div class="eyebrow"><span class="dot"></span>Logout Berhasil</div>
            <h2>Sampai Jumpa <span>Lagi</span> 👋</h2>
            <p>Anda telah keluar dari akun WizCinema dengan aman.</p>
        </div>

        <div class="session-box">
            <div class="session-icon"><i class="fa-solid fa-check"></i></div>
            <div class="session-text">
                <div class="title">Sesi Ditutup</div>
                <div class="sub">Data akun Anda tetap aman</div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="login.php" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Login Kembali
            </a>
            <a href="index.php" class="btn-secondary">
                <i class="fa-solid fa-house"></i> Kembali ke Beranda
            </a>
        </div>

        <div class="redirect-loader">
            <div class="loader-ring"></div>
            <span>Mengalihkan ke login</span>
            <div class="progress-mini"></div>
        </div>
    </section>
</main>
</body>
</html>