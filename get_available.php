<?php

require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Parâmetros: date=YYYY-MM-DD (opcional, padrão hoje)
$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Seleciona técnicos que possuem escala naquela data
    $stmt = $pdo->prepare("SELECT u.id, u.username, s.shift, s.note
                           FROM schedules s
                           JOIN users u ON u.id = s.user_id
                           WHERE s.date = ? AND u.is_active = 1
                           ORDER BY u.username");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll();

    echo json_encode(['date' => $date, 'available' => $rows]);
} catch (PDOException $e) {
    error_log('Erro em get_available.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
