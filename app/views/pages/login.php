<?php
require dirname(__DIR__, 3) . '/app/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    if ((int)($_SESSION['is_admin'] ?? 0) >= 1) {
        header('Location: ' . route_url('restricted.php'));
    } else {
        header('Location: ' . route_url('escala.php'));
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
        case 'session_expired':
            $error_msg = 'Sua sessão expirou por inatividade. Faça login novamente.';
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
    <title>Entrar | Restrito</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars(asset_url('img/ico.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>
    <!-- session-guard ANTES dos outros scripts para agir imediatamente -->
    <script src="<?= htmlspecialchars(asset_url('js/session-guard.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/components.js')) ?>" defer></script>
    <?php require APP_ROOT . '/components/google-analytics.php'; ?>
</head>

<body class="min-h-screen bg-[#0f0f10] text-white">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-8">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat login-hero-bg"></div>
            <div class="absolute inset-0 bg-[rgba(8,8,10,0.72)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.14),transparent_38%)]"></div>
            <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[rgba(8,8,10,0.75)] to-transparent"></div>
        </div>

        <div class="relative w-full max-w-[420px]">
            <div class="rounded-[24px] border border-white/12 bg-[rgba(18,18,20,0.55)] p-3 shadow-[0_28px_90px_rgba(0,0,0,0.55)] backdrop-blur-2xl sm:p-4">
                <section class="rounded-[20px] border border-white/10 bg-[rgba(255,255,255,0.04)] px-5 py-6 sm:px-7 sm:py-8">
                    <div class="mb-6 text-center sm:mb-7">
                        <img src="<?= htmlspecialchars(asset_url('img/microgate2.png')) ?>" alt="Microgate" class="mx-auto h-11 w-auto object-contain sm:h-14">
                        <h1 class="mt-5 text-3xl font-bold tracking-tight text-white">Restrito</h1>
                        <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.32em] text-white/55">Entrar</p>
                    </div>

                    <form method="post" action="<?= htmlspecialchars(action_url('auth/login_post.php')) ?>" class="space-y-[18px]" id="login-form">
                        <div>
                            <label for="username" class="mb-2 block text-sm font-medium text-white/82">Usuário</label>
                            <div class="relative">
                                <i data-lucide="user" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35"></i>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="w-full rounded-2xl border border-white/12 bg-white/6 py-3.5 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-white/28 focus:border-white/22 focus:bg-white/10 focus:ring-4 focus:ring-white/8"
                                    placeholder="Informe seu usuário"
                                    required
                                    autofocus>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-white/82">Senha</label>
                            <div class="relative">
                                <i data-lucide="lock-keyhole" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35"></i>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="w-full rounded-2xl border border-white/12 bg-white/6 py-3.5 pl-11 pr-12 text-sm text-white outline-none transition placeholder:text-white/28 focus:border-white/22 focus:bg-white/10 focus:ring-4 focus:ring-white/8"
                                    placeholder="Informe sua senha"
                                    required>
                                <button
                                    type="button"
                                    id="toggle-password"
                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-white/42 transition hover:text-white/72"
                                    aria-label="Mostrar senha"
                                    aria-pressed="false">
                                    <i data-lucide="eye" class="h-5 w-5"></i>
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($error_msg)): ?>
                            <div class="rounded-2xl border border-red-400/20 bg-red-950/45 px-4 py-3 text-sm font-semibold text-red-100">
                                <?php echo htmlspecialchars($error_msg); ?>
                            </div>
                        <?php endif; ?>

                        <button
                            type="submit"
                            id="login-submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/16 bg-white/12 px-4 py-3.5 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:border-white/26 hover:bg-white/18 disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60">
                            <i data-lucide="log-in" class="h-4 w-4"></i>
                            <span id="login-submit-label">Entrar</span>
                        </button>
                    </form>

                    <div class="mt-6 border-t border-white/10 pt-5">
                        <?php if (!empty($forgot_success_msg)): ?>
                            <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-950/35 px-4 py-3 text-sm font-medium text-emerald-100">
                                <?php echo htmlspecialchars($forgot_success_msg); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($forgot_error_msg)): ?>
                            <div class="mb-4 rounded-2xl border border-red-400/20 bg-red-950/45 px-4 py-3 text-sm font-semibold text-red-100">
                                <?php echo htmlspecialchars($forgot_error_msg); ?>
                            </div>
                        <?php endif; ?>

                        <button
                            type="button"
                            id="toggle-forgot-password"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm font-semibold text-white/88 transition hover:border-white/18 hover:bg-white/[0.10]"
                            aria-expanded="<?= $show_forgot_panel ? 'true' : 'false' ?>"
                            aria-controls="forgot-password-panel">
                            <i data-lucide="key-round" class="h-4 w-4"></i>
                            Esqueci minha senha
                        </button>

                        <div id="forgot-password-panel" class="mt-4 <?= $show_forgot_panel ? '' : 'hidden' ?>">
                            <div class="rounded-[18px] border border-white/8 bg-[rgba(255,255,255,0.03)] p-4 sm:p-5">
                                <p class="mb-4 text-sm leading-6 text-white/58">Informe seu usuário e celular para solicitar o reset manual da senha.</p>
                                <form method="post" action="<?= htmlspecialchars(action_url('auth/forgot_password_request_post.php')) ?>" class="space-y-4">
                                    <div>
                                        <label for="forgot-username" class="mb-2 block text-sm font-medium text-white/78">Usuário</label>
                                        <input
                                            type="text"
                                            id="forgot-username"
                                            name="username"
                                            class="w-full rounded-xl border border-white/10 bg-white/6 px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/28 focus:border-white/22 focus:bg-white/10 focus:ring-4 focus:ring-white/8"
                                            placeholder="Informe seu usuário"
                                            required>
                                    </div>
                                    <div>
                                        <label for="forgot-phone" class="mb-2 block text-sm font-medium text-white/78">Celular para contato</label>
                                        <input
                                            type="tel"
                                            id="forgot-phone"
                                            name="phone"
                                            class="w-full rounded-xl border border-white/10 bg-white/6 px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/28 focus:border-white/22 focus:bg-white/10 focus:ring-4 focus:ring-white/8"
                                            placeholder="(00) 00000-0000"
                                            required>
                                    </div>
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/12 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:border-white/24 hover:bg-white/16">
                                        <i data-lucide="send" class="h-4 w-4"></i>
                                        Enviar Solicitação
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs text-white/42">Seus dados são protegidos e criptografados.</p>
                </section>
            </div>
        </div>
    </main>
    <script>
        const forgotToggleBtn = document.getElementById('toggle-forgot-password');
        const forgotPanel = document.getElementById('forgot-password-panel');
        const loginForm = document.getElementById('login-form');
        const loginSubmitBtn = document.getElementById('login-submit');
        const loginSubmitLabel = document.getElementById('login-submit-label');
        const passwordInput = document.getElementById('password');
        const passwordToggleBtn = document.getElementById('toggle-password');

        if (forgotToggleBtn && forgotPanel) {
            forgotToggleBtn.addEventListener('click', function () {
                const isHidden = forgotPanel.classList.toggle('hidden');
                forgotToggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            });
        }

        if (passwordInput && passwordToggleBtn) {
            passwordToggleBtn.addEventListener('click', function () {
                const showPassword = passwordInput.type === 'password';

                passwordInput.type = showPassword ? 'text' : 'password';
                passwordToggleBtn.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
                passwordToggleBtn.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                passwordToggleBtn.innerHTML = showPassword
                    ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                    : '<i data-lucide="eye" class="w-5 h-5"></i>';

                if (window.lucide) {
                    lucide.createIcons();
                }
            });
        }

        if (loginForm && loginSubmitBtn && loginSubmitLabel) {
            loginForm.addEventListener('submit', function () {
                loginSubmitBtn.disabled = true;
                loginSubmitLabel.textContent = 'Entrando...';
            });
        }
    </script>
</body>

</html>
