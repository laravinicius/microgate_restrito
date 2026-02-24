<?php

require __DIR__ . '/bootstrap.php';

// Bloqueia acesso se não estiver logado ou não for administrador
if (empty($_SESSION['user_id']) || (int)$_SESSION['is_admin'] !== 1) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: restricted.php');
    exit;
}

$user_id = (int)$_POST['user_id'];
$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$is_admin = (int)($_POST['is_admin'] ?? 0);
$csrf_token = (string)($_POST['csrf_token'] ?? '');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    header('Location: restricted.php?error=csrf');
    exit;
}

// Validações
if ($user_id <= 0 || empty($username) || strlen($username) < 3 || empty($full_name)) {
    header('Location: restricted.php?error=username_invalid');
    exit;
}

if (!in_array($is_admin, [0, 1, 2], true)) {
    header('Location: restricted.php?error=invalid_role');
    exit;
}

// Verifica se o username já existe (excepto o usuário sendo editado)
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$username, $user_id]);
if ($stmt->fetch()) {
    header('Location: restricted.php?error=username_exists');
    exit;
}

try {
    // Prepara a query de update
    if (!empty($new_password)) {
        // Se forneceu nova senha, valida e atualiza
        if (strlen($new_password) < 6) {
            header('Location: restricted.php?error=password_invalid');
            exit;
        }
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, password_hash = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$username, $full_name, $password_hash, $is_admin, $user_id]);
    } else {
        // Só atualiza username, full_name e is_admin, mantém a senha
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$username, $full_name, $is_admin, $user_id]);
    }

    header('Location: restricted.php?msg=user_updated');
    exit;
} catch (PDOException $e) {
    error_log('Erro em edit_user_post.php: ' . $e->getMessage());
    header('Location: restricted.php?error=db_error');
    exit;
}
?>
