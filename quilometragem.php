<?php

require __DIR__ . '/bootstrap.php';

requireLogin();

if (isAdmin()) {
    header('Location: restricted.php');
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
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quilometragem | Microgate Informática</title>
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

        @media (min-width: 768px) {
            main { margin-top: 120px !important; }
        }

        .km-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 24px;
        }
        .km-card.done   { border-color: rgba(74,222,128,0.3); background: rgba(74,222,128,0.04); }
        .km-card.locked { opacity: 0.55; pointer-events: none; }

        .km-input {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            padding: 12px 16px;
            width: 100%;
            text-align: center;
            letter-spacing: 2px;
        }
        .km-input:focus { outline: none; border-color: rgba(167,139,250,0.6); background: rgba(255,255,255,0.08); }
        .km-input::placeholder { color: rgba(255,255,255,0.2); font-size: 1rem; letter-spacing: 0; }

        .photo-area {
            border: 2px dashed rgba(255,255,255,0.15);
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s;
            background: rgba(0,0,0,0.2);
            position: relative;
        }
        .photo-area:hover { border-color: rgba(167,139,250,0.5); }
        .photo-area img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
        .photo-area .photo-placeholder {
            display: flex; flex-direction: column;
            align-items: center; gap: 8px;
            color: rgba(255,255,255,0.35);
            font-size: 0.85rem;
        }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-badge.pending { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .status-badge.done    { background: rgba(74,222,128,0.15);  color: #4ade80; }

        .btn-primary {
            background: #7c3aed;
            border: none; border-radius: 8px;
            color: white; font-weight: 600;
            padding: 14px 24px; width: 100%;
            cursor: pointer; font-size: 1rem;
            transition: background 0.2s, opacity 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover:not(:disabled) { background: #6d28d9; }
        .btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }

        #toast {
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            padding: 12px 24px; border-radius: 8px;
            font-size: 0.9rem; font-weight: 500;
            opacity: 0; transition: opacity 0.3s;
            z-index: 9999; white-space: nowrap;
            pointer-events: none;
        }
        #toast.show    { opacity: 1; }
        #toast.success { background: #166534; color: #bbf7d0; border: 1px solid #4ade80; }
        #toast.error   { background: #7f1d1d; color: #fecaca; border: 1px solid #ef4444; }

        .step-line { height: 2px; flex: 1; background: rgba(255,255,255,0.1); transition: background 0.4s; }
        .step-line.done { background: #4ade80; }
    </style>
</head>

<body>
    <div class="boxed-layout">
        <div class="content-wrapper min-h-screen flex flex-col">
            <div id="header-placeholder"></div>

            <main class="flex-1 pt-24 md:pt-52 pb-24">
                <div class="max-w-lg mx-auto px-4">

                    <div class="mb-8">
                        <div class="mb-4">
                            <a href="escala.php" class="bg-gray-600 hover:bg-gray-700 border-2 border-gray-500 text-white font-semibold py-2 px-4 rounded-lg transition inline-flex items-center gap-2 text-sm">
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
                            <div class="photo-area" style="cursor:default;">
                                <img src="serve_km_photo.php?file=<?= urlencode($existingLog['photo_start']) ?>" alt="Foto KM inicial">
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
                                            <i data-lucide="camera" style="width:32px;height:32px;"></i>
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
                            <div class="photo-area mt-3" style="cursor:default;">
                                <img src="serve_km_photo.php?file=<?= urlencode($existingLog['photo_end']) ?>" alt="Foto KM final">
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
                                            <i data-lucide="camera" style="width:32px;height:32px;"></i>
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
                area.innerHTML = `<img src="${compressed}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                    <div style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.6);padding:4px 8px;border-radius:4px;color:white;font-size:0.7rem;">
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
            const res = await fetch('save_km.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: <?= json_encode($_SESSION['csrf_token']) ?>,
                    type:       type,
                    km:         parseInt(kmVal, 10),
                    photo:      photo,
                    log_date:   getDeviceDate(),
                })
            });

            const data = await res.json();

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
            showToast('Erro de conexão. Tente novamente.', 'error');
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
    document.getElementById('step-start').style.cssText = 'background:#166534;border-color:#4ade80;color:#bbf7d0;';
    document.getElementById('step-line').classList.add('done');
    document.getElementById('step-end').style.cssText = 'background:rgba(139,92,246,0.2);border-color:#7c3aed;color:white;';
    <?php endif; ?>
    <?php if ($existingLog && $existingLog['km_end'] !== null): ?>
    document.getElementById('step-end').style.cssText = 'background:#166534;border-color:#4ade80;color:#bbf7d0;';
    <?php endif; ?>

    lucide.createIcons();
    </script>
</body>
</html>