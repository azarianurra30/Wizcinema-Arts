<?php
/**
 * WizCinema - Configuration Template
 * 
 * CARA PAKAI:
 * 1. Copy file ini jadi config.php
 * 2. Isi $DB_* dengan kredensial database Anda
 * 3. JANGAN upload config.php (yang asli) ke GitHub
 */

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

// ===== ISI KREDENSIAL DATABASE ANDA DI SINI =====
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'nama_database_anda';
$DB_USER = getenv('DB_USER') ?: 'username_database';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'password_database';

// ... (copy seluruh isi config.php dari sini ke bawah, 
//      bagian function wc_db_error_page, mysqli, PDO)