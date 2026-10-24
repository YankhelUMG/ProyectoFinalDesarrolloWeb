<?php
declare(strict_types=1);
require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/.env');
// Secure cookie settings work on both local HTTP (XAMPP) and HTTPS deployments.
if (session_status() === PHP_SESSION_NONE) {
    // Accept only server-managed cookies and reject uninitialized session IDs.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
