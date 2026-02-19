<?php
session_start();
require __DIR__ . '/bootstrap.php';

// Segurança: Apenas admin logado pode cadastrar
if (empty($_SESSION['user_id']) || $_SESSION['is_admin'] !== 1) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ação não autorizada (CSRF Token Inválido)');
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $error = '';

    // Validações
    if (empty($username)) {
        $error = 'Nome de usuário não pode estar vazio!';
    } elseif (empty($password)) {
        $error = 'Senha não pode estar vazia!';
    } elseif (strlen($username) < 3) {
        $error = 'Nome de usuário deve ter pelo menos 3 caracteres!';
    } elseif (strlen($password) < 6) {
        $error = 'Senha deve ter pelo menos 6 caracteres!';
    } else {
        // Verifica se username já existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Este nome de usuário já está cadastrado!';
        }
    }

    if (!empty($error)) {
        echo "<script>alert('Erro: {$error}'); window.location.href='restricted.php';</script>";
        exit;
    }

    // Se passou em todas as validações, inserir o usuário
    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin, is_active) VALUES (?, ?, 0, 1)");
        $stmt->execute([$username, $hash]);
        echo "<script>alert('Usuário criado com sucesso!'); window.location.href='restricted.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao criar usuário: " . addslashes($e->getMessage()) . "'); window.location.href='restricted.php';</script>";
    }
}