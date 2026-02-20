<?php

require __DIR__ . '/bootstrap.php';

// Apenas admin
if (empty($_SESSION['user_id']) || $_SESSION['is_admin'] !== 1) {
    http_response_code(403);
    die('Acesso negado');
}

// Obter todos os usuários do banco
$stmt = $pdo->query('SELECT id, username FROM users ORDER BY username');
$allUsers = $stmt->fetchAll();

// Nomes a testar (do CSV de abril)
$namesToTest = [
    'lucas',
    'ronaldo', 
    'jefferson',
    'paulo.h',
    'paulo.j',
    'joao',
    'igor',
    'marcos',
    'pedro',
    'matheus',
    'adriano'
];

?><!DOCTYPE html>
<html lang="pt-br" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Busca de Usuários | Microgate</title>
    <link rel="shortcut icon" href="./img/ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./js/theme.js"></script>
    <?php require __DIR__ . '/components/google-analytics.php'; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,255,255,0.05); font-weight: 600; color: #9ca3af; }
        .match { color: #4ade80; } /* verde */
        .no-match { color: #ef4444; } /* vermelho */
        .info { max-width: 6xl; margin: 40px auto; padding: 0 20px; }
    </style>
</head>
<body>
    <div class="info">
        <h1 style="color: white; margin-bottom: 20px; font-size: 28px;">🔍 Debug: Busca de Usuários</h1>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="color: white;">Usuários no Sistema</h2>
            <ul style="color: #d1d5db;">
                <?php foreach ($allUsers as $user): ?>
                    <li><strong><?= htmlspecialchars($user['username']) ?></strong> (ID: <?= $user['id'] ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <h2 style="color: white; margin-bottom: 15px;">Teste de Match (CSV de Abril)</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome no CSV</th>
                    <th>Encontrado?</th>
                    <th>Username no Sistema</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($namesToTest as $csvName): ?>
                    <?php
                        $matched = null;
                        $nameLower = strtolower(trim($csvName));
                        
                        // Tentativa 1: Match exato
                        foreach ($allUsers as $user) {
                            if (strtolower($user['username']) === $nameLower) {
                                $matched = $user['username'];
                                break;
                            }
                        }
                        
                        // Tentativa 2: Match com ponto
                        if (!$matched && strpos($nameLower, '.') !== false) {
                            foreach ($allUsers as $user) {
                                if (strpos(strtolower($user['username']), $nameLower) === 0) {
                                    $matched = $user['username'];
                                    break;
                                }
                            }
                        }
                        
                        // Tentativa 3: Por primeiro nome
                        if (!$matched) {
                            $parts = preg_split('/[\s\.]+/', trim($csvName));
                            $first = strtolower($parts[0]);
                            
                            foreach ($allUsers as $user) {
                                if (strpos(strtolower($user['username']), $first) === 0) {
                                    $matched = $user['username'];
                                    break;
                                }
                            }
                        }
                        
                        // Tentativa 4: Fuzzy
                        if (!$matched) {
                            $nameCleaned = strtolower(preg_replace('/[^a-z0-9]/i', '', $csvName));
                            foreach ($allUsers as $user) {
                                $userCleaned = strtolower(preg_replace('/[^a-z0-9]/i', '', $user['username']));
                                if ($userCleaned === $nameCleaned) {
                                    $matched = $user['username'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($csvName) ?></strong></td>
                        <td class="<?= $matched ? 'match' : 'no-match' ?>">
                            <?= $matched ? '✓ SIM' : '✗ NÃO' ?>
                        </td>
                        <td><?= $matched ? htmlspecialchars($matched) : '-' ?></td>
                        <td><?= $matched ? '✓ Importará com sucesso' : '✗ Será pulado' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 20px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.5); border-radius: 8px; color: #93c5fd;">
            <p><strong>💡 Dica:</strong> Se algum nome não foi encontrado, pode ser que:</p>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li>O username no banco está escrito diferente</li>
                <li>Há espaços extras ao cadastrar o usuário</li>
                <li>O sistema ainda não consegue fazer o match automático</li>
            </ul>
            <p style="margin-top: 15px;"><strong>✓ Solução:</strong> Ajuste os nomes no CSV de acordo com a lista acima, ou notifique o desenvolvedor para melhorar a busca.</p>
        </div>

        <div style="margin-top: 20px;">
            <a href="import_schedules.php" style="color: #60a5fa; text-decoration: none;">← Voltar para Importar Escala</a>
        </div>
    </div>
</body>
</html>
