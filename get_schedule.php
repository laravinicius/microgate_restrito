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

// Se usuário não admin, força que só veja a própria escala
if ((int)($_SESSION['is_admin'] ?? 0) !== 1) {
    $user_id = (int)$_SESSION['user_id'];
    $username = null;
}

// Se nem user_id nem username informados, deixar como todos (apenas para admin)

// Datas padrão: primeiro dia do mês atual até último dia do mês atual + 2 meses
if (!$start || !$end) {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $startDt = $now->modify('first day of this month')->setTime(0,0);
    $endDt = $startDt->modify('+2 months')->modify('last day of this month')->setTime(23,59,59);
    $start = $startDt->format('Y-m-d');
    $end = $endDt->format('Y-m-d');
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

    // Feriados no range (camada separada da escala)
    $hstmt = $pdo->prepare("
        SELECT date, name
        FROM holidays
        WHERE is_active = 1
          AND date BETWEEN ? AND ?
        ORDER BY date
    ");
    $hstmt->execute([$start, $end]);
    $holidays = $hstmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'start' => $start,
        'end' => $end,
        'data' => $rows,
        'holidays' => $holidays
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
}
