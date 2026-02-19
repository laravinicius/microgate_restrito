<?php
declare(strict_types=1);

// Configura a sessão para durar 8 horas (28800 segundos)
ini_set('session.gc_maxlifetime', '28800');
ini_set('session.cookie_lifetime', '28800');

session_start();

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

require_once APP_ROOT . '/config/database.php';
