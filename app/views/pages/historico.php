<?php

require dirname(__DIR__, 3) . '/app/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . route_url('login.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico da Escala | Microgate Informática</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars(asset_url('img/ico.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/components.js')) ?>" defer></script>
    <?php require APP_ROOT . '/components/google-analytics.php'; ?>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>

            <main class="page-main flex-1">
                <div class="max-w-7xl mx-auto px-4">

                    <?php
                        $pageTitle    = 'Histórico da Escala';
                        $pageSubtitle = 'Consulte meses anteriores e futuros da sua escala';
                        $backUrl      = route_url('escala.php');
                        require APP_ROOT . '/components/page_header.php';
                    ?>

                    <div id="calendar-wrap" class="w-full overflow-hidden mb-8">
                        <div class="agenda-loading">Carregando...</div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        window.HISTORY_ONLY = true;
        window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
    <script src="<?= htmlspecialchars(asset_url('js/escala-abas.js')) ?>?v=<?= time() ?>"></script>
</body>

</html>