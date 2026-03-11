<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_audit.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'desconhecido';

logAuthEvent($pdo, 'logout', $userId, $username, true);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /login.php', true, 302);
exit;
