<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function fuelReportError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isLoggedIn()) {
    fuelReportError('Nao autenticado.', 401);
}

if (!isAdmin()) {
    fuelReportError('Acesso restrito.', 403);
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($userId <= 0) {
    fuelReportError('Tecnico invalido.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    fuelReportError('Periodo invalido.');
}

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$techStmt = $pdo->prepare(
    "SELECT id, full_name, username
     FROM users
     WHERE id = :id AND is_admin = 0 AND is_active = 1 AND allow_fuel = 1
     LIMIT 1"
);
$techStmt->execute([':id' => $userId]);
$technician = $techStmt->fetch();
if (!$technician) {
    fuelReportError('Tecnico nao encontrado ou sem acesso a abastecimento.', 404);
}

$mileageStmt = $pdo->prepare(
    "SELECT
        log_date,
        km_start,
        km_end,
        photo_start,
        photo_end
     FROM mileage_logs
     WHERE user_id = :uid
       AND log_date BETWEEN :date_from AND :date_to"
);
$mileageStmt->execute([
    ':uid' => $userId,
    ':date_from' => $dateFrom,
    ':date_to' => $dateTo,
]);
$mileageRows = $mileageStmt->fetchAll();

$mileageByDate = [];
foreach ($mileageRows as $row) {
    $date = (string)$row['log_date'];
    $kmStart = $row['km_start'] !== null ? (int)$row['km_start'] : null;
    $kmEnd = $row['km_end'] !== null ? (int)$row['km_end'] : null;

    $mileageByDate[$date] = [
        'km_start' => $kmStart,
        'km_end' => $kmEnd,
        'km_driven' => ($kmStart !== null && $kmEnd !== null) ? ($kmEnd - $kmStart) : null,
        'km_start_by_tech' => $kmStart !== null && !empty($row['photo_start']),
        'km_end_by_tech' => $kmEnd !== null && !empty($row['photo_end']),
    ];
}

$fuelStmt = $pdo->prepare(
    "SELECT
        DATE(fueled_at) AS fuel_date,
        COUNT(*) AS entries_count,
        AVG(fuel_price) AS avg_fuel_price,
        SUM(liters) AS total_liters,
        SUM(total_amount) AS total_amount
     FROM fuel_logs
     WHERE user_id = :uid
       AND fueled_at BETWEEN :from_dt AND :to_dt
     GROUP BY DATE(fueled_at)"
);
$fuelStmt->execute([
    ':uid' => $userId,
    ':from_dt' => $dateFrom . ' 00:00:00',
    ':to_dt' => $dateTo . ' 23:59:59',
]);
$fuelRows = $fuelStmt->fetchAll();

$fuelByDate = [];
foreach ($fuelRows as $row) {
    $date = (string)$row['fuel_date'];
    $fuelByDate[$date] = [
        'entries_count' => (int)$row['entries_count'],
        'avg_fuel_price' => $row['avg_fuel_price'] !== null ? (float)$row['avg_fuel_price'] : null,
        'total_liters' => $row['total_liters'] !== null ? (float)$row['total_liters'] : null,
        'total_amount' => $row['total_amount'] !== null ? (float)$row['total_amount'] : null,
        'fuel_by_tech' => ((int)$row['entries_count']) > 0,
    ];
}

$records = [];
$totalKmDriven = 0;
$totalLiters = 0.0;
$totalAmount = 0.0;
$kmForKml = 0;
$litersForKml = 0.0;
$fuelDays = 0;

$cursor = new DateTimeImmutable($dateFrom);
$end = new DateTimeImmutable($dateTo);

while ($cursor <= $end) {
    $isoDate = $cursor->format('Y-m-d');
    $mileage = $mileageByDate[$isoDate] ?? null;
    $fuel = $fuelByDate[$isoDate] ?? null;

    $kmStart = $mileage['km_start'] ?? null;
    $kmEnd = $mileage['km_end'] ?? null;
    $kmDriven = $mileage['km_driven'] ?? null;

    $entriesCount = $fuel['entries_count'] ?? 0;
    $fuelPrice = $fuel['avg_fuel_price'] ?? null;
    $liters = $fuel['total_liters'] ?? null;
    $amount = $fuel['total_amount'] ?? null;

    if ($kmDriven !== null) {
        $totalKmDriven += $kmDriven;
    }
    if ($liters !== null) {
        $totalLiters += $liters;
    }
    if ($amount !== null) {
        $totalAmount += $amount;
    }
    if ($entriesCount > 0) {
        $fuelDays++;
    }

    if ($kmDriven !== null && $kmDriven > 0 && $liters !== null && $liters > 0) {
        $kmForKml += $kmDriven;
        $litersForKml += $liters;
    }

    $records[] = [
        'date' => $isoDate,
        'km_start' => $kmStart,
        'km_end' => $kmEnd,
        'km_driven' => $kmDriven,
        'had_fuel' => $entriesCount > 0,
        'fuel_price' => $fuelPrice,
        'liters' => $liters,
        'total_amount' => $amount,
        'entries_count' => $entriesCount,
        'km_start_by_tech' => (bool)($mileage['km_start_by_tech'] ?? false),
        'km_end_by_tech' => (bool)($mileage['km_end_by_tech'] ?? false),
        'fuel_by_tech' => (bool)($fuel['fuel_by_tech'] ?? false),
    ];

    $cursor = $cursor->modify('+1 day');
}

usort($records, static function (array $a, array $b): int {
    return strcmp($a['date'], $b['date']);
});

$averageFuelPrice = $totalLiters > 0 ? ($totalAmount / $totalLiters) : null;
$overallKml = $litersForKml > 0 ? ($kmForKml / $litersForKml) : null;

$fullName = (string)($technician['full_name'] ?: $technician['username']);

echo json_encode([
    'success' => true,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'technician' => [
        'id' => (int)$technician['id'],
        'full_name' => $fullName,
        'username' => (string)$technician['username'],
    ],
    'records' => $records,
    'totals' => [
        'total_km_driven' => $totalKmDriven,
        'fuel_days' => $fuelDays,
        'average_fuel_price' => $averageFuelPrice,
        'total_liters' => $totalLiters,
        'total_amount' => $totalAmount,
        'overall_kml' => $overallKml,
    ],
]);
