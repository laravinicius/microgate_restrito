<?php

require dirname(__DIR__, 3) . '/app/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . route_url('login.php'));
    exit;
}

if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    die('<div>Parâmetro user_id inválido.</div>');
}

$stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('<div>Técnico não encontrado.</div>');
}

// Apenas admin nível 1 pode editar escalas
$canEdit = ((int)$_SESSION['is_admin'] === 1);

// CSRF token para chamadas AJAX de edição
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tech_name = htmlspecialchars($user['full_name'] ?: $user['username']);
?><!DOCTYPE html>
<html lang="pt-br" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de <?= $tech_name ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>
</head>
<body class="agenda-embed-page">
    <div class="agenda-page-shell">
        <div id="calendar-wrap" class="w-full overflow-hidden">
            <div class="agenda-loading">Carregando...</div>
        </div>
    </div>

    <div id="schedule-toast"></div>

    <script>
        window.TARGET_USER_ID  = <?= (int)$user_id ?>;
        window.ADMIN_EDIT_MODE = <?= $canEdit ? 'true' : 'false' ?>;
        window.CSRF_TOKEN      = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
    <script src="<?= htmlspecialchars(asset_url('js/escala-abas.js')) ?>?v=<?= time() ?>"></script>
</body>
</html>
