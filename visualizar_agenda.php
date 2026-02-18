<?php
session_start();
require __DIR__ . '/bootstrap.php';

// Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Bloqueia acesso se não for administrador
if ($_SESSION['is_admin'] !== 1) {
    header('Location: escala.php');
    exit;
}

// Obtém e valida o user_id do parâmetro GET
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    die('<div style="padding: 20px; color: #ef4444;">Parâmetro user_id inválido.</div>');
}

// Valida se o user_id existe no banco de dados
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('<div style="padding: 20px; color: #ef4444;">Técnico não encontrado.</div>');
}

$tech_name = htmlspecialchars($user['username']);
?>

<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de <?= $tech_name ?> | Microgate Informática</title>
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
                margin-top: 80px !important;
            }
        }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            
            <main class="flex-1 pt-20 md:pt-24 pb-10">
                <div class="max-w-4xl mx-auto px-4">
                    <div class="mb-12 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                        <div class="flex-1">
                            <h1 class="text-2xl md:text-4xl font-bold text-white mb-2">Agenda de <?= $tech_name ?></h1>
                            <p class="text-gray-400 text-sm md:text-base">Visualizando escala técnica</p>
                        </div>
                        <button onclick="window.close()" class="bg-gray-700 hover:bg-gray-600 border-2 border-gray-600 text-white font-semibold py-2 px-4 md:py-2 md:px-6 rounded-lg transition flex items-center gap-2 w-full md:w-auto justify-center text-sm md:text-base">
                            <i data-lucide="x" class="w-4 h-4"></i>
                            Fechar
                        </button>
                    </div>

                    <div class="mt-6">
                        <div class="flex flex-col md:flex-row gap-4 mb-6 items-start md:items-center md:justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg md:text-xl font-bold text-white flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-5 h-5"></i>
                                    Escala de Trabalho
                                </h2>
                                <div id="month-display" class="text-xs md:text-sm text-gray-300 bg-white/5 px-3 py-2 rounded">Carregando...</div>
                            </div>
                        </div>

                        <div id="calendar-wrap" class="w-full overflow-hidden">
                            <div class="p-4 text-center text-gray-400">Carregando calendários...</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Define o ID do técnico para o escala-abas.js buscar a agenda correta
        window.TARGET_USER_ID = <?= (int)$user_id ?>;
    </script>
    <script src="./js/escala-abas.js?v=<?= time() ?>"></script>
</body>

</html>
