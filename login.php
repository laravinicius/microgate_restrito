<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    if ((int)($_SESSION['is_admin'] ?? 0) >= 1) {
        header('Location: restricted.php');
    } else {
        header('Location: escala.php');
    }
    exit;
}

// Exibe mensagem de erro quando redirecionado por login_post.php
$error_msg = '';
if (!empty($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_msg = 'Usuário ou senha inválidos. Verifique suas credenciais.';
            break;
        case '2':
            $error_msg = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
            break;
        default:
            $error_msg = 'Erro ao efetuar login.';
    }
}

$forgot_error_msg = '';
if (!empty($_GET['forgot_error'])) {
    switch ($_GET['forgot_error']) {
        case 'invalid_username':
            $forgot_error_msg = 'Informe um usuário válido para solicitar o reset.';
            break;
        case 'invalid_phone':
            $forgot_error_msg = 'Informe um número de celular válido para contato.';
            break;
        case 'user_not_found':
            $forgot_error_msg = 'Usuário não encontrado ou inativo.';
            break;
        default:
            $forgot_error_msg = 'Não foi possível registrar sua solicitação.';
    }
}

$forgot_success_msg = '';
if (!empty($_GET['forgot_msg']) && $_GET['forgot_msg'] === 'requested') {
    $forgot_success_msg = 'Solicitação enviada com sucesso. A equipe entrará em contato no celular informado.';
}

$show_forgot_panel = ($forgot_error_msg !== '' || $forgot_success_msg !== '');
?>
<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Microgate Informática</title>
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

            <main class="flex-1 flex items-center justify-center px-4 py-20">
                <div class="w-full max-w-md">
                    <!-- Card de Login -->
                    <div class="bg-brand-dark border border-white/10 rounded-lg overflow-hidden">
                        <!-- Header do Card -->
                        <div class="p-8 border-b border-white/10 text-center">
                            <h1 class="text-3xl font-bold text-white mb-2">Área Restrita</h1>
                            <p class="text-gray-400 text-sm">Faça login para acessar o painel</p>
                        </div>

                        <!-- Formulário -->
                        <div class="p-8">
                            <?php if (!empty($error_msg)): ?>
                                <div class="mb-4 p-3 rounded bg-red-600/95 text-white flex items-center gap-3">
                                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                                    <div class="text-sm"><?php echo htmlspecialchars($error_msg); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($forgot_success_msg)): ?>
                                <div class="mb-4 p-3 rounded bg-green-600/95 text-white flex items-center gap-3">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                    <div class="text-sm"><?php echo htmlspecialchars($forgot_success_msg); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($forgot_error_msg)): ?>
                                <div class="mb-4 p-3 rounded bg-red-600/95 text-white flex items-center gap-3">
                                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                                    <div class="text-sm"><?php echo htmlspecialchars($forgot_error_msg); ?></div>
                                </div>
                            <?php endif; ?>
                            <form method="post" action="login_post.php" class="space-y-5">
                                <!-- Campo Username -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Nome de Usuário</label>
                                    <div class="relative">
                                        
                                        <input 
                                            type="text" 
                                            id="username"
                                            name="username" 
                                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-12 pr-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" 
                                            placeholder="usuário"
                                            required
                                            autofocus>
                                    </div>
                                </div>

                                <!-- Campo Senha -->
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Senha</label>
                                    <div class="relative">
                                        <input 
                                            type="password" 
                                            id="password"
                                            name="password" 
                                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-12 pr-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition" 
                                            placeholder="••••••••"
                                            required>
                                    </div>
                                </div>

                                <!-- Botão Login -->
                                <button 
                                    type="submit" 
                                    class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2 mt-8">
                                    <i data-lucide="log-in" class="w-5 h-5"></i>
                                    Entrar
                                </button>
                            </form>

                            <div class="mt-8 border-t border-white/10 pt-6">
                                <button
                                    type="button"
                                    id="toggle-forgot-password"
                                    class="w-full bg-white/5 hover:bg-white/10 border border-white/10 text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2"
                                    aria-expanded="<?= $show_forgot_panel ? 'true' : 'false' ?>"
                                    aria-controls="forgot-password-panel">
                                    <i data-lucide="key-round" class="w-5 h-5"></i>
                                    Esqueci minha senha
                                </button>

                                <div id="forgot-password-panel" class="mt-4 <?= $show_forgot_panel ? '' : 'hidden' ?>">
                                    <p class="text-gray-400 text-sm mb-4">Informe seu usuário e celular para solicitar o reset manual da senha.</p>
                                    <form method="post" action="forgot_password_request_post.php" class="space-y-4">
                                        <div>
                                            <label for="forgot-username" class="block text-sm font-medium text-gray-300 mb-2">Nome de Usuário</label>
                                            <input
                                                type="text"
                                                id="forgot-username"
                                                name="username"
                                                class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition"
                                                placeholder="usuário"
                                                required>
                                        </div>
                                        <div>
                                            <label for="forgot-phone" class="block text-sm font-medium text-gray-300 mb-2">Celular para contato</label>
                                            <input
                                                type="tel"
                                                id="forgot-phone"
                                                name="phone"
                                                class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-transparent outline-none transition"
                                                placeholder="(00) 00000-0000"
                                                required>
                                        </div>
                                        <button
                                            type="submit"
                                            class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                            <i data-lucide="send" class="w-5 h-5"></i>
                                            Enviar Solicitação
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensagem de Segurança -->
                    <div class="mt-8 text-center text-gray-400 text-sm">
                        <p>Seus dados são protegidos e criptografados.</p>
                    </div>
                </div>
            </main>

        </div>
    </div>
    <script>
        const forgotToggleBtn = document.getElementById('toggle-forgot-password');
        const forgotPanel = document.getElementById('forgot-password-panel');

        if (forgotToggleBtn && forgotPanel) {
            forgotToggleBtn.addEventListener('click', function () {
                const isHidden = forgotPanel.classList.toggle('hidden');
                forgotToggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            });
        }
    </script>
</body>

</html>
