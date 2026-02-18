<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: restricted.php');
    exit;
}

// Exibe mensagem de erro quando redirecionado por login_post.php
$error_msg = '';
if (!empty($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_msg = 'Usuário ou senha inválidos. Verifique suas credenciais.';
            break;
        default:
            $error_msg = 'Erro ao efetuar login.';
    }
}
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
                            <form method="post" action="login_post.php" class="space-y-5">
                                <!-- Campo Username -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Nome de Usuário</label>
                                    <div class="relative">
                                        
                                        <input 
                                            type="text" 
                                            id="username"
                                            name="username" 
                                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-12 pr-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition" 
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
                                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-12 pr-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition" 
                                            placeholder="••••••••"
                                            required>
                                    </div>
                                </div>

                                <!-- Botão Login -->
                                <button 
                                    type="submit" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2 mt-8">
                                    <i data-lucide="log-in" class="w-5 h-5"></i>
                                    Entrar
                                </button>
                            </form>
                        </div>

                        <!-- Footer do Card -->
                        <div class="px-8 py-4 bg-white/5 border-t border-white/10 text-center text-xs text-gray-500">
                            Acesso exclusivo para administradores
                        </div>
                    </div>

                    <!-- Mensagem de Segurança -->
                    <div class="mt-8 text-center text-gray-400 text-sm">
                        <p>Seus dados são protegidos e criptografados.</p>
                    </div>
                </div>
            </main>

            <footer id="footer-placeholder" class="bg-brand-dark border-t border-white/10"></footer>
        </div>
    </div>
</body>

</html>