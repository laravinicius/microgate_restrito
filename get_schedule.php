<?php
session_start();
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// Autenticação simples
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Parâmetros: user_id ou username (opcional), start (YYYY-MM-DD), end (YYYY-MM-DD)
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$username = isset($_GET['username']) ? trim($_GET['username']) : null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

$isAdmin = ((int)($_SESSION['is_admin'] ?? 0) === 1);

// Se usuário não admin, força que só veja a própria escala
if (!$isAdmin) {
    $user_id = (int)$_SESSION['user_id'];
    $username = null;
}

// Datas / janela permitida (regra do dia 15 para técnico)
$tz = new DateTimeZone('America/Sao_Paulo');
$now = new DateTimeImmutable('now', $tz);

$allowedStartDt = $now->modify('first day of this month')->setTime(0, 0, 0);

if ((int)$now->format('d') >= 15) {
    // libera até o fim do próximo mês
    $allowedEndDt = $now->modify('last day of next month')->setTime(23, 59, 59);
} else {
    // só mês atual
    $allowedEndDt = $now->modify('last day of this month')->setTime(23, 59, 59);
}

// Datas padrão:
// - Admin: mantém padrão atual (mês atual até fim do mês atual + 2 meses)
// - Técnico: usa janela permitida (mês atual ou mês atual + próximo)
if (!$start || !$end) {
    if ($isAdmin) {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startDt = $nowUtc->modify('first day of this month')->setTime(0, 0);
        $endDt = $startDt->modify('+2 months')->modify('last day of this month')->setTime(23, 59, 59);
        $start = $startDt->format('Y-m-d');
        $end = $endDt->format('Y-m-d');
    } else {
        $start = $allowedStartDt->format('Y-m-d');
        $end   = $allowedEndDt->format('Y-m-d');
    }
} else {
    // Se vier start/end e NÃO for admin, clampa na janela permitida
    if (!$isAdmin) {
        $reqStart = DateTimeImmutable::createFromFormat('Y-m-d', $start, $tz);
        $reqEnd   = DateTimeImmutable::createFromFormat('Y-m-d', $end, $tz);

        if (!$reqStart || !$reqEnd) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
            exit;
        }

        if ($reqStart < $allowedStartDt) $reqStart = $allowedStartDt;
        if ($reqEnd > $allowedEndDt)     $reqEnd   = $allowedEndDt;

        if ($reqEnd < $reqStart) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid range for current permission window']);
            exit;
        }

        $start = $reqStart->format('Y-m-d');
        $end   = $reqEnd->format('Y-m-d');
    }
}

try {
    $params = [];
    $sql = "SELECT s.id, s.user_id, u.username, s.date, s.shift, s.note
            FROM schedules s
            JOIN users u ON u.id = s.user_id
            WHERE s.date BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;

    if ($user_id) {
        $sql .= " AND s.user_id = ?";
        $params[] = $user_id;
    } elseif ($username) {
        $sql .= " AND u.username = ?";
        $params[] = $username;
    }

    $sql .= " ORDER BY s.date, u.username";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['start' => $start, 'end' => $end, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
}
