<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Somente admin nível 1 pode editar escalas
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

if ((int)($_SESSION['is_admin'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso restrito a administradores.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Corpo da requisição inválido.']);
    exit;
}

// Validação CSRF
$csrfSent    = (string)($body['csrf_token'] ?? '');
$csrfSession = (string)($_SESSION['csrf_token'] ?? '');

if ($csrfSession === '' || !hash_equals($csrfSession, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

$userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
$date   = trim((string)($body['date']  ?? ''));
$shift  = trim((string)($body['shift'] ?? ''));

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'user_id inválido.']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

$shiftUpper    = $shift !== '' ? strtoupper($shift) : '';
$allowedShifts = ['AGENDA', 'FOLGA', 'SEM AGENDA', 'FÉRIAS', 'FERIAS', 'AUSENTE', ''];

if (!in_array($shiftUpper, $allowedShifts, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status inválido: ' . $shiftUpper]);
    exit;
}

// Normaliza FERIAS → FÉRIAS
if ($shiftUpper === 'FERIAS') {
    $shiftUpper = 'FÉRIAS';
}

if ($shiftUpper === 'SEM AGENDA') {
    $shiftUpper = 'FOLGA';
}

// Verifica se o usuário existe
$stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$stmtCheck->execute([$userId]);
if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

try {
    if ($shiftUpper === '') {
        // Remover registro
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $date]);
        echo json_encode(['success' => true, 'action' => 'removed', 'date' => $date]);
    } else {
        // Upsert: apaga o existente e insere o novo
        $pdo->beginTransaction();

        $del = $pdo->prepare("DELETE FROM schedules WHERE user_id = ? AND date = ?");
        $del->execute([$userId, $date]);

        $ins = $pdo->prepare("INSERT INTO schedules (user_id, date, shift, note) VALUES (?, ?, ?, '')");
        $ins->execute([$userId, $date, $shiftUpper]);

        $pdo->commit();
        echo json_encode(['success' => true, 'action' => 'saved', 'date' => $date, 'shift' => $shiftUpper]);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('save_schedule_day.php PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados.']);
}
