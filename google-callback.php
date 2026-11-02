<?php
session_start();
require __DIR__ . '/config.php';        // $pdo
$cfg = require __DIR__ . '/google-config.php';

// 1) Verifikasi state (anti CSRF)
if (
    empty($_GET['state']) ||
    empty($_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])
) {
    die('State OAuth tidak valid.');
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    header('Location: login.php');
    exit;
}

// 2) Tukar code → access token
$post = http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'redirect_uri'  => $cfg['redirect_uri'],
    'grant_type'    => 'authorization_code',
]);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenResp = curl_exec($ch);
curl_close($ch);

$token = json_decode($tokenResp, true);
if (empty($token['access_token'])) {
    die('Gagal tukar code ke Google.');
}

// 3) Ambil profil user dari Google
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
]);
$profile = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($profile['sub']) || empty($profile['email'])) {
    die('Gagal mengambil profil Google.');
}

$googleId = $profile['sub'];
$email    = $profile['email'];
$name     = $profile['name'] ?? explode('@', $email)[0];

// 4) Cari user di DB berdasarkan google_id
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
$stmt->execute([$googleId]);
$user = $stmt->fetch();

if (!$user) {
    // Cek apakah sudah punya akun dengan email yang sama → hubungkan google_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET google_id = ? WHERE id_user = ?")
            ->execute([$googleId, $user['id_user']]);

        // pastikan data role/foto/bio tetap ada
        $user['google_id'] = $googleId;
    } else {
        // Buat akun baru otomatis
        $base = preg_replace('/[^a-zA-Z0-9._]/', '', strtolower(explode('@', $email)[0]));
        $base = substr($base, 0, 25);
        if (strlen($base) < 3) $base = 'user' . rand(100, 999);

        $i = 0;
        do {
            $username = $i === 0 ? $base : $base . $i;
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE nama = ? LIMIT 1");
            $stmt->execute([$username]);
            $i++;
        } while ($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "INSERT INTO users (nama, email, password, google_id, role) 
             VALUES (?, ?, NULL, ?, 'user')"
        );
        $stmt->execute([$username, $email, $googleId]);

        $user = [
            'id_user'     => (int) $pdo->lastInsertId(),
            'nama'        => $username,
            'username'    => $username,
            'role'        => 'user',
            'foto_profil' => 'default.png',
            'bio'         => '',
        ];
    }
}

// 5) Login — samakan nama session dengan login.php
session_regenerate_id(true);
$_SESSION['id_user']    = (int) $user['id_user'];
$_SESSION['nama']       = $user['nama']         ?? $name;
$_SESSION['username']   = $user['username']     ?? $name;
$_SESSION['role']       = $user['role']         ?? 'user';
$_SESSION['foto']       = $user['foto_profil']  ?? 'default.png';
$_SESSION['bio']        = $user['bio']          ?? '';
$_SESSION['login_time'] = time();

header('Location: index.php');
exit;