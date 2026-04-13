<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/auth_audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('login.php'));
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$maxAttempts   = 8;
$windowMinutes = 15;
$clientIp      = (string)($_SERVER['REMOTE_ADDR'] ?? '');

try {
    ensureAuthAuditTable($pdo);
    $stmtRate = $pdo->prepare(
        "SELECT COUNT(*) FROM auth_access_logs
         WHERE ip_address = ?
           AND success = 0
           AND created_at > NOW() - INTERVAL ? MINUTE"
    );
    $stmtRate->execute([$clientIp, $windowMinutes]);
    $failedAttempts = (int)$stmtRate->fetchColumn();
} catch (PDOException $e) {
    error_log("Erro ao verificar rate limit: " . $e->getMessage());
    $failedAttempts = 0;
}

if ($failedAttempts >= $maxAttempts) {
    logAuthEvent($pdo, 'login_rate_limited', null, $username, false,
        "Muitas tentativas em janela de {$windowMinutes} minutos (IP: {$clientIp})");
    header('Location: ' . route_url('login.php?error=2'));
    exit;
}

if ($username === '' || $password === '') {
    logAuthEvent($pdo, 'login_failed', null, $username, false, 'Credenciais ausentes');
    header('Location: ' . route_url('login.php?error=1'));
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, username, full_name, password_hash, is_admin, is_active, allow_fuel
         FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Erro de banco de dados no login: " . $e->getMessage());
    header('Location: ' . route_url('login.php?error=1'));
    exit;
}

if (!$user || (int)($user['is_active'] ?? 0) !== 1 || !password_verify($password, $user['password_hash'])) {
    logAuthEvent($pdo, 'login_failed', $user ? (int)$user['id'] : null, $username, false,
        'Usuário/senha inválidos ou conta inativa');
    header('Location: ' . route_url('login.php?error=1'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['is_admin']  = (int)$user['is_admin'];
$_SESSION['allow_fuel'] = (int)($user['allow_fuel'] ?? 0);

logAuthEvent($pdo, 'login_success', (int)$user['id'], $user['username'], true);

$level = (int)$user['is_admin'];

if ($level >= 1) {
    header('Location: ' . route_url('restricted.php'));
} else {
    header('Location: ' . route_url('escala.php'));
}
exit;
