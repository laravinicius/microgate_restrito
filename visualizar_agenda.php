<?php

require __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: escala.php');
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    die('<div style="padding:20px;color:#ef4444;">Parâmetro user_id inválido.</div>');
}

$stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('<div style="padding:20px;color:#ef4444;">Técnico não encontrado.</div>');
}

$tech_name = htmlspecialchars($user['full_name'] ?: $user['username']);
?><!DOCTYPE html>
<html lang="pt-br" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de <?= $tech_name ?></title>
    <link rel="stylesheet" href="./css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./js/theme.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        /* Reset completo — sem header, sem background-image, sem boxed layout */
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #141414;
            color: #f9fafb;
            min-height: 100%;
        }

        /* Garante que classes do style.css global não vazem */
        body { background-image: none !important; background-attachment: scroll !important; }
    </style>
</head>
<body>
    <div style="max-width:900px;margin:0 auto;padding:24px 16px 40px;">

        <div id="calendar-wrap" class="w-full overflow-hidden">
            <div style="padding:16px;text-align:center;color:#6b7280;">Carregando...</div>
        </div>

    </div>

    <script>
        window.TARGET_USER_ID = <?= (int)$user_id ?>;
    </script>
    <script src="./js/escala-abas.js?v=<?= time() ?>"></script>
</body>
</html>