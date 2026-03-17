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

                    <!-- Cabeçalho -->
                    <div class="mb-10 flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                        <div>
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Painel</h1>
                            <p class="text-gray-400">Bem-vindo, <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></strong></p>
                        </div>
                        <a href="logout.php" class="bg-red-600 hover:bg-red-700 border-2 border-red-500 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 self-start text-sm">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Sair
                        </a>
                    </div>

                    <!-- Cards de navegação rápida -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">

                        <a href="km_report.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-purple-500/15">
                                    <i data-lucide="gauge" class="w-5 h-5 text-purple-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-sm truncate">Quilometragem</p>
                                    <p class="text-gray-400 text-xs hidden md:block">KM por técnico</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <a href="gerenciamento_usuarios.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-blue-500/15">
                                    <i data-lucide="users" class="w-5 h-5 text-blue-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-sm truncate">Usuários</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Gerenciamento</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <?php if ($isAdmin): ?>
                        <a href="import_schedules.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-green-500/15">
                                    <i data-lucide="upload" class="w-5 h-5 text-green-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-sm truncate">Importar</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Escala CSV</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>

                        <a href="access_logs.php" class="nav-card">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="icon bg-yellow-500/15">
                                    <i data-lucide="shield-check" class="w-5 h-5 text-yellow-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-semibold text-sm truncate">Logs</p>
                                    <p class="text-gray-400 text-xs hidden md:block">Auditoria</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"></i>
                        </a>
                        <?php endif; ?>

                    </div>

                    <!-- Calendário de escalas -->
                    <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">

                        <!-- Header do calendário -->
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 px-6 py-4 border-b border-white/10">
                            <div>
                                <h2 class="text-white font-bold text-xl flex items-center gap-2">
                                    <i data-lucide="calendar-days" class="w-5 h-5 text-gray-400"></i>
                                    Escala de Técnicos
                                </h2>
                                <p class="text-gray-400 text-xs mt-1">Clique em um dia para ver quem está disponível</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
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
                        <div class="px-6 pt-4 pb-3 border-b border-white/10">
                            <div id="month-tab-wrap" class="flex gap-2 overflow-x-auto flex-wrap"></div>
                        </div>

                        <!-- Corpo do calendário -->
                        <div class="p-4 md:p-6">
                            <div id="calendar-wrap"></div>
                        </div>

                    </div>

                </div>
            </main>
        </div>
    </div>

    <script src="./js/escala-admin.js?v=<?= time() ?>"></script>
</body>

</html>