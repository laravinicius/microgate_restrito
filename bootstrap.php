<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

/**
 * Sessão: configurações só podem ser feitas ANTES da sessão iniciar.
 * Então: aplica ini_set apenas se ainda não iniciou.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '28800');
    ini_set('session.cookie_lifetime', '28800');

    session_start();
}

require_once APP_ROOT . '/config/database.php';
