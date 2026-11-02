<?php
$APP_ENV = getenv('APP_ENV') ?: 'local';
$IS_PRODUCTION = ($APP_ENV === 'production');

error_reporting(E_ALL);
if ($IS_PRODUCTION) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

date_default_timezone_set('Asia/Jakarta');

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'kp_webnonton';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

function wc_db_error_page($message, $dbName, $isProduction) {
    error_log('[WizCinema DB ERROR] ' . $message);
    $detail = $isProduction ? '' : htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $detailHtml = $detail ? "<div class='error-detail'><strong>Detail:</strong> {$detail}</div>" : '';

    http_response_code(500);
    die("<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Koneksi Database Gagal - WizCinema</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, 'Segoe UI', sans-serif; background: #0B1120; color: #F1F5F9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; line-height: 1.6; }
        .error-card { background: #111A2E; border: 1px solid #EF4444; border-radius: 18px; padding: 36px 32px; max-width: 540px; width: 100%; box-shadow: 0 25px 60px rgba(0,0,0,0.5); position: relative; overflow: hidden; }
        .error-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #EF4444, #DC2626); }
        .error-icon { width: 60px; height: 60px; border-radius: 50%; background: rgba(239,68,68,0.15); border: 2px solid rgba(239,68,68,0.4); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 20px; }
        h2 { color: #FCA5A5; font-size: 1.3rem; margin-bottom: 12px; font-weight: 800; }
        p { color: #94A3B8; font-size: 0.9rem; margin-bottom: 14px; }
        code { background: #0B1120; padding: 2px 8px; border-radius: 4px; color: #FCD34D; font-family: 'Courier New', monospace; font-size: 0.85rem; border: 1px solid #1E293B; }
        ul { margin: 8px 0 20px 20px; color: #CBD5E1; font-size: 0.88rem; }
        ul li { margin-bottom: 6px; }
        .hint { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 10px; padding: 16px 18px; color: #93C5FD; font-size: 0.85rem; margin-bottom: 14px; }
        .hint strong { color: #60A5FA; }
        .error-detail { background: #0B1120; border: 1px solid #334155; border-radius: 8px; padding: 12px 14px; color: #94A3B8; font-family: 'Courier New', monospace; font-size: 0.78rem; margin-top: 16px; word-break: break-word; }
        .error-detail strong { color: #FCA5A5; }
    </style>
</head>
<body>
    <div class='error-card'>
        <div class='error-icon'>⚠️</div>
        <h2>Koneksi Database Gagal</h2>
        <p>Server tidak dapat terhubung ke database <code>" . htmlspecialchars($dbName) . "</code>.</p>
        <p><strong style='color:#FCA5A5;'>Kemungkinan penyebab:</strong></p>
        <ul>
            <li>MySQL / MariaDB di Laragon <strong>belum dijalankan</strong></li>
            <li>Database <code>" . htmlspecialchars($dbName) . "</code> belum dibuat di phpMyAdmin</li>
            <li>Kredensial di <code>config.php</code> tidak sesuai</li>
        </ul>
        <div class='hint'>
            💡 <strong>Solusi cepat:</strong><br>
            1. Buka <strong>Laragon</strong> → klik <strong>Start All</strong><br>
            2. Buka <code>http://localhost/phpmyadmin</code> → pastikan database <code>" . htmlspecialchars($dbName) . "</code> ada<br>
            3. Refresh halaman ini
        </div>
        {$detailHtml}
    </div>
</body>
</html>");
}

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
} catch (Throwable $e) {
    wc_db_error_page($e->getMessage(), $DB_NAME, $IS_PRODUCTION);
}

if ($conn->connect_error) {
    wc_db_error_page($conn->connect_error, $DB_NAME, $IS_PRODUCTION);
}

if (!$conn->set_charset('utf8mb4')) {
    wc_db_error_page('Gagal set charset: ' . $conn->error, $DB_NAME, $IS_PRODUCTION);
}

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    wc_db_error_page($e->getMessage(), $DB_NAME, $IS_PRODUCTION);
}

define('WC_APP_ENV', $APP_ENV);
define('WC_IS_PRODUCTION', $IS_PRODUCTION);