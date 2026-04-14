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

$today  = date('Y-m-d');
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT km_start, km_end, photo_start, photo_end, saved_at_start, saved_at_end, log_date
     FROM mileage_logs
     WHERE user_id = :uid AND log_date = :today
     LIMIT 1"
);
$stmt->execute([':uid' => $userId, ':today' => $today]);
$existingLog = $stmt->fetch();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quilometragem | Microgate Informática</title>
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
                                Voltar para Escala
                            </a>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-bold text-white mb-1">Quilometragem</h1>
                        <p class="text-gray-400 text-sm">Registre o KM do veículo no início e no fim do turno</p>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p id="display-date" class="text-white font-semibold text-lg"></p>
                            <p id="display-time" class="text-gray-400 text-sm"></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div id="step-start" class="w-8 h-8 rounded-full border-2 border-purple-500 bg-purple-500/20 flex items-center justify-center text-xs font-bold text-white">1</div>
                            <div id="step-line" class="step-line w-12"></div>
                            <div id="step-end"   class="w-8 h-8 rounded-full border-2 border-gray-500  bg-gray-700      flex items-center justify-center text-xs font-bold text-gray-300">2</div>
                        </div>
                    </div>

                    <!-- ── KM INICIAL ── -->
                    <div id="card-start" class="km-card mb-4 <?= $existingLog ? 'done' : '' ?>">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="play-circle" class="w-5 h-5 text-green-400"></i>
                                <h2 class="text-white font-semibold">KM Inicial</h2>
                            </div>
                            <?php if ($existingLog && $existingLog['km_start'] !== null): ?>
                                <span class="status-badge done"><i data-lucide="check" class="w-3 h-3"></i> Registrado</span>
                            <?php else: ?>
                                <span class="status-badge pending">Pendente</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($existingLog && $existingLog['km_start'] !== null): ?>
                            <p class="text-center text-3xl font-bold text-white tracking-widest mb-1">
                                <?= number_format($existingLog['km_start'], 0, ',', '.') ?>
                                <span class="text-gray-400 text-lg font-normal">km</span>
                            </p>
                            <?php if (!empty($existingLog['saved_at_start'])): ?>
                                <p class="text-center text-gray-400 text-sm mb-3">
                                    <i data-lucide="clock" class="w-3 h-3 inline-block mr-1 opacity-60"></i>
                                    <?= date('H:i', strtotime($existingLog['saved_at_start'])) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($existingLog['photo_start']): ?>
                            <div class="photo-area km-photo-static">
                                <img src="<?= htmlspecialchars(action_url('km/serve_km_photo.php') . '?file=' . urlencode($existingLog['photo_start'])) ?>" alt="Foto KM inicial">
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Quilometragem atual</label>
                                    <input type="number" id="km-start" class="km-input" placeholder="Ex: 54320" min="0" inputmode="numeric">
                                </div>
                                <div>
                                    <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Foto do painel</label>
                                    <div class="photo-area" id="photo-start-area" onclick="document.getElementById('photo-start-input').click()">
                                        <div class="photo-placeholder">
                                            <i data-lucide="camera" class="w-8 h-8"></i>
                                            <span>Toque para tirar foto</span>
                                        </div>
                                    </div>
                                    <input type="file" id="photo-start-input" accept="image/*" capture="environment" class="hidden" onchange="previewPhoto(this,'photo-start-area','photo-start-data')">
                                    <input type="hidden" id="photo-start-data">
                                </div>
                                <button class="btn-primary" id="btn-start" onclick="saveKm('start')" disabled>
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    Salvar KM Inicial
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── KM FINAL ── -->
                    <div id="card-end" class="km-card <?= (!$existingLog || $existingLog['km_start'] === null) ? 'locked' : '' ?> <?= ($existingLog && $existingLog['km_end'] !== null) ? 'done' : '' ?>">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="stop-circle" class="w-5 h-5 text-red-400"></i>
                                <h2 class="text-white font-semibold">KM Final</h2>
                            </div>
                            <?php if ($existingLog && $existingLog['km_end'] !== null): ?>
                                <span class="status-badge done"><i data-lucide="check" class="w-3 h-3"></i> Registrado</span>
                            <?php elseif (!$existingLog || $existingLog['km_start'] === null): ?>
                                <span class="status-badge pending">Aguardando KM inicial</span>
                            <?php else: ?>
                                <span class="status-badge pending">Pendente</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($existingLog && $existingLog['km_end'] !== null): ?>
                            <p class="text-center text-3xl font-bold text-white tracking-widest mb-1">
                                <?= number_format($existingLog['km_end'], 0, ',', '.') ?>
                                <span class="text-gray-400 text-lg font-normal">km</span>
                            </p>
                            <?php if (!empty($existingLog['saved_at_end'])): ?>
                                <p class="text-center text-gray-400 text-sm mb-3">
                                    <i data-lucide="clock" class="w-3 h-3 inline-block mr-1 opacity-60"></i>
                                    <?= date('H:i', strtotime($existingLog['saved_at_end'])) ?>
                                </p>
                            <?php endif; ?>
                            <div class="mt-3 p-3 rounded-lg bg-white/5 text-center">
                                <p class="text-gray-400 text-xs uppercase tracking-wider">Total rodado hoje</p>
                                <p class="text-white font-bold text-xl mt-1">
                                    <?= number_format($existingLog['km_end'] - $existingLog['km_start'], 0, ',', '.') ?> km
                                </p>
                            </div>
                            <?php if ($existingLog['photo_end']): ?>
                            <div class="photo-area mt-3 km-photo-static">
                                <img src="<?= htmlspecialchars(action_url('km/serve_km_photo.php') . '?file=' . urlencode($existingLog['photo_end'])) ?>" alt="Foto KM final">
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Quilometragem atual</label>
                                    <input type="number" id="km-end" class="km-input" placeholder="Ex: 54587" min="0" inputmode="numeric">
                                </div>
                                <div>
                                    <label class="text-gray-400 text-xs uppercase tracking-wider mb-2 block">Foto do painel</label>
                                    <div class="photo-area" id="photo-end-area" onclick="document.getElementById('photo-end-input').click()">
                                        <div class="photo-placeholder">
                                            <i data-lucide="camera" class="w-8 h-8"></i>
                                            <span>Toque para tirar foto</span>
                                        </div>
                                    </div>
                                    <input type="file" id="photo-end-input" accept="image/*" capture="environment" class="hidden" onchange="previewPhoto(this,'photo-end-area','photo-end-data')">
                                    <input type="hidden" id="photo-end-data">
                                </div>
                                <button class="btn-primary" id="btn-end" onclick="saveKm('end')" disabled>
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    Salvar KM Final
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

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
                Enviando dados, não feche a janela
            </p>
        </div>
    </div>

    <script>
    // ─── DATA E HORA DO DISPOSITIVO ────────────────────────────────────────────
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

    function getDeviceDate() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    }

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

    // ─── PREVIEW E COMPRESSÃO DE FOTO ─────────────────────────────────────────
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
                    else       { w = Math.round(w * MAX / h); h = MAX; }
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                const compressed = canvas.toDataURL('image/jpeg', 0.70);
                const area = document.getElementById(areaId);
                area.innerHTML = `<img src="${compressed}" class="km-preview-image">
                    <div class="km-preview-note">
                        Toque para trocar
                    </div>`;
                document.getElementById(dataId).value = compressed.split(',')[1];
                validateBtn(areaId.includes('start') ? 'btn-start' : 'btn-end');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }

    // ─── VALIDAÇÃO DOS BOTÕES ─────────────────────────────────────────────────
    function validateBtn(btnId) {
        const suffix = btnId === 'btn-start' ? 'start' : 'end';
        const km     = document.getElementById(`km-${suffix}`)?.value;
        const photo  = document.getElementById(`photo-${suffix}-data`)?.value;
        const btn    = document.getElementById(btnId);
        if (btn) btn.disabled = !(km && km > 0 && photo);
    }

    document.getElementById('km-start')?.addEventListener('input', () => validateBtn('btn-start'));
    document.getElementById('km-end')?.addEventListener('input',   () => validateBtn('btn-end'));

    // ─── ENVIO AJAX ───────────────────────────────────────────────────────────
    async function saveKm(type) {
        const suffix = type === 'start' ? 'start' : 'end';
        const kmVal  = document.getElementById(`km-${suffix}`)?.value;
        const photo  = document.getElementById(`photo-${suffix}-data`)?.value;
        const btn    = document.getElementById(`btn-${suffix}`);

        if (!kmVal || !photo) return;

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> Salvando...';
        lucide.createIcons();

        try {
            showLocationOverlay('Enviando dados, não feche a janela');
            const coords = await getLocationForSave();

            const res = await fetch(window.APP_ROUTES?.saveKm || '/app/actions/km/save_km.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: <?= json_encode($_SESSION['csrf_token']) ?>,
                    type:       type,
                    km:         parseInt(kmVal, 10),
                    photo:      photo,
                    log_date:   getDeviceDate(),
                    lat:        coords ? coords.lat : null,
                    lng:        coords ? coords.lng : null
                })
            });

            const data = await res.json();
            hideLocationOverlay();

            if (data.success) {
                showToast('Registrado com sucesso!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Erro ao salvar.', 'error');
                btn.disabled = false;
                btn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Salvar KM ${type === 'start' ? 'Inicial' : 'Final'}`;
                lucide.createIcons();
            }
        } catch (err) {
            hideLocationOverlay();
            showToast(err?.message || 'Erro de conexão. Tente novamente.', 'error');
            btn.disabled = false;
            btn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Salvar KM ${type === 'start' ? 'Inicial' : 'Final'}`;
            lucide.createIcons();
        }
    }

    // ─── TOAST ────────────────────────────────────────────────────────────────
    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className   = `show ${type}`;
        setTimeout(() => { t.className = ''; }, 3500);
    }

    // ─── STEP VISUAL ──────────────────────────────────────────────────────────
    <?php if ($existingLog && $existingLog['km_start'] !== null): ?>
    document.getElementById('step-start').classList.add('step-circle-done');
    document.getElementById('step-line').classList.add('done');
    document.getElementById('step-end').classList.add('step-circle-active');
    <?php endif; ?>
    <?php if ($existingLog && $existingLog['km_end'] !== null): ?>
    document.getElementById('step-end').classList.remove('step-circle-active');
    document.getElementById('step-end').classList.add('step-circle-done');
    <?php endif; ?>

    lucide.createIcons();
    </script>
</body>
</html>
