<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
    );

    ini_set('session.gc_maxlifetime', '28800');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 28800,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_ROOT . '/config/database.php';
