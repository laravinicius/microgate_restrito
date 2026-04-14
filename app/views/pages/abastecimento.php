<?php

require dirname(__DIR__, 3) . '/app/bootstrap.php';

requireLogin();

if (isAdmin()) {
    header('Location: ' . route_url('restricted.php'));
    exit;
}

if (!hasFuelAccess()) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

$today = date('Y-m-d');
$userId = (int)$_SESSION['user_id'];
$dayStart = $today . ' 00:00:00';
$dayEnd = $today . ' 23:59:59';

$stmt = $pdo->prepare(
    "SELECT fueled_at, fuel_price, liters, total_amount, current_km
     FROM fuel_logs
     WHERE user_id = :uid
       AND fueled_at BETWEEN :start_day AND :end_day
     ORDER BY fueled_at DESC, id DESC"
);
$stmt->execute([
    ':uid' => $userId,
    ':start_day' => $dayStart,
    ':end_day' => $dayEnd,
]);
$todayLogs = $stmt->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abastecimento | Microgate Informatica</title>
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

            <main class="flex-1 pt-24 md:pt-52 pb-24">
                <div class="max-w-lg mx-auto px-4">

                    <div class="mb-8">
                        <div class="mb-4">
                            <a href="<?= htmlspecialchars(route_url('escala.php')) ?>" class="bg-gray-600 hover:bg-gray-700 border-2 border-gray-500 text-white font-semibold py-2 px-4 rounded-lg transition inline-flex items-center gap-2 text-sm">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Voltar para escala
                            </a>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-bold text-white mb-1">Abastecimento</h1>
                        <p class="text-gray-400 text-sm">Registre o abastecimento</p>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p id="display-date" class="text-white font-semibold text-lg"></p>
                            <p id="display-time" class="text-gray-400 text-sm"></p>
                        </div>
                    </div>

                    <div class="km-card mb-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="fuel" class="w-5 h-5 text-orange-400"></i>
                                <h2 class="text-white font-semibold">Novo abastecimento</h2>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Valor da gasolina atual</label>
                                <input type="number" id="fuel-price" class="km-input" placeholder="Ex: 6.49" min="0" step="0.01" inputmode="decimal">
                            </div>

                            <div>
                                <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Litros abastecidos</label>
                                <input type="number" id="liters" class="km-input" placeholder="Ex: 20" min="0" step="0.001" inputmode="decimal">
                            </div>

                            <div>
                                <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Valor total abastecido</label>
                                <input type="text" id="total-amount" class="km-input" value="R$ 0,00" readonly>
                            </div>

                            <div>
                                <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">KM atual (opcional)</label>
                                <input type="number" id="current-km" class="km-input" placeholder="Ex: 54320" min="0" inputmode="numeric">
                            </div>

                            <div>
                                <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Foto do comprovante</label>
                                <div class="photo-area" id="photo-fuel-area" onclick="document.getElementById('photo-fuel-input').click()">
                                    <div class="photo-placeholder">
                                        <i data-lucide="camera" class="w-8 h-8"></i>
                                        <span>Toque para tirar foto</span>
                                    </div>
                                </div>
                                <input type="file" id="photo-fuel-input" accept="image/*" capture="environment" class="hidden" onchange="previewPhoto(this, 'photo-fuel-area', 'photo-fuel-data')">
                                <input type="hidden" id="photo-fuel-data">
                            </div>

                            <button class="btn-primary" id="btn-fuel" onclick="saveFuel()" disabled>
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Salvar abastecimento
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($todayLogs)): ?>
                    <div class="km-card">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="history" class="w-5 h-5 text-sky-400"></i>
                            <h2 class="text-white font-semibold">Historico</h2>
                        </div>

                        <div class="space-y-3">
                            <?php foreach ($todayLogs as $log): ?>
                                <div class="p-3 rounded-lg bg-white/5 border border-white/10 text-sm text-gray-200">
                                    <p>
                                        Abastecimento realizado as <?= htmlspecialchars(date('H:i', strtotime((string)$log['fueled_at']))) ?>.
                                        Valor gasolina: R$ <?= htmlspecialchars(number_format((float)$log['fuel_price'], 2, ',', '.')) ?>,
                                        litros abastecidos <?= htmlspecialchars(number_format((float)$log['liters'], 3, ',', '.')) ?>,
                                        valor total: R$ <?= htmlspecialchars(number_format((float)$log['total_amount'], 2, ',', '.')) ?>.
                                    </p>
                                    <?php if ($log['current_km'] !== null): ?>
                                        <p class="text-gray-400 mt-2">KM atual informado: <?= htmlspecialchars(number_format((int)$log['current_km'], 0, ',', '.')) ?> km</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <div id="toast"></div>
    <div id="location-overlay" aria-hidden="true">
        <div class="location-overlay-box">
            <div class="location-spinner"></div>
            <h2 id="location-overlay-title" class="text-white text-xl font-bold">Enviando dados</h2>
            <p id="location-overlay-message" class="location-overlay-note">
                Enviando dados, nao feche a janela
            </p>
        </div>
    </div>

    <script>
    (function updateClock() {
        const now = new Date();
        document.getElementById('display-date').textContent = now.toLocaleDateString('pt-BR', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        });
        document.getElementById('display-time').textContent = now.toLocaleTimeString('pt-BR', {
            hour: '2-digit', minute: '2-digit'
        });
        setTimeout(updateClock, (60 - now.getSeconds()) * 1000);
    })();

    const locationState = {
        cached: null,
        fetchedAt: 0,
        pendingPromise: null
    };

    function showLocationOverlay(message) {
        document.getElementById('location-overlay-message').textContent = message;
        document.getElementById('location-overlay').classList.add('open');
    }

    function hideLocationOverlay() {
        document.getElementById('location-overlay').classList.remove('open');
    }

    function cacheLocation(latitude, longitude) {
        locationState.cached = {
            lat: Number(latitude),
            lng: Number(longitude)
        };
        locationState.fetchedAt = Date.now();
        return locationState.cached;
    }

    function getFreshCachedLocation() {
        if (!locationState.cached) return null;
        const ageMs = Date.now() - locationState.fetchedAt;
        return ageMs <= 2 * 60 * 1000 ? locationState.cached : null;
    }

    function requestCurrentLocation(options = {}) {
        const {
            timeout = 15000,
            maximumAge = 0,
            enableHighAccuracy = true
        } = options;

        if (!navigator.geolocation) {
            return Promise.reject(new Error('Este dispositivo nao suporta geolocalizacao.'));
        }

        if (locationState.pendingPromise) {
            return locationState.pendingPromise;
        }

        locationState.pendingPromise = new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const coords = cacheLocation(position.coords.latitude, position.coords.longitude);
                    locationState.pendingPromise = null;
                    resolve(coords);
                },
                (error) => {
                    locationState.pendingPromise = null;
                    const messages = {
                        1: 'A localizacao do dispositivo nao foi autorizada.',
                        2: 'Nao foi possivel determinar a localizacao atual.',
                        3: 'Tempo esgotado ao obter a localizacao. Tente novamente.'
                    };
                    reject(new Error(messages[error.code] || 'Falha ao obter a localizacao atual.'));
                },
                {
                    enableHighAccuracy,
                    timeout,
                    maximumAge
                }
            );
        });

        return locationState.pendingPromise;
    }

    async function getLocationForSave() {
        const cached = getFreshCachedLocation();
        if (cached) return cached;

        try {
            return await requestCurrentLocation({ timeout: 12000, maximumAge: 0, enableHighAccuracy: true });
        } catch (error) {
            return locationState.cached || null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (navigator.geolocation) {
            requestCurrentLocation({ timeout: 8000, maximumAge: 60000, enableHighAccuracy: true }).catch(() => {});
        }
    });

    function formatMoney(value) {
        return value.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function calculateTotal() {
        const fuelPrice = parseFloat((document.getElementById('fuel-price').value || '0').replace(',', '.'));
        const liters = parseFloat((document.getElementById('liters').value || '0').replace(',', '.'));
        const total = (Number.isFinite(fuelPrice) ? fuelPrice : 0) * (Number.isFinite(liters) ? liters : 0);
        document.getElementById('total-amount').value = formatMoney(total);
        validateFuelBtn();
    }

    function previewPhoto(input, areaId, dataId) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const MAX = 1280;
                let w = img.width, h = img.height;
                if (w > MAX || h > MAX) {
                    if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                    else { w = Math.round(w * MAX / h); h = MAX; }
                }
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                const compressed = canvas.toDataURL('image/jpeg', 0.70);
                const area = document.getElementById(areaId);
                area.innerHTML = `<img src="${compressed}" class="km-preview-image">
                    <div class="km-preview-note">Toque para trocar</div>`;
                document.getElementById(dataId).value = compressed.split(',')[1];
                validateFuelBtn();
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }

    function validateFuelBtn() {
        const fuelPrice = parseFloat((document.getElementById('fuel-price').value || '0').replace(',', '.'));
        const liters = parseFloat((document.getElementById('liters').value || '0').replace(',', '.'));
        const photo = document.getElementById('photo-fuel-data').value;
        const btn = document.getElementById('btn-fuel');
        btn.disabled = !(Number.isFinite(fuelPrice) && fuelPrice > 0 && Number.isFinite(liters) && liters > 0 && photo);
    }

    document.getElementById('fuel-price').addEventListener('input', calculateTotal);
    document.getElementById('liters').addEventListener('input', calculateTotal);

    async function saveFuel() {
        const fuelPriceRaw = document.getElementById('fuel-price').value;
        const litersRaw = document.getElementById('liters').value;
        const currentKmRaw = document.getElementById('current-km').value;
        const photo = document.getElementById('photo-fuel-data').value;
        const btn = document.getElementById('btn-fuel');

        const fuelPrice = parseFloat((fuelPriceRaw || '0').replace(',', '.'));
        const liters = parseFloat((litersRaw || '0').replace(',', '.'));

        if (!Number.isFinite(fuelPrice) || fuelPrice <= 0 || !Number.isFinite(liters) || liters <= 0 || !photo) {
            showToast('Preencha os campos obrigatorios.', 'error');
            return;
        }

        let currentKm = null;
        if (currentKmRaw !== '') {
            const parsedKm = parseInt(currentKmRaw, 10);
            if (!Number.isFinite(parsedKm) || parsedKm < 0) {
                showToast('KM atual invalido.', 'error');
                return;
            }
            currentKm = parsedKm;
        }

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> Salvando...';
        lucide.createIcons();

        try {
            showLocationOverlay('Enviando dados, nao feche a janela');
            const coords = await getLocationForSave();

            const res = await fetch(window.APP_ROUTES?.saveFuel || '/app/actions/km/save_fuel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: <?= json_encode($_SESSION['csrf_token']) ?>,
                    fuel_price: fuelPrice,
                    liters: liters,
                    current_km: currentKm,
                    photo: photo,
                    lat: coords ? coords.lat : null,
                    lng: coords ? coords.lng : null
                })
            });

            const data = await res.json();
            hideLocationOverlay();

            if (data.success) {
                showToast('Abastecimento registrado com sucesso!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Erro ao salvar.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Salvar abastecimento';
                lucide.createIcons();
            }
        } catch (err) {
            hideLocationOverlay();
            showToast(err?.message || 'Erro de conexao. Tente novamente.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Salvar abastecimento';
            lucide.createIcons();
        }
    }

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = `show ${type}`;
        setTimeout(() => { t.className = ''; }, 3500);
    }

    lucide.createIcons();
    </script>
</body>
</html>
