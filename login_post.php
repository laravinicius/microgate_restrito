<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: /login.php?error=1');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin, is_active FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
    header('Location: /login.php?error=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['is_admin'] = (int)$user['is_admin'];

// Redireciona para a página apropriada baseado no tipo de usuário
if ((int)$user['is_admin'] >= 1) {
    header('Location: /restricted.php');
} else {
    header('Location: /escala.php');
}
exit;
