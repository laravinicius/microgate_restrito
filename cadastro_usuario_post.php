<?php

require __DIR__ . '/bootstrap.php';

// F-06 FIX: usar helper centralizado
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        // F-05 FIX: usar redirect em vez de echo com dados em script inline
        header('Location: restricted.php?error=csrf');
        exit;
    }

    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $is_admin  = (int)($_POST['is_admin'] ?? 0);

    $error = '';

    // Validações — F-09 FIX: mínimo de 8 caracteres para senha
    if (empty($username)) {
        $error = 'username_empty';
    } elseif (empty($full_name)) {
        $error = 'fullname_empty';
    } elseif (empty($password)) {
        $error = 'password_empty';
    } elseif (strlen($username) < 3) {
        $error = 'username_short';
    } elseif (strlen($password) < 8) {
        $error = 'password_short';
    } elseif (!in_array($is_admin, [0, 1, 2], true)) {
        $error = 'invalid_role';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = 'username_exists';
        }
    }

    if (!empty($error)) {
        // F-05 FIX: redirecionar com código de erro em vez de embutir dados
        // do servidor em alert() JavaScript (evita XSS)
        header('Location: restricted.php?error=' . urlencode($error));
        exit;
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, full_name, password_hash, is_admin, is_active)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$username, $full_name, $hash, $is_admin]);
        header('Location: restricted.php?msg=user_created');
    } catch (PDOException $e) {
        error_log('Erro em cadastro_usuario_post.php: ' . $e->getMessage());
        header('Location: restricted.php?error=db_error');
    }
    exit;
} else {
    header('Location: restricted.php');
    exit;
}
