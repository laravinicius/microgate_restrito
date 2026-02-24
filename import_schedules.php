<?php

require __DIR__ . '/bootstrap.php';

// 1. Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Se NÃO for administrador, redireciona para escala.php
if ((int)$_SESSION['is_admin'] !== 1) {
    header('Location: escala.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$previewData = [];
$importMonth = '';
$importYear = '';

// Função para garantir que a string seja UTF-8 (Excel costuma salvar em ISO-8859-1)
// Definida fora do loop para evitar recriação e com fallback caso mbstring não esteja instalado
function safeToUtf8($str) {
    $str = (string)$str;
    if ($str === '') return '';
    if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
        if (!mb_check_encoding($str, 'UTF-8')) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
    }
    return $str;
}

// Processar upload do CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Ação não autorizada. Recarregue a página e tente novamente.';
        $messageType = 'error';
    } else {
    $file = $_FILES['schedule_file'];
    
    // Validações
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Erro ao fazer upload do arquivo.';
        $messageType = 'error';
    } elseif (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') {
        $message = 'Formato inválido. Use arquivos CSV.';
        $messageType = 'error';
    } else {
        // Processar o CSV
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $message = 'Não foi possível abrir o arquivo enviado.';
            $messageType = 'error';
        } else {
        
        // Tenta detectar o separador (ponto-e-vírgula ou vírgula)
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = (strpos($firstLine, ';') !== false) ? ';' : ',';
        
        $header = fgetcsv($handle, 0, $sep);
        
        if (!$header) {
            $message = 'Arquivo CSV vazio ou inválido.';
            $messageType = 'error';
        } else {
            // Função auxiliar para normalizar data
            $normalizaData = function($dateStr) {
                $dateStr = trim($dateStr);
                
                // Tenta vários formatos
                $formatos = [
                    '/^(\d{2})\/(\d{2})\/(\d{4})$/',  // DD/MM/YYYY
                    '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', // D/M/YYYY ou DD/MM/YYYY
                    '/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', // DD/MM/YY
                ];
                
                foreach ($formatos as $fmt) {
                    if (preg_match($fmt, $dateStr, $m)) {
                        $day = (int)$m[1];
                        $month = (int)$m[2];
                        $year = (int)$m[3];

                        // Se o ano tiver 2 dígitos (ex: 24), assume 20xx (2024)
                        if ($year < 100) {
                            $year += 2000;
                        }
                        
                        // Validar data
                        if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 2020 && $year <= 2030) {
                            return [
                                'day' => $day,
                                'month' => $month,
                                'year' => $year,
                                'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                                'valid' => true
                            ];
                        }
                    }
                }
                
                return ['valid' => false];
            };
            
            // Detectar coluna de nomes
            $nameCol = 0;
            $dayCols = [];
            
            foreach ($header as $i => $h) {
                $normalized = $normalizaData($h);
                if ($normalized['valid']) {
                    $dayCols[$i] = $normalized;
                }
            }
            
            if (empty($dayCols)) {
                $message = 'Não foi possível detectar as colunas de datas no formato DD/MM/YYYY.';

                $messageType = 'error';
            } else {
                // Detectar mês e ano a partir do primeiro dia
                $firstDay = reset($dayCols);
                $importMonth = $firstDay['month'];
                $importYear = $firstDay['year'];
                
                // Ler linhas do CSV
                $rows = [];
                $unknown = [];
                
                while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                    $allEmpty = true;
                    foreach ($row as $c) {
                        if (trim((string)$c) !== '') {
                            $allEmpty = false;
                            break;
                        }
                    }
                    if ($allEmpty) continue;
                    
                    $name = safeToUtf8(trim($row[$nameCol] ?? ''));
                    $name = preg_replace('/^\s*\d+\s*-\s*/', '', $name);
                    if ($name === '') continue;
                    
                    // Tentar encontrar o usuário no banco
                    $matched = null;
                    $nameLower = strtolower(trim($name));
                    
                    // Tentativa 1: Match exato (case-insensitive)
                    $stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE LOWER(username) = ? LIMIT 1');
                    $stmt->execute([$nameLower]);
                    $u = $stmt->fetch();
                    $matchedFullName = null;
                    if ($u) {
                        $matched = $u['username'];
                        $matchedFullName = $u['full_name'];
                    }
                    
                    // Tentativa 2: Match parcial com ponto (ex: paulo.h, paulo.j)
                    if (!$matched && strpos($nameLower, '.') !== false) {
                        // Nomes com pontos como "paulo.h"
                        $stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE LOWER(username) LIKE ? LIMIT 1');
                        $stmt->execute([$nameLower . '%']);
                        $u = $stmt->fetch();
                        if ($u) {
                            $matched = $u['username'];
                            $matchedFullName = $u['full_name'];
                        }
                    }
                    
                    // Tentativa 3: Busca por primeiro nome
                    if (!$matched) {
                        $parts = preg_split('/[\s\.]+/', trim($name));
                        $first = strtolower($parts[0]);
                        
                        if (!empty($first)) {
                            $stmt = $pdo->prepare('SELECT username, full_name FROM users WHERE LOWER(username) LIKE ? LIMIT 1');
                            $stmt->execute([$first . '%']);
                            $u = $stmt->fetch();
                            if ($u) {
                                $matched = $u['username'];
                                $matchedFullName = $u['full_name'];
                            }
                        }
                    }
                    
                    // Tentativa 4: Busca fuzzy com LIKE (sem pontos)
                    if (!$matched) {
                        $nameCleaned = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
                        if (!empty($nameCleaned)) {
                            // Buscar todos os usuários e comparar com nome limpo
                            $stmt = $pdo->query('SELECT username, full_name FROM users');
                            $allUsers = $stmt->fetchAll();
                            
                            foreach ($allUsers as $user) {
                                $userCleaned = strtolower(preg_replace('/[^a-z0-9]/i', '', $user['username']));
                                if ($userCleaned === $nameCleaned || strpos($userCleaned, substr($nameCleaned, 0, 5)) === 0) {
                                    $matched = $user['username'];
                                    $matchedFullName = $user['full_name'];
                                    break;
                                }
                            }
                        }
                    }
                    
                    if (!$matched) {
                        $unknown[] = $name;
                        continue;
                    }
                    
                    // Coletar escala do técnico
                    $schedule = [];
                    foreach ($dayCols as $colIdx => $dayInfo) {
                        $shift = safeToUtf8(trim($row[$colIdx] ?? ''));
                        if ($shift !== '') {
                            $schedule[] = [
                                'date' => $dayInfo['date'],
                                'shift' => $shift,
                                'username' => $matched
                            ];
                        }
                    }
                    
                    if (!empty($schedule)) {
                        $rows[] = [
                            'original_name' => $name,
                            'username' => $matched,
                            'full_name' => $matchedFullName,
                            'schedule_count' => count($schedule),
                            'schedule' => $schedule
                        ];
                    }
                }
                
                fclose($handle);
                
                if (!empty($rows)) {
                    $previewData = $rows;
                    $message = 'Arquivo processado com sucesso! Revise os dados abaixo antes de importar.';
                    $messageType = 'success';
                    
                    if (!empty($unknown)) {
                        $escapedUnknown = array_map('htmlspecialchars', $unknown);
                        $message .= '<br><strong>Aviso:</strong> ' . count($unknown) . ' técnico(s) não foram encontrados no sistema: ' . implode(', ', $escapedUnknown);
                        $messageType = 'warning';
                    }
                } else {
                    $message = 'Nenhum dado válido foi encontrado no arquivo.';
                    $messageType = 'error';
                }
            }
        }
    }
    }
    }
}

// Processar importação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Ação não autorizada. Recarregue a página e tente novamente.';
        $messageType = 'error';
    } else {
    $month = (int)($_POST['month'] ?? 0);
    $year = (int)($_POST['year'] ?? 0);
    $scheduleJson = $_POST['schedule_data'] ?? '[]';
    
    if ($month < 1 || $month > 12 || $year < 2024 || $year > 2030) {
        $message = 'Mês ou ano inválido.';
        $messageType = 'error';
    } else {
        $scheduleData = json_decode((string)$scheduleJson, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE || empty($scheduleData)) {
            $message = 'Nenhum dado para importar ou erro na integridade dos dados (Erro JSON: ' . json_last_error_msg() . ').';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $insertCount = 0;

                // Prepara as queries uma única vez para melhor performance e estabilidade
                $stmtUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $stmtDelete = $pdo->prepare('DELETE FROM schedules WHERE user_id = ? AND date = ?');
                $stmtInsert = $pdo->prepare('INSERT INTO schedules (user_id, date, shift, note) VALUES (?, ?, ?, ?)');
                
                // Extrair eventos de todos os técnicos
                foreach ($scheduleData as $tech) {
                    if (!isset($tech['schedule']) || !is_array($tech['schedule'])) {
                        continue;
                    }
                    
                    foreach ($tech['schedule'] as $event) {
                        // Obter user_id
                        $stmtUser->execute([$event['username']]);
                        $user = $stmtUser->fetch();
                        
                        if (!$user) continue;
                        
                        // Remove registro existente para este dia/usuário para permitir atualização (evita duplicados)
                        $stmtDelete->execute([$user['id'], $event['date']]);

                        // Inserir novo registro
                        $stmtInsert->execute([
                            $user['id'],
                            $event['date'],
                            $event['shift'],
                            ''
                        ]);
                        $insertCount++;
                    }
                }
                
                $pdo->commit();
                $message = "Importação concluída! $insertCount registros adicionados à escala de $month/$year.";
                $messageType = 'success';
                $previewData = [];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Erro em import_schedules.php: ' . $e->getMessage());
                $message = 'Erro interno ao importar os dados.';
                $messageType = 'error';
            }
        }
    }
    }
}
?><!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Escala | Microgate Informática</title>
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
        #header-placeholder nav {
            top: 0 !important;
        }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>
            
            <main class="flex-1 pt-32 md:pt-52 pb-20">
                <div class="max-w-6xl mx-auto px-4">
                    <div class="mb-12 flex justify-between items-start">
                        <div>
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Importar Escala</h1>
                            <p class="text-gray-400">Adicione escalas de novos meses facilmente</p>
                        </div>
                        <a href="restricted.php" class="bg-gray-600 hover:bg-gray-700 border-2 border-gray-500 text-white font-semibold py-2 px-4 md:py-3 md:px-8 rounded-lg transition flex items-center gap-2 w-full md:w-auto justify-center md:justify-start text-sm md:text-base">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Voltar
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <?php
                        $bgColor = 'bg-green-500/10';
                        $borderColor = 'border-green-500/50';
                        $textColor = 'text-green-400';
                        $iconType = 'check-circle';
                        
                        if ($messageType === 'error') {
                            $bgColor = 'bg-red-500/10';
                            $borderColor = 'border-red-500/50';
                            $textColor = 'text-red-400';
                            $iconType = 'alert-circle';
                        } elseif ($messageType === 'warning') {
                            $bgColor = 'bg-yellow-500/10';
                            $borderColor = 'border-yellow-500/50';
                            $textColor = 'text-yellow-400';
                            $iconType = 'alert-circle';
                        }
                        ?>
                        <div class="mb-6 <?= $bgColor ?> border <?= $borderColor ?> rounded-lg p-4 flex items-center gap-3">
                            <i data-lucide="<?= $iconType ?>" class="w-5 h-5 <?= $textColor ?>"></i>
                            <p class="<?= $textColor ?>"><?= $message ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($previewData)): ?>
                        <div class="bg-brand-dark border border-white/10 rounded-lg p-8">
                            <h2 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                                <i data-lucide="upload" class="w-6 h-6"></i>
                                Upload do Arquivo
                            </h2>

                            <div class="mb-8 p-4 bg-gray-500/10 border border-gray-500/50 rounded-lg">
                                <p class="text-gray-400 text-sm"><strong>Dica:</strong> Seu arquivo CSV deve ter:</p>
                                <ul class="text-gray-400 text-sm mt-2 ml-4 list-disc">
                                    <li>Primeira coluna: Nomes dos técnicos</li>
                                    <li>Próximas colunas: Datas no formato <strong>DD/MM/YYYY</strong> (sempre 2 dígitos: 01, 02, ... 30)</li>
                                    <li>Valores nas células: AGENDA, FOLGA, FÉRIAS ou vazio</li>
                                    <li>Separador: Ponto-e-vírgula (;)</li>
                                </ul>
                                <div class="bg-orange-500/20 border border-orange-500/50 rounded p-2 mt-3">
                                    <p class="text-gray-300 text-xs"><strong>⚠️ IMPORTANTE:</strong> O Excel pode corromper as datas! Use o modelo fornecido e salve como CSV (separado por ponto-e-vírgula).</p>
                                </div>
                                <p class="text-gray-400 text-sm mt-3 flex flex-col gap-2">
                                    <a href="./db/modelo_escala_04_2026.csv" download class="inline-flex items-center gap-1 hover:text-gray-300 transition">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                        📥 Baixar modelo corrigido (Recomendado)
                                    </a>
                                    <a href="./debug_usuarios.php" target="_blank" class="inline-flex items-center gap-1 hover:text-gray-300 transition">
                                        <i data-lucide="search" class="w-4 h-4"></i>
                                        🔍 Ver quais usuários serão encontrados
                                    </a>
                                </p>
                            </div>

                            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-3">Selecione o arquivo CSV</label>
                                    <div class="relative">
                                        <input type="file" name="schedule_file" accept=".csv" required class="hidden" id="file-input" onchange="updateFileName(this)">
                                        <label for="file-input" class="block w-full bg-white/5 border-2 border-dashed border-white/20 rounded-lg p-8 text-center cursor-pointer hover:bg-white/10 transition">
                                            <i data-lucide="file-up" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                                            <p class="text-white font-medium">Clique para selecionar ou arraste o arquivo</p>
                                            <p class="text-gray-400 text-sm mt-1">CSV</p>
                                        </label>
                                        <p id="file-name" class="text-gray-400 text-sm mt-2"></p>
                                    </div>
                                </div>

                                <div class="flex gap-3 pt-4">
                                    <button type="submit" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                        <i data-lucide="upload" class="w-4 h-4"></i>
                                        Processar Arquivo
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="bg-brand-dark border border-white/10 rounded-lg overflow-hidden">
                            <div class="p-6 border-b border-white/10">
                                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                                    <i data-lucide="eye" class="w-6 h-6"></i>
                                    Preview dos Dados
                                </h2>
                                <p class="text-gray-400 text-sm mt-2">Mês: <strong><?= sprintf('%02d/%04d', $importMonth, $importYear) ?></strong></p>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-white/5 border-b border-white/10">
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Técnico (Planilha)</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Usuário (Sistema)</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Dias</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/10">
                                        <?php foreach ($previewData as $tech): ?>
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-6 py-4 text-sm text-white"><?= htmlspecialchars($tech['original_name']) ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-xs font-medium inline-block">
                                                    <?= htmlspecialchars($tech['full_name'] ?: $tech['username']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-sm text-gray-300"><?= $tech['schedule_count'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" class="p-6 border-t border-white/10 space-y-4">
                                <input type="hidden" name="action" value="import">
                                <input type="hidden" name="month" value="<?= $importMonth ?>">
                                <input type="hidden" name="year" value="<?= $importYear ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <?php 
                                    $jsonData = json_encode($previewData, JSON_UNESCAPED_UNICODE);
                                    if ($jsonData === false) {
                                        // Fallback de segurança caso ainda haja caracteres inválidos
                                        $jsonData = json_encode($previewData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                                    }
                                ?>
                                <input type="hidden" name="schedule_data" value="<?= htmlspecialchars($jsonData) ?>">

                                <div class="flex gap-3">
                                    <button type="button" onclick="location.reload()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                        Cancelar
                                    </button>
                                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                        <i data-lucide="check" class="w-4 h-4"></i>
                                        Importar para o Banco
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0]?.name || '';
            document.getElementById('file-name').textContent = fileName ? `Arquivo: ${fileName}` : '';
        }

        lucide.createIcons();
    </script>
</body>

</html>
