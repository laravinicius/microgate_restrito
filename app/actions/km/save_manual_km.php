<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

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

function recalculateShiftKm(PDO $pdo, int $userId, string $logDate): void
{
    $rowStmt = $pdo->prepare(
        "SELECT id, km_start, km_end
         FROM mileage_logs
         WHERE user_id = :uid AND log_date = :log_date
         LIMIT 1"
    );
    $rowStmt->execute([
        ':uid' => $userId,
        ':log_date' => $logDate,
    ]);
    $row = $rowStmt->fetch();
    if (!$row) {
        return;
    }

    $kmStart = $row['km_start'] !== null ? (int)$row['km_start'] : null;
    $kmEnd = $row['km_end'] !== null ? (int)$row['km_end'] : null;

    $prevKmStmt = $pdo->prepare(
        "SELECT km_end, km_start
         FROM mileage_logs
         WHERE user_id = :uid
           AND log_date < :log_date
           AND (km_end IS NOT NULL OR km_start IS NOT NULL)
         ORDER BY log_date DESC, saved_at_end DESC, id DESC
         LIMIT 1"
    );
    $prevKmStmt->execute([
        ':uid' => $userId,
        ':log_date' => $logDate,
    ]);
    $prevKmRow = $prevKmStmt->fetch();
    $prevReferenceKm = null;
    if ($prevKmRow) {
        if ($prevKmRow['km_end'] !== null) {
            $prevReferenceKm = (int)$prevKmRow['km_end'];
        } elseif ($prevKmRow['km_start'] !== null) {
            $prevReferenceKm = (int)$prevKmRow['km_start'];
        }
    }

    $kmOutsideShift = null;
    if ($kmStart !== null) {
        $kmOutsideShift = $prevReferenceKm !== null ? abs($kmStart - $prevReferenceKm) : 0;
    }

    $kmInsideShift = null;
    if ($kmStart !== null && $kmEnd !== null) {
        $kmDriven = $kmEnd - $kmStart;
        $kmInsideShift = max(0, $kmDriven - (int)($kmOutsideShift ?? 0));
    }

    $updateStmt = $pdo->prepare(
        "UPDATE mileage_logs
         SET km_outside_shift = :outside_shift,
             km_inside_shift = :inside_shift,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );
    $updateStmt->execute([
        ':outside_shift' => $kmOutsideShift,
        ':inside_shift' => $kmInsideShift,
        ':id' => (int)$row['id'],
    ]);
}

function recalculateNextShiftKm(PDO $pdo, int $userId, string $fromDate): void
{
    $nextStmt = $pdo->prepare(
        "SELECT log_date
         FROM mileage_logs
         WHERE user_id = :uid
           AND log_date > :log_date
         ORDER BY log_date ASC, id ASC
         LIMIT 1"
    );
    $nextStmt->execute([
        ':uid' => $userId,
        ':log_date' => $fromDate,
    ]);
    $nextRow = $nextStmt->fetch();
    if (!$nextRow || empty($nextRow['log_date'])) {
        return;
    }

    recalculateShiftKm($pdo, $userId, (string)$nextRow['log_date']);
}

requireLogin();
if (!isAdmin()) {
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

$userStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND is_admin = 0 AND is_active = 1 AND allow_fuel = 1 LIMIT 1");
$userStmt->execute([':id' => $userId]);
if (!$userStmt->fetch()) {
    manualJsonError('Tecnico nao encontrado ou sem permissao de abastecimento.');
}

try {
    $pdo->beginTransaction();

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

    recalculateShiftKm($pdo, $userId, $logDate);
    recalculateNextShiftKm($pdo, $userId, $logDate);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

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
