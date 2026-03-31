<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once APP_ROOT . '/app/auth/auth_audit.php';

// F-06 FIX: usar helper centralizado (nível >= 1)
requireAdmin();

ensureAuthAuditTable($pdo);

$search = trim((string)($_GET['q'] ?? ''));
$eventType = trim((string)($_GET['event'] ?? ''));
$success = ($_GET['success'] ?? '') === '' ? '' : (string)$_GET['success'];
$onlyTechnicians = !isset($_GET['only_technicians']) || $_GET['only_technicians'] === '1';

$sql = "SELECT l.id, l.user_id, l.username, l.event_type, l.success, l.ip_address, l.user_agent, l.details, l.created_at, u.full_name
        FROM auth_access_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (l.username LIKE :search_q1 OR u.full_name LIKE :search_q2)";
    $params[':search_q1'] = '%' . $search . '%';
    $params[':search_q2'] = '%' . $search . '%';
}

if ($eventType !== '') {
    $sql .= " AND l.event_type = :event_type";
    $params[':event_type'] = $eventType;
}

if ($success === '0' || $success === '1') {
    $sql .= " AND l.success = :success";
    $params[':success'] = (int)$success;
}

if ($onlyTechnicians) {
    $sql .= " AND COALESCE(u.is_admin, 0) = 0";
}

$sql .= " ORDER BY l.created_at DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="pt-br" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Acesso | Microgate</title>
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
                        $pageTitle    = 'Logs de Auditoria';
                        $pageSubtitle = 'Últimos 300 eventos de login, logout e falhas';
                        $backUrl      = route_url('restricted.php');
                        require APP_ROOT . '/components/page_header.php';
                    ?>

                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6 bg-white/5 border border-white/10 rounded-lg p-4">
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Filtrar por usuário ou nome"
                            class="bg-white/5 border border-white/10 rounded px-3 py-2 text-white placeholder-gray-400"
                        >
                        <select name="event" class="bg-white/5 border border-white/10 rounded px-3 py-2 text-white">
                            <option value="" class="text-black">Todos eventos</option>
                            <?php foreach (['login_success', 'login_failed', 'login_rate_limited', 'logout'] as $opt): ?>
                                <option value="<?= $opt ?>" class="text-black" <?= $eventType === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="success" class="bg-white/5 border border-white/10 rounded px-3 py-2 text-white">
                            <option value="" class="text-black">Sucesso/Falha</option>
                            <option value="1" class="text-black" <?= $success === '1' ? 'selected' : '' ?>>Sucesso</option>
                            <option value="0" class="text-black" <?= $success === '0' ? 'selected' : '' ?>>Falha</option>
                        </select>
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded px-3 py-2">Aplicar Filtros</button>
                        <label class="md:col-span-4 inline-flex items-center gap-2 text-sm text-gray-300 select-none">
                            <input type="hidden" name="only_technicians" value="0">
                            <input
                                type="checkbox"
                                name="only_technicians"
                                value="1"
                                <?= $onlyTechnicians ? 'checked' : '' ?>
                                class="rounded border border-white/10 bg-white/5 text-gray-600 focus:ring-gray-500"
                            >
                            Ignorar administradores e gerentes, mostrando apenas técnicos
                        </label>
                    </form>

                    <div class="bg-brand-dark border border-white/10 rounded-lg overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Data/Hora</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Usuário</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Nome Completo</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Evento</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">IP</th>
                                    <th class="px-4 py-3 text-left text-xs text-gray-400 uppercase">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php if (!$logs): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Nenhum evento encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="hover:bg-white/5">
                                            <td class="px-4 py-3 text-sm text-gray-300 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                                            <td class="px-4 py-3 text-sm text-white">
                                                <?= htmlspecialchars($log['username']) ?>
                                                <?php if (!empty($log['user_id'])): ?>
                                                    <span class="text-gray-400">(ID <?= (int)$log['user_id'] ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-300"><?= htmlspecialchars((string)($log['full_name'] ?? '-')) ?></td>
                                            <td class="px-4 py-3 text-sm text-white"><?= htmlspecialchars($log['event_type']) ?></td>
                                            <td class="px-4 py-3 text-sm">
                                                <?php if ((int)$log['success'] === 1): ?>
                                                    <span class="text-green-400">Sucesso</span>
                                                <?php else: ?>
                                                    <span class="text-red-400">Falha</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-300"><?= htmlspecialchars($log['ip_address']) ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-400">
                                                <?= htmlspecialchars((string)$log['details']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
