<?php

require __DIR__ . '/bootstrap.php';

// Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redireciona administradores para a tela correta
if ($_SESSION['is_admin'] === 1) {
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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        @media (min-width: 768px) {
            main {
                margin-top: 120px !important;
            }
        }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>
            
            <main class="flex-1 pt-24 md:pt-52 pb-20">
                <div class="max-w-6xl mx-auto px-4">
                    <div class="mb-16 flex flex-col md:flex-row md:justify-between md:items-start gap-6 md:gap-0">
                        <div class="flex-1">
                            <h1 class="text-3xl md:text-5xl font-bold text-white mb-2">Escala Técnica</h1>
                            <p class="text-gray-400">Consulte sua escala de trabalho</p>
                        </div>
                        <div class="flex flex-col items-start md:items-end gap-4">
                            <p class="text-gray-300 text-sm"><span class="text-gray-400">Logado como:</span> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
                            <a href="logout.php" class="bg-red-600 hover:bg-red-700 border-2 border-red-500 text-white font-semibold py-2 px-4 md:py-3 md:px-8 rounded-lg transition flex items-center gap-2 w-full md:w-auto justify-center md:justify-start text-sm md:text-base">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                Sair da Conta
                            </a>
                        </div>
                    </div>

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