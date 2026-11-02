<?php
/**
 * CSRF Protection Helper
 * Include file ini di setiap halaman yang punya form POST.
 * 
 * Cara pakai:
 *   1. include 'csrf.php';  (setelah session_start)
 *   2. Di dalam form HTML: <?= csrf_field(); ?>
 *   3. Di awal blok pemrosesan POST: verify_csrf();
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token kalau belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Output hidden input field CSRF ke dalam form.
 * Pemakaian: <?= csrf_field(); ?>
 */
function csrf_field() {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifikasi CSRF token dari request POST.
 * Panggil di AWAL blok pemrosesan POST.
 * Jika gagal → berhenti dengan HTTP 403.
 */
function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || !hash_equals($session_token, $token)) {
        http_response_code(403);
        die('
            <div style="font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:10px;margin:20px;max-width:500px;">
                <h3 style="margin-top:0;font-size:1.2rem;">⚠️ Akses Ditolak</h3>
                <p>CSRF token tidak valid atau sudah kadaluarsa.</p>
                <p>Silakan kembali ke halaman sebelumnya, refresh, dan coba lagi.</p>
                <a href="javascript:history.back()" style="display:inline-block;margin-top:10px;padding:8px 16px;background:#991b1b;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">← Kembali</a>
            </div>
        ');
    }
}