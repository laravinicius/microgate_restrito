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

if (!isLoggedIn()) jsonError('Nao autenticado.', 401);
if (isAdmin()) jsonError('Acesso nao permitido.', 403);
if (!hasFuelAccess()) jsonError('Acesso a abastecimento nao permitido para este usuario.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Metodo invalido.', 405);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) jsonError('Corpo da requisicao invalido.');

$csrfToken = (string)($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    jsonError('Token de seguranca invalido. Recarregue a pagina.', 403);
}

$fuelPrice = isset($body['fuel_price']) && is_numeric($body['fuel_price']) ? (float)$body['fuel_price'] : -1;
$liters = isset($body['liters']) && is_numeric($body['liters']) ? (float)$body['liters'] : -1;
$currentKm = null;
if (isset($body['current_km']) && $body['current_km'] !== '' && $body['current_km'] !== null) {
    if (!is_numeric($body['current_km'])) {
        jsonError('KM atual invalido.');
    }
    $currentKm = (int)$body['current_km'];
}

$photo = (string)($body['photo'] ?? '');
$lat = isset($body['lat']) && is_numeric($body['lat']) ? (float)$body['lat'] : null;
$lng = isset($body['lng']) && is_numeric($body['lng']) ? (float)$body['lng'] : null;

if ($lat !== null && ($lat < -90 || $lat > 90)) $lat = null;
if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;

if ($fuelPrice <= 0 || $fuelPrice > 9999.99) jsonError('Valor da gasolina invalido.');
if ($liters <= 0 || $liters > 9999.999) jsonError('Quantidade de litros invalida.');
if ($currentKm !== null && ($currentKm < 0 || $currentKm > 9_999_999)) {
    jsonError('KM atual fora do intervalo (0-9.999.999).');
}
if ($photo === '') jsonError('Foto do comprovante obrigatoria.');

$imageData = base64_decode($photo, true);
if ($imageData === false || strlen($imageData) < 100) jsonError('Imagem invalida ou corrompida.');
if (substr($imageData, 0, 3) !== "\xFF\xD8\xFF") jsonError('Somente imagens JPEG sao aceitas.');
if (strlen($imageData) > 3 * 1024 * 1024) jsonError('Imagem muito grande. Maximo: 3 MB.');

$userId = (int)$_SESSION['user_id'];
$totalAmount = round($fuelPrice * $liters, 2);

$fuelRootDir = dirname(__DIR__, 3) . '/uploads/abastecimento';
$baseDir = $fuelRootDir . '/' . $userId;

if (!is_dir($fuelRootDir) && !mkdir($fuelRootDir, 0775, true) && !is_dir($fuelRootDir)) {
    error_log('save_fuel.php storage error: unable to create fuel root dir "' . $fuelRootDir . '"');
    jsonError('Erro ao preparar armazenamento.', 500);
}

$fuelHtaccess = $fuelRootDir . '/.htaccess';
if (!file_exists($fuelHtaccess)) {
    @file_put_contents(
        $fuelHtaccess,
        "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    );
}

if (!is_dir($baseDir) && !mkdir($baseDir, 0750, true) && !is_dir($baseDir)) {
    $mkdirError = error_get_last();
    error_log(
        'save_fuel.php storage error: unable to create user dir "' . $baseDir . '"' .
        ($mkdirError ? ' - ' . $mkdirError['message'] : '')
    );
    jsonError('Erro ao preparar armazenamento.', 500);
}

$filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
$relativePath = 'uploads/abastecimento/' . $userId . '/' . $filename;
$fullPath = dirname(__DIR__, 3) . '/' . $relativePath;

if (file_put_contents($fullPath, $imageData) === false) {
    jsonError('Erro ao salvar a foto.', 500);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO fuel_logs
            (user_id, fueled_at, fuel_price, liters, total_amount, current_km, receipt_photo, lat, lng)
         VALUES
            (:uid, NOW(), :fuel_price, :liters, :total_amount, :current_km, :receipt_photo, :lat, :lng)"
    );

    $stmt->execute([
        ':uid' => $userId,
        ':fuel_price' => number_format($fuelPrice, 2, '.', ''),
        ':liters' => number_format($liters, 3, '.', ''),
        ':total_amount' => number_format($totalAmount, 2, '.', ''),
        ':current_km' => $currentKm,
        ':receipt_photo' => $relativePath,
        ':lat' => $lat,
        ':lng' => $lng,
    ]);

    $savedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('H:i');

} catch (Throwable $e) {
    error_log('save_fuel.php DB error: ' . $e->getMessage());
    @unlink($fullPath);
    jsonError('Erro ao salvar no banco de dados.', 500);
}

jsonOk([
    'saved_at' => $savedAt,
    'fuel_price' => number_format($fuelPrice, 2, '.', ''),
    'liters' => number_format($liters, 3, '.', ''),
    'total_amount' => number_format($totalAmount, 2, '.', ''),
]);
