<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function jsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function jsonOk(array $data = []): void
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

if (!isLoggedIn()) jsonError('Não autenticado.', 401);
if (isAdmin())     jsonError('Acesso não permitido.', 403);
if (!hasFuelAccess()) jsonError('Acesso a abastecimento não permitido para este usuário.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método inválido.', 405);

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) jsonError('Corpo da requisição inválido.');

// CSRF
$csrfToken = (string)($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    jsonError('Token de segurança inválido. Recarregue a página.', 403);
}

$type    = (string)($body['type']     ?? '');
$km      = isset($body['km'])   ? (int)$body['km']   : -1;
$photo   = (string)($body['photo']    ?? '');
$logDate = (string)($body['log_date'] ?? '');

// Localização
$lat = isset($body['lat']) && is_numeric($body['lat']) ? (float)$body['lat'] : null;
$lng = isset($body['lng']) && is_numeric($body['lng']) ? (float)$body['lng'] : null;

// Validação de coordenadas — descarta se fora dos limites válidos
if ($lat !== null && ($lat < -90  || $lat > 90))  $lat = null;
if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;

if (!in_array($type, ['start', 'end'], true)) jsonError('Tipo inválido.');
if ($km < 0 || $km > 9_999_999)               jsonError('Quilometragem fora do intervalo (0–9.999.999).');

$dt = new DateTimeImmutable($logDate);
$serverToday = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) jsonError('Data inválida.');
if ($dt > $serverToday->modify('+1 day'))            jsonError('Não é permitido registrar datas futuras.');

if (empty($photo)) jsonError('Foto obrigatória.');

// Valida imagem
$imageData = base64_decode($photo, true);
if ($imageData === false || strlen($imageData) < 100)  jsonError('Imagem inválida ou corrompida.');
if (substr($imageData, 0, 3) !== "\xFF\xD8\xFF")       jsonError('Somente imagens JPEG são aceitas.');
if (strlen($imageData) > 3 * 1024 * 1024)              jsonError('Imagem muito grande. Máximo: 3 MB.');

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT id, km_start, km_end FROM mileage_logs WHERE user_id = :uid AND log_date = :date LIMIT 1"
);
$stmt->execute([':uid' => $userId, ':date' => $logDate]);
$existing = $stmt->fetch();

$lastEndStmt = $pdo->prepare(
    "SELECT km_end, log_date
     FROM mileage_logs
     WHERE user_id = :uid
       AND km_end IS NOT NULL
       AND log_date <= :date
     ORDER BY log_date DESC, saved_at_end DESC, id DESC
     LIMIT 1"
);
$lastEndStmt->execute([':uid' => $userId, ':date' => $logDate]);
$lastEnd = $lastEndStmt->fetch();

if ($type === 'start' && $existing && $existing['km_start'] !== null) {
    jsonError('KM inicial já registrado para hoje.');
}
if ($type === 'start' && $lastEnd && $lastEnd['km_end'] !== null && $km < (int)$lastEnd['km_end']) {
    jsonError(
        'KM inicial não pode ser menor que o último KM final (' .
        number_format((int)$lastEnd['km_end'], 0, ',', '.') .
        ') em ' . date('d/m/Y', strtotime((string)$lastEnd['log_date'])) . '.'
    );
}
if ($type === 'end') {
    if (!$existing || $existing['km_start'] === null) jsonError('Registre o KM inicial antes do final.');
    if ($existing['km_end'] !== null)                 jsonError('KM final já registrado para hoje.');
    if ($km < (int)$existing['km_start'])             jsonError('KM final não pode ser menor que o KM inicial (' . $existing['km_start'] . ').');
}

// Salva foto
$kmRootDir = dirname(__DIR__, 3) . '/uploads/km';
$baseDir   = $kmRootDir . '/' . $userId;

if (!is_dir($kmRootDir) && !mkdir($kmRootDir, 0775, true) && !is_dir($kmRootDir)) {
    error_log('save_km.php storage error: unable to create km root dir "' . $kmRootDir . '"');
    jsonError('Erro ao preparar armazenamento.', 500);
}

$kmHtaccess = $kmRootDir . '/.htaccess';
if (!file_exists($kmHtaccess)) {
    @file_put_contents(
        $kmHtaccess,
        "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    );
}

if (!is_dir($baseDir) && !mkdir($baseDir, 0750, true) && !is_dir($baseDir)) {
    $mkdirError = error_get_last();
    error_log(
        'save_km.php storage error: unable to create user dir "' . $baseDir . '"' .
        ($mkdirError ? ' - ' . $mkdirError['message'] : '')
    );
    jsonError('Erro ao preparar armazenamento.', 500);
}

$filename     = $logDate . '_' . $type . '.jpg';
$relativePath = 'uploads/km/' . $userId . '/' . $filename;
$fullPath     = dirname(__DIR__, 3) . '/' . $relativePath;

if (file_put_contents($fullPath, $imageData) === false) {
    jsonError('Erro ao salvar a foto.', 500);
}

// Persiste no banco
try {
    if ($type === 'start') {
        $sql = "INSERT INTO mileage_logs (user_id, log_date, km_start, photo_start, lat_start, lng_start, saved_at_start)
                VALUES (:uid, :date, :km, :photo, :lat, :lng, NOW())
                ON DUPLICATE KEY UPDATE
                    km_start       = VALUES(km_start),
                    photo_start    = VALUES(photo_start),
                    lat_start      = VALUES(lat_start),
                    lng_start      = VALUES(lng_start),
                    saved_at_start = VALUES(saved_at_start),
                    updated_at     = CURRENT_TIMESTAMP";
        $params = [
            ':uid'   => $userId,
            ':date'  => $logDate,
            ':km'    => $km,
            ':photo' => $relativePath,
            ':lat'   => $lat,
            ':lng'   => $lng,
        ];
    } else {
        $sql = "UPDATE mileage_logs
                SET km_end = :km, photo_end = :photo,
                    lat_end = :lat, lng_end = :lng,
                    saved_at_end = NOW(), updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :uid AND log_date = :date";
        $params = [
            ':km'    => $km,
            ':photo' => $relativePath,
            ':lat'   => $lat,
            ':lng'   => $lng,
            ':uid'   => $userId,
            ':date'  => $logDate,
        ];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

} catch (PDOException $e) {
    try {
        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $dbHost = (string)$pdo->query('SELECT @@hostname')->fetchColumn();
        $hasLatStart = $pdo->prepare("SHOW COLUMNS FROM mileage_logs LIKE 'lat_start'");
        $hasLatStart->execute();
        $latStartExists = $hasLatStart->fetch() ? 'yes' : 'no';

        error_log(
            'save_km.php DB error: ' . $e->getMessage() .
            ' | database=' . $dbName .
            ' | host=' . $dbHost .
            ' | lat_start_exists=' . $latStartExists
        );
    } catch (Throwable $diagnosticError) {
        error_log(
            'save_km.php DB error: ' . $e->getMessage() .
            ' | diagnostic_failed=' . $diagnosticError->getMessage()
        );
    }

    @unlink($fullPath);
    jsonError('Erro ao salvar no banco de dados.', 500);
}

jsonOk(['type' => $type, 'km' => $km]);
