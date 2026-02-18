<?php
session_start();
require __DIR__ . '/bootstrap.php';

// Bloqueia acesso se não estiver logado ou não for administrador
if (empty($_SESSION['user_id']) || $_SESSION['is_admin'] !== 1) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: restricted.php');
    exit;
}

$user_id = (int)$_POST['user_id'];
$username = trim($_POST['username'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$is_admin = (int)($_POST['is_admin'] ?? 0);

// Validações
if (empty($username) || strlen($username) < 3) {
    header('Location: restricted.php?error=username_invalid');
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
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$username, $password_hash, $is_admin, $user_id]);
    } else {
        // Só atualiza username e is_admin, mantém a senha
        $stmt = $pdo->prepare("UPDATE users SET username = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$username, $is_admin, $user_id]);
    }

    header('Location: restricted.php?msg=user_updated');
    exit;
} catch (PDOException $e) {
    header('Location: restricted.php?error=db_error');
    exit;
}
?>
