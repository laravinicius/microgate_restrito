<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Responde sempre em JSON
header('Content-Type: application/json; charset=utf-8');

// ── Helpers ──────────────────────────────────────────────────────────────────
function jsonError(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function jsonOk(array $data = []): never
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// ── Autenticação ─────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    jsonError('Não autenticado.', 401);
}

// Apenas técnicos usam esta rota (admin tem painel próprio)
if (isAdmin()) {
    jsonError('Acesso não permitido.', 403);
}

// ── Método e corpo ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método inválido.', 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    jsonError('Corpo da requisição inválido.');
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfToken = (string)($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    jsonError('Token de segurança inválido. Recarregue a página.', 403);
}

// ── Validação dos campos ──────────────────────────────────────────────────────
$type    = (string)($body['type'] ?? '');     // 'start' | 'end'
$km      = isset($body['km']) ? (int)$body['km'] : -1;
$photo   = (string)($body['photo'] ?? '');    // base64 sem prefixo
$logDate = (string)($body['log_date'] ?? ''); // YYYY-MM-DD (do dispositivo)

if (!in_array($type, ['start', 'end'], true)) {
    jsonError('Tipo inválido. Use "start" ou "end".');
}

if ($km < 0 || $km > 9_999_999) {
    jsonError('Quilometragem fora do intervalo permitido (0 – 9.999.999).');
}

// Valida formato da data (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
    jsonError('Data inválida.');
}
// Não aceita datas futuras (mais de 1 dia, tolerância para fuso horário)
$deviceDate  = new DateTimeImmutable($logDate);
$serverToday = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
if ($deviceDate > $serverToday->modify('+1 day')) {
    jsonError('Não é permitido registrar para datas futuras.');
}

if (empty($photo)) {
    jsonError('Foto obrigatória.');
}

// ── Decodifica e valida a imagem ───────────────────────────────────────────────
$imageData = base64_decode($photo, true);
if ($imageData === false || strlen($imageData) < 100) {
    jsonError('Imagem inválida ou corrompida.');
}

// Verifica assinatura real do arquivo (JPEG: FF D8 FF)
$sig = substr($imageData, 0, 3);
if ($sig !== "\xFF\xD8\xFF") {
    jsonError('Somente imagens JPEG são aceitas.');
}

// Limite de tamanho: 3 MB (após compressão client-side deve ficar ~150 KB)
if (strlen($imageData) > 3 * 1024 * 1024) {
    jsonError('Imagem muito grande. Máximo permitido: 3 MB.');
}

// ── Verifica regras de negócio no banco ───────────────────────────────────────
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT id, km_start, km_end
     FROM mileage_logs
     WHERE user_id = :uid AND log_date = :date
     LIMIT 1"
);
$stmt->execute([':uid' => $userId, ':date' => $logDate]);
$existing = $stmt->fetch();

if ($type === 'start' && $existing && $existing['km_start'] !== null) {
    jsonError('KM inicial já registrado para hoje.');
}

if ($type === 'end') {
    if (!$existing || $existing['km_start'] === null) {
        jsonError('Registre o KM inicial antes do final.');
    }
    if ($existing['km_end'] !== null) {
        jsonError('KM final já registrado para hoje.');
    }
    // KM final deve ser >= KM inicial
    if ($km < (int)$existing['km_start']) {
        jsonError('KM final não pode ser menor que o KM inicial (' . $existing['km_start'] . ').');
    }
}

// ── Salva a foto ──────────────────────────────────────────────────────────────
$baseDir = __DIR__ . '/uploads/km/' . $userId;
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0750, true)) {
        error_log("save_km.php: falha ao criar diretório $baseDir");
        jsonError('Erro interno ao preparar armazenamento.', 500);
    }
}

// Cria um .htaccess na pasta km/ na primeira execução
$kmHtaccess = __DIR__ . '/uploads/km/.htaccess';
if (!file_exists($kmHtaccess)) {
    file_put_contents($kmHtaccess,
        "# Bloqueia acesso web direto\nDeny from all\n\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    );
}

$filename    = $logDate . '_' . $type . '.jpg';   // ex: 2026-03-16_start.jpg
$relativePath = 'uploads/km/' . $userId . '/' . $filename;
$fullPath     = __DIR__ . '/' . $relativePath;

if (file_put_contents($fullPath, $imageData) === false) {
    error_log("save_km.php: falha ao gravar arquivo $fullPath");
    jsonError('Erro interno ao salvar a foto.', 500);
}

// ── Persiste no banco (INSERT ou UPDATE) ──────────────────────────────────────
try {
    if ($type === 'start') {
        $sql = "INSERT INTO mileage_logs (user_id, log_date, km_start, photo_start)
                VALUES (:uid, :date, :km, :photo)
                ON DUPLICATE KEY UPDATE
                    km_start    = VALUES(km_start),
                    photo_start = VALUES(photo_start),
                    updated_at  = CURRENT_TIMESTAMP";
        $params = [
            ':uid'   => $userId,
            ':date'  => $logDate,
            ':km'    => $km,
            ':photo' => $relativePath,
        ];
    } else {
        $sql = "UPDATE mileage_logs
                SET km_end = :km, photo_end = :photo, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :uid AND log_date = :date";
        $params = [
            ':km'    => $km,
            ':photo' => $relativePath,
            ':uid'   => $userId,
            ':date'  => $logDate,
        ];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

} catch (PDOException $e) {
    error_log('save_km.php DB error: ' . $e->getMessage());
    // Remove o arquivo salvo para não deixar órfão
    @unlink($fullPath);
    jsonError('Erro ao salvar no banco de dados. Tente novamente.', 500);
}

jsonOk(['type' => $type, 'km' => $km]);
error_log("save_km PATH: " . $fullPath . " | DIR gravável: " . (is_writable($baseDir) ? 'SIM' : 'NAO') . " | tamanho imagem: " . strlen($imageData));
