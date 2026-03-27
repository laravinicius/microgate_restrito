<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function manualJsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function manualJsonOk(array $data = []): void
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

requireLogin();
if (!isAdmin() && !isKmManager()) {
    manualJsonError('Acesso restrito.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    manualJsonError('Metodo invalido.', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    manualJsonError('Corpo da requisicao invalido.');
}

$csrfToken = (string)($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    manualJsonError('Token de seguranca invalido.', 403);
}

$logDate = trim((string)($body['log_date'] ?? ''));
$userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
$kmStart = isset($body['km_start']) ? (int)$body['km_start'] : -1;
$kmEnd = isset($body['km_end']) ? (int)$body['km_end'] : -1;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
    manualJsonError('Data invalida.');
}

if ($userId <= 0) {
    manualJsonError('Tecnico invalido.');
}

if ($kmStart < 0 || $kmEnd < 0) {
    manualJsonError('KM invalido.');
}

if ($kmEnd < $kmStart) {
    manualJsonError('KM final nao pode ser menor que o KM inicial.');
}

$userStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND is_admin = 0 AND is_active = 1 LIMIT 1");
$userStmt->execute([':id' => $userId]);
if (!$userStmt->fetch()) {
    manualJsonError('Tecnico nao encontrado.');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO mileage_logs (user_id, log_date, km_start, km_end, updated_at)
         VALUES (:uid, :log_date, :km_start, :km_end, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE
            km_start = VALUES(km_start),
            km_end = VALUES(km_end),
            updated_at = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        ':uid' => $userId,
        ':log_date' => $logDate,
        ':km_start' => $kmStart,
        ':km_end' => $kmEnd,
    ]);
} catch (PDOException $e) {
    error_log('save_manual_km.php DB error: ' . $e->getMessage());
    manualJsonError('Erro ao salvar cadastro manual.', 500);
}

manualJsonOk([
    'log_date' => $logDate,
    'user_id' => $userId,
    'km_start' => $kmStart,
    'km_end' => $kmEnd,
    'km_driven' => $kmEnd - $kmStart,
]);
