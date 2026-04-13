<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('gerenciamento_usuarios.php'));
    exit;
}

$user_id      = (int)$_POST['user_id'];
$username     = trim($_POST['username'] ?? '');
$full_name    = trim($_POST['full_name'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$is_admin     = (int)($_POST['is_admin'] ?? 0);
$allow_fuel   = (int)($_POST['allow_fuel'] ?? 0);
$csrf_token   = (string)($_POST['csrf_token'] ?? '');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
    exit;
}

if ($user_id <= 0 || empty($username) || strlen($username) < 3 || empty($full_name)) {
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=username_invalid'));
    exit;
}

if (!in_array($is_admin, [0, 1, 2], true)) {
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=invalid_role'));
    exit;
}

if (!in_array($allow_fuel, [0, 1], true)) {
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=invalid_fuel'));
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$username, $user_id]);
if ($stmt->fetch()) {
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=username_exists'));
    exit;
}

try {
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            header('Location: ' . route_url('gerenciamento_usuarios.php?error=password_invalid'));
            exit;
        }
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, password_hash=?, is_admin=?, allow_fuel=? WHERE id=?");
        $stmt->execute([$username, $full_name, $hash, $is_admin, $allow_fuel, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, is_admin=?, allow_fuel=? WHERE id=?");
        $stmt->execute([$username, $full_name, $is_admin, $allow_fuel, $user_id]);
    }

    header('Location: ' . route_url('gerenciamento_usuarios.php?msg=user_updated'));
    exit;
} catch (PDOException $e) {
    error_log('Erro em edit_user_post.php: ' . $e->getMessage());
    header('Location: ' . route_url('gerenciamento_usuarios.php?error=db_error'));
    exit;
}
