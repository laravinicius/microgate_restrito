<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
        exit;
    }

    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $is_admin  = (int)($_POST['is_admin'] ?? 0);
    $error     = '';

    if (empty($username))               $error = 'username_empty';
    elseif (empty($full_name))          $error = 'fullname_empty';
    elseif (empty($password))           $error = 'password_empty';
    elseif (strlen($username) < 3)      $error = 'username_short';
    elseif (strlen($password) < 8)      $error = 'password_short';
    elseif (!in_array($is_admin,[0,1,2],true)) $error = 'invalid_role';
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) $error = 'username_exists';
    }

    if (!empty($error)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=' . urlencode($error)));
        exit;
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, full_name, password_hash, is_admin, is_active) VALUES (?,?,?,?,1)"
        );
        $stmt->execute([$username, $full_name, $hash, $is_admin]);
        header('Location: ' . route_url('gerenciamento_usuarios.php?msg=user_created'));
    } catch (PDOException $e) {
        error_log('Erro em cadastro_usuario_post.php: ' . $e->getMessage());
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=db_error'));
    }
    exit;
}

header('Location: ' . route_url('gerenciamento_usuarios.php'));
exit;
