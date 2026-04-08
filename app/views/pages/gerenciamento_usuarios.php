<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once APP_ROOT . '/forgot_password_requests.php';

// 1. Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: ' . route_url('login.php'));
    exit;
}

// 2. Apenas administradores (nível >= 1) têm acesso
if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

$isAdmin = ((int)$_SESSION['is_admin'] === 1);
ensurePasswordResetRequestsTable($pdo);

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ações POST ────────────────────────────────────────────────────────────────

// Desabilitar/habilitar usuário (apenas Super Admin)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_user_status') {
    $id_to_toggle = (int)($_POST['user_id'] ?? 0);
    $token        = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
        exit;
    }

    if ($id_to_toggle > 0 && $id_to_toggle !== (int)$_SESSION['user_id']) {
        // Obtém o status atual
        $checkStmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $checkStmt->execute([$id_to_toggle]);
        $user = $checkStmt->fetch();

        if ($user) {
            $currentStatus = (int)$user['is_active'];
            $newStatus = $currentStatus === 1 ? 0 : 1;
            
            $updateStmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $id_to_toggle]);
            
            $msgKey = $newStatus === 1 ? 'user_enabled' : 'user_disabled';
            header('Location: ' . route_url('gerenciamento_usuarios.php?msg=' . $msgKey));
            exit;
        }
    }
}

// Deletar usuário
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $id_to_delete = (int)($_POST['delete_user_id'] ?? 0);
    $token        = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
        exit;
    }

    if ($id_to_delete > 0 && $id_to_delete !== (int)$_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        header('Location: ' . route_url('gerenciamento_usuarios.php?msg=deleted'));
        exit;
    }
}

// Marcar reset como atendido
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_reset_handled') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $token     = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
        exit;
    }

    if ($requestId > 0) {
        $markStmt = $pdo->prepare(
            "UPDATE password_reset_requests
             SET status = 'handled', handled_at = NOW(), handled_by = :handled_by
             WHERE id = :id AND status = 'pending'"
        );
        $markStmt->execute([':handled_by' => (int)$_SESSION['user_id'], ':id' => $requestId]);
        header('Location: ' . route_url('gerenciamento_usuarios.php?msg=reset_handled'));
        exit;
    }
}

// Deletar notificação de reset
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_reset_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $token     = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: ' . route_url('gerenciamento_usuarios.php?error=csrf'));
        exit;
    }

    if ($requestId > 0) {
        $deleteStmt = $pdo->prepare("DELETE FROM password_reset_requests WHERE id = :id");
        $deleteStmt->execute([':id' => $requestId]);
        header('Location: ' . route_url('gerenciamento_usuarios.php?msg=reset_deleted'));
        exit;
    }
}

// ── Consultas ──────────────────────────────────────────────────────────────────
$stmt   = $pdo->query("SELECT id, username, full_name, is_admin, is_active FROM users ORDER BY is_admin DESC, full_name ASC");
$usuarios = $stmt->fetchAll();

$resetStmt = $pdo->query(
    "SELECT prr.id, prr.username, prr.phone, prr.status, prr.requested_at, prr.handled_at,
            handler.username AS handled_by_username
     FROM password_reset_requests prr
     LEFT JOIN users handler ON handler.id = prr.handled_by
     ORDER BY prr.requested_at DESC
     LIMIT 50"
);
$resetRequests = $resetStmt->fetchAll();

$pendingResetCount = 0;
foreach ($resetRequests as $r) {
    if (($r['status'] ?? '') === 'pending') $pendingResetCount++;
}
?><!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários | Microgate Informática</title>
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
                        $pageTitle    = 'Gerenciamento de Usuários';
                        $pageSubtitle = 'Criação, edição e exclusão de contas';
                        $backUrl      = 'restricted.php';
                        require APP_ROOT . '/components/page_header.php';
                    ?>

                    <!-- Mensagens de feedback -->
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="mb-6 bg-green-500/10 border border-green-500/50 rounded-lg p-4 flex items-center gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>
                            <p class="text-green-400">
                                <?= match($_GET['msg']) {
                                    'deleted'       => 'Usuário excluído com sucesso.',
                                    'user_created'  => 'Usuário criado com sucesso.',
                                    'user_updated'  => 'Usuário atualizado com sucesso.',
                                    'user_enabled'  => 'Usuário habilitado com sucesso.',
                                    'user_disabled' => 'Usuário desabilitado com sucesso.',
                                    'reset_handled' => 'Solicitação de reset marcada como atendida.',
                                    'reset_deleted' => 'Notificação de reset excluída.',
                                    default         => 'Operação realizada com sucesso.'
                                } ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="mb-6 bg-red-500/10 border border-red-500/50 rounded-lg p-4 flex items-center gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-400"></i>
                            <p class="text-red-400">
                                <?= match($_GET['error']) {
                                    'username_invalid' => 'Nome de usuário inválido (mínimo 3 caracteres).',
                                    'username_short'   => 'Nome de usuário muito curto (mínimo 3 caracteres).',
                                    'username_empty'   => 'Nome de usuário é obrigatório.',
                                    'fullname_empty'   => 'Nome completo é obrigatório.',
                                    'username_exists'  => 'Este nome de usuário já está em uso.',
                                    'password_empty'   => 'Senha é obrigatória.',
                                    'password_short'   => 'Senha deve ter pelo menos 8 caracteres.',
                                    'password_invalid' => 'Senha deve ter pelo menos 8 caracteres.',
                                    'invalid_role'     => 'Nível de acesso inválido.',
                                    'csrf'             => 'Sessão expirada ou requisição inválida. Tente novamente.',
                                    'db_error'         => 'Erro no banco de dados. Tente novamente.',
                                    default            => 'Ocorreu um erro. Tente novamente.'
                                } ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                    <!-- ── Notificações de Reset de Senha ── -->
                    <?php if (!empty($resetRequests)): ?>
                    <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2 mb-5">
                            <i data-lucide="bell-ring" class="w-5 h-5 text-yellow-400"></i>
                            Solicitações de Reset de Senha
                            <?php if ($pendingResetCount > 0): ?>
                                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingResetCount ?></span>
                            <?php endif; ?>
                        </h2>
                        <div class="space-y-3">
                            <?php foreach ($resetRequests as $req): ?>
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 rounded-lg
                                    <?= $req['status'] === 'pending' ? 'bg-yellow-500/10 border border-yellow-500/20' : 'bg-white/3 border border-white/5 opacity-60' ?>">
                                    <div>
                                        <p class="text-white font-semibold text-sm"><?= htmlspecialchars($req['username']) ?></p>
                                        <p class="text-gray-400 text-xs">Celular: <?= htmlspecialchars($req['phone']) ?></p>
                                        <p class="text-gray-500 text-xs mt-0.5">
                                            <?= date('d/m/Y H:i', strtotime($req['requested_at'])) ?>
                                            <?php if ($req['status'] === 'handled'): ?>
                                                · Atendido por <?= htmlspecialchars($req['handled_by_username'] ?? 'desconhecido') ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="mark_reset_handled">
                                                <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold py-2 px-3 rounded-lg transition inline-flex items-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i> Atendido
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="bg-green-500/20 text-green-400 text-xs font-semibold px-3 py-1 rounded-full">Atendido</span>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Apagar esta notificação?')" class="inline">
                                            <input type="hidden" name="action" value="delete_reset_request">
                                            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <button type="submit" class="bg-white/5 hover:bg-red-600/80 text-gray-400 hover:text-white text-xs font-semibold py-2 px-3 rounded-lg transition inline-flex items-center gap-1">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── Cadastrar Novo Usuário ── -->
                    <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">
                        <h2 class="text-xl font-bold text-white mb-5 flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-5 h-5 text-blue-400"></i>
                            Cadastrar Novo Usuário
                        </h2>
                        <form action="<?= htmlspecialchars(action_url('users/cadastro_usuario_post.php')) ?>" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div>
                                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Usuário</label>
                                <input type="text" name="username"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                                    placeholder="usuario" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nome Completo</label>
                                <input type="text" name="full_name"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                                    placeholder="Nome do Técnico" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Senha</label>
                                <input type="password" name="password"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                                    placeholder="Mín. 8 caracteres" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nível</label>
                                <select name="is_admin"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm">
                                    <option value="0">Padrão</option>
                                    <option value="3">Gerente KM</option>
                                    <option value="2">Gerente</option>
                                    <option value="1">Administrador</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Criar Conta
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- ── Tabela de Usuários ── -->
                    <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                <i data-lucide="users" class="w-5 h-5 text-gray-400"></i>
                                Usuários Cadastrados
                                <span class="text-gray-500 font-normal text-sm">(<?= count($usuarios) ?>)</span>
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-white/10 bg-white/3">
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Usuário</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome Completo</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Nível</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                        <?php if ($isAdmin): ?>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Ações</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <?php foreach ($usuarios as $user): ?>
                                    <tr class="hover:bg-white/3 transition">
                                        <td class="px-6 py-4 text-sm text-gray-400"><?= $user['id'] ?></td>
                                        <td class="px-6 py-4 text-sm">
                                            <button type="button"
                                                onclick="abrirAgenda(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                                class="text-white hover:text-blue-400 font-semibold transition cursor-pointer inline-flex items-center gap-2 group">
                                                <i data-lucide="calendar" class="w-4 h-4 text-gray-500 group-hover:text-blue-400 transition"></i>
                                                <?= htmlspecialchars($user['username']) ?>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-300"><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <?php
                                            $level = (int)$user['is_admin'];
                                            $levelClass = match($level) {
                                                1 => 'bg-red-500/15 text-red-400',
                                                2 => 'bg-purple-500/15 text-purple-400',
                                                3 => 'bg-yellow-500/15 text-yellow-400',
                                                default => 'bg-gray-500/15 text-gray-400'
                                            };
                                            $levelLabel = match($level) {
                                                1 => 'Admin',
                                                2 => 'Gerente',
                                                3 => 'Gerente KM',
                                                default => 'Padrão'
                                            };
                                            ?>
                                            <span class="<?= $levelClass ?> px-3 py-1 rounded-full text-xs font-medium inline-block">
                                                <?= $levelLabel ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php
                                            $isActive = (int)($user['is_active'] ?? 1);
                                            $statusClass = $isActive === 1 
                                                ? 'bg-green-500/15 text-green-400' 
                                                : 'bg-red-500/15 text-red-400';
                                            $statusLabel = $isActive === 1 ? '✓ Ativo' : '✗ Desabilitado';
                                            ?>
                                            <span class="<?= $statusClass ?> px-3 py-1 rounded-full text-xs font-medium inline-block">
                                                <?= $statusLabel ?>
                                            </span>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center gap-3">
                                                <button type="button"
                                                    onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES) ?>', <?= $user['is_admin'] ?>)"
                                                    class="text-gray-400 hover:text-white transition inline-flex items-center gap-1 text-sm">
                                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                    Editar
                                                </button>
                                                <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                                    <form method="POST" class="inline-flex">
                                                        <input type="hidden" name="action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <button type="submit" class="text-gray-400 hover:text-blue-400 transition inline-flex items-center gap-1 text-sm" title="<?= (int)$user['is_active'] === 1 ? 'Desabilitar usuário' : 'Habilitar usuário' ?>">
                                                            <i data-lucide="<?= (int)$user['is_active'] === 1 ? 'lock-open' : 'lock' ?>" class="w-4 h-4"></i>
                                                            <?= (int)$user['is_active'] === 1 ? 'Desabilitar' : 'Habilitar' ?>
                                                        </button>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Excluir usuário «<?= htmlspecialchars($user['username']) ?>»?')" class="inline-flex">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="delete_user_id" value="<?= (int)$user['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <button type="submit" class="text-gray-600 hover:text-red-400 transition inline-flex items-center gap-1 text-sm">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                            Excluir
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-gray-600 text-xs italic">Sua conta</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Modal de edição de usuário -->
    <div id="editModal" class="modal-overlay hidden">
        <div class="edit-modal-box">
            <h3 class="text-xl font-bold text-white mb-5 flex items-center gap-2">
                <i data-lucide="edit-2" class="w-5 h-5 text-blue-400"></i>
                Editar Usuário
            </h3>
            <form action="<?= htmlspecialchars(action_url('users/edit_user_post.php')) ?>" method="POST" class="space-y-4">
                <input type="hidden" name="user_id"    id="editUserId"  value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nome de Usuário</label>
                    <input type="text" name="username" id="editUsername"
                        class="w-full border border-white/10 rounded-lg px-3 py-2.5 placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nome Completo</label>
                    <input type="text" name="full_name" id="editFullName"
                        class="w-full border border-white/10 rounded-lg px-3 py-2.5 placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nova Senha <span class="text-gray-600 normal-case font-normal">(deixe vazio para manter)</span></label>
                    <input type="password" name="new_password" id="editPassword"
                        class="w-full border border-white/10 rounded-lg px-3 py-2.5 placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        placeholder="Mín. 8 caracteres">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nível de Acesso</label>
                    <select name="is_admin" id="editAdmin"
                        class="w-full border border-white/10 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm">
                        <option value="0">Padrão</option>
                        <option value="3">Gerente KM</option>
                        <option value="2">Gerente</option>
                        <option value="1">Administrador</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Salvar
                    </button>
                    <button type="button" onclick="closeEditModal()" class="edit-modal-cancel-btn">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, username, fullName, isAdmin) {
            document.getElementById('editUserId').value  = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editAdmin').value   = isAdmin;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        // ── Modal de Agenda ──────────────────────────────────────────────
        function abrirAgenda(userId, username) {
            const modal  = document.getElementById('agendaModal');
            const iframe = document.getElementById('agendaIframe');
            const title  = document.getElementById('agendaTitle');

            title.textContent = 'Agenda — ' + username;
            iframe.src = `visualizar_agenda.php?user_id=${userId}`;
            modal.classList.remove('hidden');
            document.body.classList.add('no-scroll');
        }

        function closeAgendaModal() {
            const modal  = document.getElementById('agendaModal');
            const iframe = document.getElementById('agendaIframe');
            modal.classList.add('hidden');
            iframe.src = '';
            document.body.classList.remove('no-scroll');
        }

        document.getElementById('agendaModal').addEventListener('click', function (e) {
            if (e.target === this) closeAgendaModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAgendaModal();
                closeEditModal();
            }
        });

        lucide.createIcons();
    </script>

    <!-- ── Modal de Agenda (iframe) ── -->
    <div id="agendaModal" class="hidden">
        <div class="agenda-box">

            <div class="agenda-modal-header">
                <div class="agenda-modal-title-wrap">
                    <i data-lucide="calendar-days" class="w-4 h-4 text-gray-400 flex-shrink-0"></i>
                    <span id="agendaTitle" class="agenda-modal-title"></span>
                </div>
                <button onclick="closeAgendaModal()" class="agenda-modal-close">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Fechar
                </button>
            </div>

            <iframe id="agendaIframe" src="" class="agenda-iframe" title="Agenda do técnico"></iframe>
        </div>
    </div>
</body>

</html>
