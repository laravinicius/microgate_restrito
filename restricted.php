<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// 1. Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Se NÃO for administrador, redireciona para escala.php
if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: escala.php');
    exit;
}

$isAdmin = ((int)$_SESSION['is_admin'] === 1);
?><!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel | Microgate Informática</title>
    <link rel="shortcut icon" href="./img/ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./js/theme.js"></script>
    <script src="./js/components.js" defer></script>
    <?php require __DIR__ . '/components/google-analytics.php'; ?>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>

            <main class="flex-1 pt-32 md:pt-52 pb-20">
                <div class="max-w-7xl mx-auto px-4">

                    <?php
                        $pageTitle    = 'Painel';
                        $pageSubtitle = 'Bem-vindo, ' . ($_SESSION['full_name'] ?? $_SESSION['username']);
                        $backUrl      = '';
                        require __DIR__ . '/components/page_header.php';
                    ?>

                    <!-- Cards de navegação rápida -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">

                        <a href="km_report.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-purple-500/15">
                                    <i data-lucide="gauge" class="w-6 h-6 md:w-5 md:h-5 text-purple-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-base md:text-sm truncate">Quilometragem</p>
                                    <p class="text-gray-400 text-xs hidden md:block">KM por técnico</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 md:w-4 md:h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <a href="gerenciamento_usuarios.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-blue-500/15">
                                    <i data-lucide="users" class="w-6 h-6 md:w-5 md:h-5 text-blue-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-base md:text-sm truncate">Usuários</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Gerenciamento</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 md:w-4 md:h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <a href="test_location.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-emerald-500/15">
                                    <i data-lucide="map-pinned" class="w-6 h-6 md:w-5 md:h-5 text-emerald-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-base md:text-sm truncate">Teste Localizacao</p>
                                    <p class="text-gray-400 text-xs hidden md:block">GPS no navegador</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 md:w-4 md:h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <?php if ($isAdmin): ?>
                        <a href="import_schedules.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-green-500/15">
                                    <i data-lucide="upload" class="w-6 h-6 md:w-5 md:h-5 text-green-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-base md:text-sm truncate">Importar</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Escala CSV</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 md:w-4 md:h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <a href="access_logs.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-yellow-500/15">
                                    <i data-lucide="shield-check" class="w-6 h-6 md:w-5 md:h-5 text-yellow-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-base md:text-sm truncate">Logs</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Auditoria</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 md:w-4 md:h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>
                        <?php endif; ?>

                    </div>

                    <!-- Calendário de escalas -->
                    <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">

                        <!-- Header do calendário -->
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 px-4 md:px-6 py-4 border-b border-white/10">
                            <div>
                                <h2 class="text-white font-bold text-2xl md:text-xl flex items-center gap-2">
                                    <i data-lucide="calendar-days" class="w-6 h-6 md:w-5 md:h-5 text-gray-400"></i>
                                    Escala de Técnicos
                                </h2>
                                <p class="text-gray-400 text-sm md:text-xs mt-1">Clique em um dia para ver quem está disponível</p>
                            </div>
                            <div class="flex items-center gap-3 text-sm md:text-xs">
                                <span class="flex items-center gap-1.5">
                                    <span class="legend-swatch legend-swatch--green"></span>
                                    <span class="legend-label--green">Trabalhando</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="legend-swatch legend-swatch--blue"></span>
                                    <span class="legend-label--blue">Folga</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="legend-swatch legend-swatch--orange"></span>
                                    <span class="legend-label--orange">Férias</span>
                                </span>
                            </div>
                        </div>

                        <!-- Abas de meses -->
                        <div class="px-4 md:px-6 pt-4 pb-3 border-b border-white/10">
                            <div id="month-tab-wrap" class="flex gap-2 overflow-x-auto flex-wrap"></div>
                        </div>

                        <!-- Corpo do calendário -->
                        <div class="p-3 md:p-6">
                            <div id="calendar-wrap"></div>
                        </div>

                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        window.IS_ADMIN   = <?= (int)$_SESSION['is_admin'] === 1 ? 'true' : 'false' ?>;
        window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    </script>
    <script>
        window.IS_ADMIN   = <?= $isAdmin ? 'true' : 'false' ?>;
        window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    </script>
    <script src="./js/escala-admin.js?v=<?= time() ?>"></script>
</body>

</html>
