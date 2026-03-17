<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/forgot_password_requests.php';

// 1. Bloqueia acesso se não estiver logado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Apenas administradores (nível >= 1) têm acesso
if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: escala.php');
    exit;
}

$isAdmin = ((int)$_SESSION['is_admin'] === 1);
ensurePasswordResetRequestsTable($pdo);

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ações POST ────────────────────────────────────────────────────────────────

// Deletar usuário
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $id_to_delete = (int)($_POST['delete_user_id'] ?? 0);
    $token        = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: gerenciamento_usuarios.php?error=csrf');
        exit;
    }

    if ($id_to_delete > 0 && $id_to_delete !== (int)$_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        header('Location: gerenciamento_usuarios.php?msg=deleted');
        exit;
    }
}

// Marcar reset como atendido
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_reset_handled') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $token     = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: gerenciamento_usuarios.php?error=csrf');
        exit;
    }

    if ($requestId > 0) {
        $markStmt = $pdo->prepare(
            "UPDATE password_reset_requests
             SET status = 'handled', handled_at = NOW(), handled_by = :handled_by
             WHERE id = :id AND status = 'pending'"
        );
        $markStmt->execute([':handled_by' => (int)$_SESSION['user_id'], ':id' => $requestId]);
        header('Location: gerenciamento_usuarios.php?msg=reset_handled');
        exit;
    }
}

// Deletar notificação de reset
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_reset_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $token     = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: gerenciamento_usuarios.php?error=csrf');
        exit;
    }

    if ($requestId > 0) {
        $deleteStmt = $pdo->prepare("DELETE FROM password_reset_requests WHERE id = :id");
        $deleteStmt->execute([':id' => $requestId]);
        header('Location: gerenciamento_usuarios.php?msg=reset_deleted');
        exit;
    }
}

// ── Consultas ──────────────────────────────────────────────────────────────────
$stmt   = $pdo->query("SELECT id, username, full_name, is_admin FROM users ORDER BY is_admin DESC, full_name ASC");
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
    <link rel="shortcut icon" href="./img/ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./js/theme.js"></script>
    <script src="./js/components.js" defer></script>
    <?php require __DIR__ . '/components/google-analytics.php'; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        #header-placeholder nav { top: 0 !important; }

        /* Fix: inputs e selects com texto visível no dark mode */
        select, input[type="text"], input[type="password"], input[type="email"], input[type="tel"] {
            color: #ffffff !important;
            background-color: rgba(255,255,255,0.05) !important;
        }
        select option {
            background-color: #1f1f1f;
            color: #ffffff;
        }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>

            <main class="flex-1 pt-32 md:pt-52 pb-20">
                <div class="max-w-6xl mx-auto px-4">

                    <!-- Cabeçalho -->
                    <div class="mb-10 flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                        <div>
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Gerenciamento de Usuários</h1>
                            <p class="text-gray-400">Criação, edição e exclusão de contas</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 items-start">
                            <a href="restricted.php" class="bg-white/10 hover:bg-white/15 border border-white/15 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Voltar ao Painel
                            </a>
                            <a href="logout.php" class="bg-red-600 hover:bg-red-700 border-2 border-red-500 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                Sair
                            </a>
                        </div>
                    </div>

                    <!-- Mensagens de feedback -->
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="mb-6 bg-green-500/10 border border-green-500/50 rounded-lg p-4 flex items-center gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>
                            <p class="text-green-400">
                                <?= match($_GET['msg']) {
                                    'deleted'       => 'Usuário excluído com sucesso.',
                                    'user_created'  => 'Usuário criado com sucesso.',
                                    'user_updated'  => 'Usuário atualizado com sucesso.',
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
                        <form action="cadastro_usuario_post.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                                    <option value="0" class="text-black">Padrão</option>
                                    <option value="2" class="text-black">Gerente</option>
                                    <option value="1" class="text-black">Administrador</option>
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
                                                default => 'bg-gray-500/15 text-gray-400'
                                            };
                                            $levelLabel = match($level) {
                                                1 => 'Admin',
                                                2 => 'Gerente',
                                                default => 'Padrão'
                                            };
                                            ?>
                                            <span class="<?= $levelClass ?> px-3 py-1 rounded-full text-xs font-medium inline-block">
                                                <?= $levelLabel ?>
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
    <div id="editModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-[#1a1a1a] border border-white/10 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-5 flex items-center gap-2">
                <i data-lucide="edit-2" class="w-5 h-5 text-blue-400"></i>
                Editar Usuário
            </h3>
            <form action="edit_user_post.php" method="POST" class="space-y-4">
                <input type="hidden" name="user_id"    id="editUserId"  value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nome de Usuário</label>
                    <input type="text" name="username" id="editUsername"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nome Completo</label>
                    <input type="text" name="full_name" id="editFullName"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nova Senha <span class="text-gray-600 normal-case font-normal">(deixe vazio para manter)</span></label>
                    <input type="password" name="new_password" id="editPassword"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm"
                        placeholder="Mín. 8 caracteres">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Nível de Acesso</label>
                    <select name="is_admin" id="editAdmin"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-white focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition text-sm">
                        <option value="0" class="text-black">Padrão</option>
                        <option value="2" class="text-black">Gerente</option>
                        <option value="1" class="text-black">Administrador</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Salvar
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 bg-white/8 hover:bg-white/15 border border-white/10 text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
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

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeEditModal();
        });

        function abrirAgenda(userId, username) {
            const url = `visualizar_agenda.php?user_id=${userId}`;
            const win = window.open(url, `agenda_${userId}`, 'width=1200,height=800,toolbar=no,location=no,menubar=no,resizable=yes');
            if (win) win.focus();
        }

        lucide.createIcons();
    </script>
</body>

</html>
