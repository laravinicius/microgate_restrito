<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Não autenticado.');
}

$requestedFile = trim((string)($_GET['file'] ?? ''));

if ($requestedFile === '') {
    http_response_code(400);
    exit('Parâmetro "file" ausente.');
}

$normalized = str_replace('\\', '/', $requestedFile);

if (strpos($normalized, 'uploads/km/') !== 0) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (strpos($normalized, '..') !== false || strpos($normalized, './') !== false) {
    http_response_code(403);
    exit('Caminho inválido.');
}

$parts = explode('/', $normalized);
if (count($parts) !== 4) {
    http_response_code(400);
    exit('Caminho mal formado.');
}

$fileUserId = (int)$parts[2];
$filename   = $parts[3];

if (!preg_match('/^\d{4}-\d{2}-\d{2}_(start|end)\.jpg$/', $filename)) {
    http_response_code(400);
    exit('Nome de arquivo inválido.');
}

$currentUserId = (int)$_SESSION['user_id'];

// Técnico só vê as próprias fotos
// Admin (1, 2) e Gerente KM (3) veem qualquer foto
$canViewAll = isAdmin() || isKmManager();

if (!$canViewAll && $currentUserId !== $fileUserId) {
    http_response_code(403);
    exit('Sem permissão para acessar esta foto.');
}

$stmt = $pdo->prepare(
    "SELECT id FROM mileage_logs
     WHERE user_id = :uid
       AND (photo_start = :path1 OR photo_end = :path2)
     LIMIT 1"
);
$stmt->execute([':uid' => $fileUserId, ':path1' => $normalized, ':path2' => $normalized]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Foto não encontrada.');
}

$fullPath = __DIR__ . '/' . $normalized;

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

$etag        = '"' . md5_file($fullPath) . '"';
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
header('Pragma: no-cache');

readfile($fullPath);
exit;
