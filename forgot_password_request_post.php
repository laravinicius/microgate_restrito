<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/forgot_password_requests.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

ensurePasswordResetRequestsTable($pdo);

$username = trim((string)($_POST['username'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));

if ($username === '' || strlen($username) < 3 || strlen($username) > 100) {
    header('Location: /login.php?forgot_error=invalid_username');
    exit;
}

if ($phone === '' || strlen($phone) > 30 || !preg_match('/^[0-9+\-\s()]{8,30}$/', $phone)) {
    header('Location: /login.php?forgot_error=invalid_phone');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /login.php?forgot_error=user_not_found');
    exit;
}

$insert = $pdo->prepare(
    "INSERT INTO password_reset_requests (user_id, username, phone, status)
     VALUES (:user_id, :username, :phone, 'pending')"
);

$insert->execute([
    ':user_id' => (int)$user['id'],
    ':username' => (string)$user['username'],
    ':phone' => $phone,
]);

header('Location: /login.php?forgot_msg=requested');
exit;
