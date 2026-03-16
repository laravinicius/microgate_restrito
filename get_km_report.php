<?php
declare(strict_types=1);

/**
 * get_km_report.php
 *
 * API GET — retorna registros de quilometragem para o painel do gerente.
 * Acesso: is_admin >= 1 apenas.
 *
 * Parâmetros GET opcionais:
 *   user_id    int    – filtra por técnico específico (0 = todos)
 *   date_from  string – data inicial YYYY-MM-DD (padrão: 30 dias atrás)
 *   date_to    string – data final   YYYY-MM-DD (padrão: hoje)
 */

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// ── Autenticação e permissão ──────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso restrito a gerentes.']);
    exit;
}

// ── Parâmetros e validação ────────────────────────────────────────────────────
$userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to']   ?? ''));

// Datas padrão: últimos 30 dias
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

// Garante que date_from <= date_to
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

// ── Query principal ───────────────────────────────────────────────────────────
$params = [':date_from' => $dateFrom, ':date_to' => $dateTo];
$userFilter = '';

if ($userId > 0) {
    $userFilter = ' AND ml.user_id = :user_id';
    $params[':user_id'] = $userId;
}

$sql = "
    SELECT
        ml.id,
        ml.log_date,
        ml.user_id,
        u.full_name,
        u.username,
        ml.km_start,
        ml.km_end,
        CASE
            WHEN ml.km_start IS NOT NULL AND ml.km_end IS NOT NULL
            THEN ml.km_end - ml.km_start
            ELSE NULL
        END AS km_driven,
        ml.photo_start,
        ml.photo_end,
        ml.created_at,
        ml.updated_at
    FROM mileage_logs ml
    INNER JOIN users u ON u.id = ml.user_id
    WHERE ml.log_date BETWEEN :date_from AND :date_to
    $userFilter
    ORDER BY ml.log_date DESC, u.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Monta resposta ────────────────────────────────────────────────────────────
$records = [];
foreach ($rows as $row) {
    $records[] = [
        'id'          => (int)$row['id'],
        'log_date'    => $row['log_date'],
        'user_id'     => (int)$row['user_id'],
        'full_name'   => $row['full_name'] ?: $row['username'],
        'username'    => $row['username'],
        'km_start'    => $row['km_start'] !== null ? (int)$row['km_start'] : null,
        'km_end'      => $row['km_end']   !== null ? (int)$row['km_end']   : null,
        'km_driven'   => $row['km_driven'] !== null ? (int)$row['km_driven'] : null,
        'has_photo_start' => !empty($row['photo_start']),
        'has_photo_end'   => !empty($row['photo_end']),
        'photo_start_url' => !empty($row['photo_start'])
            ? 'serve_km_photo.php?file=' . urlencode($row['photo_start'])
            : null,
        'photo_end_url'   => !empty($row['photo_end'])
            ? 'serve_km_photo.php?file=' . urlencode($row['photo_end'])
            : null,
    ];
}

// ── Resumo por técnico (para cards de totais) ─────────────────────────────────
$summaryMap = [];
foreach ($records as $r) {
    $uid = $r['user_id'];
    if (!isset($summaryMap[$uid])) {
        $summaryMap[$uid] = [
            'user_id'       => $uid,
            'full_name'     => $r['full_name'],
            'total_driven'  => 0,
            'days_recorded' => 0,
            'days_complete' => 0,   // tem km_start E km_end
        ];
    }
    $summaryMap[$uid]['days_recorded']++;
    if ($r['km_driven'] !== null) {
        $summaryMap[$uid]['total_driven']  += $r['km_driven'];
        $summaryMap[$uid]['days_complete']++;
    }
}

echo json_encode([
    'success'   => true,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'total'     => count($records),
    'records'   => $records,
    'summary'   => array_values($summaryMap),
]);
exit;
