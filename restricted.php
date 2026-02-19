<?php

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

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lógica para Deletar Usuário
if ($isAdmin && isset($_GET['delete']) && isset($_GET['token'])) {
    $id_to_delete = (int)$_GET['delete'];
    $token = $_GET['token'];
    
    // Valida token e impede que o admin delete a si próprio
    if ($token === $_SESSION['csrf_token'] && $id_to_delete !== $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        header('Location: restricted.php?msg=deleted');
        exit;
    }
}

// Busca a lista de usuários para exibir na tabela
$stmt = $pdo->query("SELECT id, username, is_admin FROM users ORDER BY id DESC");
$usuarios = $stmt->fetchAll();
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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        /* Garante que o header carregado via JS fique colado no topo */
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
                    <div class="mb-12 flex flex-col gap-6">
                        <div>
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Painel Administrativo</h1>
                                <p class="text-gray-400"><?= $isAdmin ? 'Gestão de usuários e contas' : 'Visualização de Escalas' ?></p>
                        </div>
                        <div class="flex flex-row items-center justify-between md:justify-start gap-4 border-t border-white/5 pt-6">
                            <p class="text-gray-300 text-sm md:text-base"><span class="text-gray-400">Logado como:</span> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
                            <a href="logout.php" class="bg-red-600 hover:bg-red-700 border-2 border-red-500 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm md:text-base">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                Sair da Conta
                            </a>
                        </div>
                    </div>
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="mb-6 bg-green-500/10 border border-green-500/50 rounded-lg p-4 flex items-center gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>
                            <p class="text-green-400">
                                <?= match($_GET['msg']) {
                                    'deleted' => 'Usuário excluído com sucesso.',
                                    'user_updated' => 'Usuário atualizado com sucesso.',
                                    default => 'Operação realizada com sucesso.'
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
                                    'username_exists' => 'Este nome de usuário já está em uso.',
                                    'password_invalid' => 'Senha deve ter pelo menos 6 caracteres.',
                                    'db_error' => 'Erro ao atualizar usuário. Tente novamente.',
                                    default => 'Ocorreu um erro. Tente novamente.'
                                } ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                        <a href="import_schedules.php" class="bg-brand-dark border border-white/10 rounded-lg p-8 transition group">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                                        <i data-lucide="upload" class="w-6 h-6"></i>
                                        Importar Escala
                                    </h3>
                                    <p class="text-gray-300 text-sm">Adicione escalas de novos meses usando CSV</p>
                                </div>
                                <i data-lucide="arrow-right" class="w-5 h-5 text-gray-200 group-hover:translate-x-1 transition"></i>
                            </div>
                        </a>
                    </div>

                    <div class="bg-brand-dark border border-white/10 rounded-lg p-8 mb-12">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-6 h-6"></i>
                            Cadastrar Novo Usuário
                        </h2>
                        <form action="cadastro_usuario_post.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Nome de Usuário</label>
                                <input type="text" name="username" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" placeholder="Usuário" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Senha</label>
                                <input type="password" name="password" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" placeholder="••••••••" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Nível</label>
                                <select name="is_admin" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition">
                                    <option value="0" class="text-black">Padrão</option>
                                    <option value="2" class="text-black">Gerente</option>
                                    <option value="1" class="text-black">Administrador</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 rounded transition flex items-center justify-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Criar Conta
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="bg-brand-dark border border-white/10 rounded-lg overflow-hidden">
                        <div class="p-6 border-b border-white/10">
                            <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                                <i data-lucide="users" class="w-6 h-6"></i>
                                Usuários Cadastrados
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-white/5 border-b border-white/10">
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome</th>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Nível</th>
                                        <?php if ($isAdmin): ?>
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Ações</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10">
                                    <?php foreach ($usuarios as $user): ?>
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="px-6 py-4 text-sm text-gray-300"><?= $user['id'] ?></td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <button type="button" onclick="abrirAgenda(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="text-white hover:text-gray-400 font-semibold transition cursor-pointer inline-flex items-center gap-2">
                                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                                <?= htmlspecialchars($user['username']) ?>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm">
                                            <span class="<?= $user['is_admin'] ? 'bg-gray-500/20 text-gray-400' : 'bg-gray-500/20 text-gray-400' ?> px-3 py-1 rounded-full text-xs font-medium inline-block">
                                                <?php 
                                                    echo match((int)$user['is_admin']) {
                                                        1 => 'Admin',
                                                        2 => 'Gerente',
                                                        default => 'Padrão'
                                                    };
                                                ?>
                                            </span>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                        <td class="px-6 py-4 text-center text-sm space-x-2 flex justify-center">
                                            <button type="button" onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', <?= $user['is_admin'] ?>)" class="text-white hover:text-gray-300 font-semibold transition inline-flex items-center gap-1">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                                Editar
                                            </button>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <a href="?delete=<?= $user['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Tem certeza que deseja excluir este usuário?')" class="text-white hover:text-gray-300 font-semibold transition inline-flex items-center gap-1">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    Excluir
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-500 italic text-xs">Sua conta</span>
                                            <?php endif; ?>
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

    <div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-brand-dark border border-white/10 rounded-lg p-8 max-w-md w-full">
            <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                <i data-lucide="edit" class="w-6 h-6"></i>
                Editar Usuário
            </h3>
            <form action="edit_user_post.php" method="POST" id="editForm" class="space-y-4">
                <input type="hidden" name="user_id" id="editUserId" value="">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nome de Usuário</label>
                    <input type="text" name="username" id="editUsername" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nova Senha (deixe vazio para manter)</label>
                    <input type="password" name="new_password" id="editPassword" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nível de Acesso</label>
                    <select name="is_admin" id="editAdmin" class="w-full bg-white/5 border border-white/10 rounded px-4 py-2 text-white focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition">
                        <option value="0" class="text-black">Padrão</option>
                        <option value="1" class="text-black">Administrador</option>
                        <option value="2" class="text-black">Gerente</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 rounded transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Salvar
                    </button>
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 rounded transition flex items-center justify-center gap-2">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, username, isAdmin) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editAdmin').value = isAdmin;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Fechar modal ao clicar fora dele
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Função para abrir agenda do técnico em popup
        function abrirAgenda(userId, username) {
            const url = `visualizar_agenda.php?user_id=${userId}`;
            const janela = window.open(url, `agenda_${userId}`, 'width=1200,height=800,toolbar=no,location=no,menubar=no,status=no,resizable=yes');
            if (janela) {
                janela.focus();
            }
        }

        // Renderizar ícones Lucide
        lucide.createIcons();
    </script>
</body>

</html>