<?php

require __DIR__ . '/bootstrap.php';

// Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redireciona administradores para a tela correta
if (isAdmin()) {
    header('Location: restricted.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escala Técnica | Microgate Informática</title>
    <link rel="shortcut icon" href="./img/ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./js/theme.js"></script>
    <script src="./js/components.js" defer></script>
    <?php require __DIR__ . '/components/google-analytics.php'; ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>
            
            <main class="page-main flex-1">
                <div class="max-w-7xl mx-auto px-4">

                    <?php
                        $pageTitle    = 'Escala Técnica';
                        $pageSubtitle = 'Consulte sua escala de trabalho';
                        $backUrl      = '';
                        require __DIR__ . '/components/page_header.php';
                    ?>

                    <p class="text-gray-300 text-sm mb-6">
                        <span class="text-gray-400">Logado como:</span>
                        <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></strong>
                    </p>

                    <!-- Card de acesso rápido: Quilometragem — padrão visual do projeto -->
                    <a href="quilometragem.php" class="bg-brand-dark border border-white/10 rounded-lg p-8 mb-10 transition group flex items-start justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                                <i data-lucide="gauge" class="w-6 h-6"></i>
                                Quilometragem
                            </h3>
                            <p class="text-gray-300 text-sm">Registre o KM do veículo no início e fim do turno</p>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-gray-200 group-hover:translate-x-1 transition flex-shrink-0 mt-1"></i>
                    </a>

                    <!-- Calendário de escala -->
                    <div class="mt-8">
                        <div class="flex flex-col md:flex-row gap-4 mb-8 items-start md:items-center md:justify-between">
                            <h2 class="text-xl md:text-2xl font-bold text-white flex items-center gap-2">
                                <i data-lucide="calendar" class="w-6 h-6"></i>
                                Sua Escala
                            </h2>
                            <div id="month-display" class="text-xs md:text-sm text-gray-300 bg-white/5 px-3 py-2 rounded">Carregando...</div>
                        </div>

                        <div id="calendar-wrap" class="w-full max-w-[90vw] md:max-w-full mx-auto overflow-hidden mb-8">
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        // Atualizar exibição do mês atual
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const monthName = now.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
            const displayMonth = monthName.charAt(0).toUpperCase() + monthName.slice(1);
            const monthDisplay = document.getElementById('month-display');
            if (monthDisplay) {
                monthDisplay.textContent = displayMonth;
            }
        });
    </script>
    <script src="./js/escala.js?v=<?= time() ?>"></script>
</body>

</html>
