<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$maxAttempts = 8;
$windowSeconds = 15 * 60;
$now = time();

if (!isset($_SESSION['login_rate']) || !is_array($_SESSION['login_rate'])) {
    $_SESSION['login_rate'] = ['count' => 0, 'window_start' => $now];
}

$rate = &$_SESSION['login_rate'];
if (($now - (int)$rate['window_start']) > $windowSeconds) {
    $rate = ['count' => 0, 'window_start' => $now];
}

if ((int)$rate['count'] >= $maxAttempts) {
    logAuthEvent($pdo, 'login_rate_limited', null, $username, false, 'Muitas tentativas em janela de 15 minutos');
    header('Location: /login.php?error=2');
    exit;
}

if ($username === '' || $password === '') {
    $rate['count']++;
    logAuthEvent($pdo, 'login_failed', null, $username, false, 'Credenciais ausentes');
    header('Location: /login.php?error=1');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, full_name, password_hash, is_admin, is_active FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
    $rate['count']++;
    logAuthEvent($pdo, 'login_failed', $user ? (int)$user['id'] : null, $username, false, 'Usuário/senha inválidos ou conta inativa');
    header('Location: /login.php?error=1');
    exit;
}

session_regenerate_id(true);
unset($_SESSION['login_rate']);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['is_admin'] = (int)$user['is_admin'];
logAuthEvent($pdo, 'login_success', (int)$user['id'], $user['username'], true);

// Redireciona para a página apropriada baseado no tipo de usuário
if ((int)$user['is_admin'] >= 1) {
    header('Location: /restricted.php');
} else {
    header('Location: /escala.php');
}
exit;
