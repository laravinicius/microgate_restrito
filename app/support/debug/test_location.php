<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . route_url('login.php'));
    exit;
}

if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

if (!isSuperAdmin()) {
    header('Location: ' . route_url('restricted.php'));
    exit;
}
?><!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Localizacao | Microgate Informatica</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars(asset_url('img/ico.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/components.js')) ?>" defer></script>
    <?php require dirname(__DIR__, 3) . '/components/google-analytics.php'; ?>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>

            <main class="page-main flex-1">
                <div class="max-w-site mx-auto px-4">
                    <?php
                        $pageTitle    = 'Teste de Localizacao';
                        $pageSubtitle = 'Validacao inicial da captura de latitude e longitude no navegador.';
                        $backUrl      = route_url('restricted.php');
                        $backLabel    = 'Voltar ao Painel';
                        require dirname(__DIR__, 3) . '/components/page_header.php';
                    ?>

                    <section class="max-w-4xl bg-brand-dark border border-white/10 rounded-xl p-6 md:p-8">
                        <div class="max-w-2xl">
                            <h2 class="text-white font-bold text-2xl md:text-xl mb-2">Obter localizacao atual</h2>
                            <p class="text-gray-400 text-sm mb-6">
                                Este teste usa apenas o navegador para solicitar permissao e capturar a sua posicao atual.
                            </p>

                            <div class="mb-6 rounded-lg border border-amber-500/20 bg-amber-500/10 p-4">
                                <p class="text-amber-200 text-sm font-medium">Observacao importante</p>
                                <p class="text-amber-100/80 text-sm mt-2">
                                    A geolocalizacao no navegador normalmente so funciona com HTTPS ou em localhost. Se esta pagina estiver em HTTP comum, o navegador pode bloquear sem exibir o pedido de permissao.
                                </p>
                                <p id="browser-context" class="text-amber-100/80 text-sm mt-2"></p>
                            </div>

                            <button
                                type="button"
                                id="get-location-btn"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 border border-emerald-500 text-white font-semibold py-3 px-5 rounded-lg transition text-sm"
                            >
                                <i data-lucide="crosshair" class="w-4 h-4"></i>
                                Obter localizacao
                            </button>

                            <div id="location-feedback" class="mt-6 hidden rounded-lg border border-white/10 bg-white/5 p-4">
                                <p id="location-status" class="text-sm text-gray-300"></p>
                                <div id="location-result" class="hidden mt-4 space-y-3">
                                    <div>
                                        <label class="block text-xs uppercase tracking-wide text-gray-400 mb-2">Latitude e Longitude</label>
                                        <input
                                            type="text"
                                            id="location-coordinates"
                                            readonly
                                            class="w-full rounded-lg border border-white/10 bg-black/20 px-4 py-3 text-white text-sm"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs uppercase tracking-wide text-gray-400 mb-2">Mapa</label>
                                        <div class="overflow-hidden rounded-xl border border-white/10 bg-black/20">
                                            <iframe
                                                id="maps-embed"
                                                title="Mapa da localizacao atual"
                                                src="about:blank"
                                                class="w-full h-80"
                                                loading="lazy"
                                                referrerpolicy="no-referrer-when-downgrade"
                                            ></iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('get-location-btn');
            const feedback = document.getElementById('location-feedback');
            const status = document.getElementById('location-status');
            const result = document.getElementById('location-result');
            const coordinates = document.getElementById('location-coordinates');
            const mapsEmbed = document.getElementById('maps-embed');
            const browserContext = document.getElementById('browser-context');

            function updateContextInfo(permissionState) {
                const protocol = window.location.protocol;
                const hostname = window.location.hostname;
                const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(hostname);
                const secureText = window.isSecureContext ? 'contexto seguro' : 'contexto inseguro';
                const permissionText = permissionState ? 'Permissao atual: ' + permissionState + '.' : 'Permissao atual: indisponivel.';

                browserContext.textContent = 'Ambiente detectado: ' + protocol + '//' + hostname + ' (' + secureText + '). ' + permissionText + (isLocalhost ? ' A excecao de localhost esta ativa.' : '');
            }

            function showFeedback(message, isError) {
                feedback.classList.remove('hidden');
                result.classList.add('hidden');
                status.textContent = message;
                status.className = isError ? 'text-sm text-red-400' : 'text-sm text-gray-300';
            }

            function showResult(latitude, longitude) {
                const lat = Number(latitude).toFixed(6);
                const lng = Number(longitude).toFixed(6);
                const mapsUrl = 'https://maps.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng) + '&z=16&output=embed';

                feedback.classList.remove('hidden');
                status.textContent = 'Localizacao obtida com sucesso.';
                status.className = 'text-sm text-emerald-400';
                result.classList.remove('hidden');
                coordinates.value = 'Lat: ' + lat + ' | Long: ' + lng;
                mapsEmbed.src = mapsUrl;

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            async function detectPermissionState() {
                if (!navigator.permissions || !navigator.permissions.query) {
                    updateContextInfo('');
                    return '';
                }

                try {
                    const permission = await navigator.permissions.query({ name: 'geolocation' });
                    updateContextInfo(permission.state);
                    return permission.state;
                } catch (error) {
                    updateContextInfo('');
                    return '';
                }
            }

            detectPermissionState();

            button.addEventListener('click', async function () {
                if (!navigator.geolocation) {
                    showFeedback('Este navegador nao suporta geolocalizacao.', true);
                    return;
                }

                const permissionState = await detectPermissionState();
                const protocol = window.location.protocol;
                const hostname = window.location.hostname;
                const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(hostname);

                if (!window.isSecureContext && !isLocalhost) {
                    showFeedback('O navegador bloqueou a geolocalizacao porque esta pagina nao esta em um contexto seguro. Abra este sistema em HTTPS para o pedido de permissao aparecer.', true);
                    return;
                }

                if (permissionState === 'denied') {
                    showFeedback('A permissao de localizacao ja esta bloqueada neste navegador para este site. Reative nas configuracoes do navegador e tente novamente.', true);
                    return;
                }

                showFeedback('Solicitando permissao e localizacao atual...', false);

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        showResult(position.coords.latitude, position.coords.longitude);
                    },
                    function (error) {
                        const messages = {
                            1: 'Permissao negada para acessar a localizacao.',
                            2: 'Nao foi possivel determinar a localizacao.',
                            3: 'Tempo esgotado ao tentar obter a localizacao.'
                        };

                        showFeedback(messages[error.code] || 'Ocorreu um erro ao obter a localizacao.', true);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        });
    </script>
</body>

</html>
