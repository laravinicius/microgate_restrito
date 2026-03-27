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
$phone    = trim((string)($_POST['phone'] ?? ''));

// Resposta genérica usada em TODOS os casos de erro para não vazar informação
// (F-03 FIX: não revelar se usuário existe ou não)
$genericSuccess = '/login.php?forgot_msg=requested';

if ($username === '' || strlen($username) < 3 || strlen($username) > 100) {
    header('Location: /login.php?forgot_error=invalid_input');
    exit;
}

if ($phone === '' || strlen($phone) > 30 || !preg_match('/^[0-9+\-\s()]{8,30}$/', $phone)) {
    header('Location: /login.php?forgot_error=invalid_input');
    exit;
}

// -------------------------------------------------------------------------
// F-04 FIX: Rate limiting por IP para recuperação de senha.
// Máximo de 5 solicitações por IP a cada 60 minutos.
// -------------------------------------------------------------------------
$clientIp           = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$maxResetAttempts   = 5;
$resetWindowMinutes = 60;

try {
    $stmtRate = $pdo->prepare(
        "SELECT COUNT(*) FROM password_reset_requests
         WHERE ip_address = ?
           AND created_at > NOW() - INTERVAL ? MINUTE"
    );
    $stmtRate->execute([$clientIp, $resetWindowMinutes]);
    $recentAttempts = (int)$stmtRate->fetchColumn();
} catch (PDOException $e) {
    // Coluna ip_address pode não existir ainda; ignorar e continuar
    $recentAttempts = 0;
}

if ($recentAttempts >= $maxResetAttempts) {
    // Retorna a mesma mensagem de sucesso para não revelar o bloqueio
    header('Location: ' . $genericSuccess);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

// F-03 FIX: Se o usuário não existir, retornamos a mensagem genérica de
// "solicitação registrada" para não permitir enumeração de usernames.
if (!$user) {
    header('Location: ' . $genericSuccess);
    exit;
}

try {
    $insert = $pdo->prepare(
        "INSERT INTO password_reset_requests (user_id, username, phone, ip_address, status)
         VALUES (:user_id, :username, :phone, :ip_address, 'pending')"
    );
    $insert->execute([
        ':user_id'    => (int)$user['id'],
        ':username'   => (string)$user['username'],
        ':phone'      => $phone,
        ':ip_address' => $clientIp,
    ]);
} catch (PDOException $e) {
    // Fallback sem ip_address caso a coluna ainda não exista
    $insert = $pdo->prepare(
        "INSERT INTO password_reset_requests (user_id, username, phone, status)
         VALUES (:user_id, :username, :phone, 'pending')"
    );
    $insert->execute([
        ':user_id'  => (int)$user['id'],
        ':username' => (string)$user['username'],
        ':phone'    => $phone,
    ]);
}

header('Location: ' . $genericSuccess);
exit;
