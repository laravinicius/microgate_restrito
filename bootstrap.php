<?php
declare(strict_types=1);

// Define o fuso horário para as funções de data do PHP
date_default_timezone_set('America/Sao_Paulo');

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

// Sincroniza o fuso horário da sessão do Banco de Dados com o horário de Brasília
$pdo->exec("SET time_zone = '-03:00'");
