<?php
declare(strict_types=1);

/**
 * serve_km_photo.php
 *
 * Entrega fotos de quilometragem com verificação de autenticação e permissão.
 * Nunca expõe o caminho real do arquivo no servidor.
 *
 * Uso:  serve_km_photo.php?file=uploads/km/{user_id}/{date}_{type}.jpg
 *
 * Regras de acesso:
 *   - Técnico (is_admin = 0): só vê suas próprias fotos
 *   - Admin / Gerente (is_admin >= 1): vê fotos de qualquer técnico
 */

require __DIR__ . '/bootstrap.php';

// ── Autenticação ──────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Não autenticado.');
}

// ── Parâmetro obrigatório ─────────────────────────────────────────────────────
$requestedFile = trim((string)($_GET['file'] ?? ''));

if ($requestedFile === '') {
    http_response_code(400);
    exit('Parâmetro "file" ausente.');
}

// ── Sanitização e validação do caminho ───────────────────────────────────────
// Normaliza separadores e resolve ".." para prevenir path traversal
$normalized = str_replace('\\', '/', $requestedFile);

// Deve começar exatamente com "uploads/km/"
if (strpos($normalized, 'uploads/km/') !== 0) {
    http_response_code(403);
    exit('Acesso negado.');
}

// Não permite sequências de traversal mesmo após normalização
if (strpos($normalized, '..') !== false || strpos($normalized, './') !== false) {
    http_response_code(403);
    exit('Caminho inválido.');
}

// Extrai user_id e nome do arquivo do caminho: uploads/km/{uid}/{filename}
$parts = explode('/', $normalized);
// Espera exatamente: ['uploads', 'km', '{uid}', '{filename}']
if (count($parts) !== 4) {
    http_response_code(400);
    exit('Caminho mal formado.');
}

$fileUserId = (int)$parts[2];   // user_id dono da foto
$filename   = $parts[3];

// Valida o nome do arquivo: YYYY-MM-DD_(start|end).jpg
if (!preg_match('/^\d{4}-\d{2}-\d{2}_(start|end)\.jpg$/', $filename)) {
    http_response_code(400);
    exit('Nome de arquivo inválido.');
}

// ── Autorização ───────────────────────────────────────────────────────────────
$currentUserId = (int)$_SESSION['user_id'];

if (!isAdmin() && $currentUserId !== $fileUserId) {
    http_response_code(403);
    exit('Sem permissão para acessar esta foto.');
}

// ── Valida existência no banco (evita servir arquivos órfãos) ─────────────────
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

// ── Serve o arquivo ───────────────────────────────────────────────────────────
$fullPath = __DIR__ . '/' . $normalized;

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

// Cache: 1 hora (fotos não mudam após salvas)
$etag     = '"' . md5_file($fullPath) . '"';
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');
header('ETag: ' . $etag);
// Impede que o browser adivinhe o tipo do arquivo
header('X-Content-Type-Options: nosniff');
// Não indexar nem armazenar em cache intermediário
header('Pragma: no-cache');

readfile($fullPath);
exit;