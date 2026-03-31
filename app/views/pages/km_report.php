<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap.php';

// Gerente KM (nível 3) também tem acesso
requireLogin();
if (!isAdmin() && !isKmManager()) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$techStmt = $pdo->query(
    "SELECT id, full_name, username FROM users WHERE is_admin = 0 AND is_active = 1 ORDER BY full_name ASC"
);
$technicians = $techStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Quilometragem | Microgate Informática</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars(asset_url('img/ico.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/components.js')) ?>" defer></script>
    <?php require APP_ROOT . '/components/google-analytics.php'; ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        #header-placeholder nav { top: 0 !important; }

        .filter-input {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            color: white;
            padding: 10px 14px;
            font-size: 0.875rem;
            width: 100%;
            transition: border-color 0.2s;
        }
        .filter-input:focus { outline: none; border-color: rgba(167,139,250,0.5); }
        .filter-input option { background: #1a1a2e; color: white; }

        /* Atalhos de período — só visíveis quando técnico selecionado */
        .period-shortcuts { display: none; }
        .period-shortcuts.visible { display: flex; }

        .summary-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
        }

        .km-table { width: 100%; border-collapse: collapse; }
        .km-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            white-space: nowrap;
        }
        .km-table td {
            padding: 12px 16px;
            font-size: 0.875rem;
            color: #d1d5db;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }
        .km-table tr:hover td { background: rgba(255,255,255,0.03); }
        .km-table tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600; white-space: nowrap;
        }
        .badge-done    { background: rgba(74,222,128,0.12);  color: #4ade80; }
        .badge-partial { background: rgba(251,191,36,0.12);  color: #fbbf24; }
        .badge-missing { background: rgba(239,68,68,0.12);   color: #f87171; }

        .btn-photo {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 500;
            background: rgba(167,139,250,0.1); color: #a78bfa;
            border: 1px solid rgba(167,139,250,0.2);
            cursor: pointer; transition: background 0.2s;
        }
        .btn-photo:hover { background: rgba(167,139,250,0.2); }

        #photo-modal {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.85);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        #photo-modal.open { display: flex; }
        #photo-modal .modal-box {
            background: #111827;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; overflow: hidden;
            max-width: 600px; width: 100%;
        }
        #photo-modal img { width: 100%; display: block; max-height: 70vh; object-fit: contain; background: #000; }
        #photo-modal .modal-footer {
            padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .skeleton {
            background: linear-gradient(90deg,rgba(255,255,255,0.04) 25%,rgba(255,255,255,0.08) 50%,rgba(255,255,255,0.04) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 4px; height: 14px;
        }
        @keyframes shimmer { to { background-position: -200% 0; } }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 8px;
            background: #7c3aed; color: white;
            font-size: 0.875rem; font-weight: 600;
            border: none; cursor: pointer; transition: background 0.2s;
        }
        .btn-primary:hover { background: #6d28d9; }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: #d1d5db; font-size: 0.875rem; font-weight: 500;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); }

        .shortcut-btn {
            padding: 6px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05); color: #d1d5db;
            font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: background 0.15s;
            white-space: nowrap;
        }
        .shortcut-btn:hover { background: rgba(255,255,255,0.12); }
        .shortcut-btn.active { background: rgba(167,139,250,0.15); border-color: rgba(167,139,250,0.4); color: #c4b5fd; }

        #manual-modal {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.85);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        #manual-modal.open { display: flex; }
        #manual-modal .modal-box {
            background: #111827;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            width: 100%;
            max-width: 560px;
            overflow: hidden;
        }
        .manual-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        @media (min-width: 768px) {
            .manual-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>

<body>
<div class="boxed-layout">
<div class="content-wrapper min-h-screen flex flex-col">
<div id="header-placeholder"></div>

<main class="page-main flex-1">
<div class="max-w-7xl mx-auto px-4">

    <?php
        $pageTitle    = 'Quilometragem';
        $pageSubtitle = 'Acompanhe o KM rodado por cada técnico';
        $backUrl      = isKmManager() ? '' : 'restricted.php';
        require APP_ROOT . '/components/page_header.php';
    ?>

    <!-- ── Filtros ── -->
    <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">

        <!-- Linha 1: Data + Técnico + Botão -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Data</label>
                <input type="date" id="filter-date" class="filter-input">
            </div>
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Técnico <span class="text-gray-600 normal-case">(opcional)</span></label>
                <select id="filter-user" class="filter-input" onchange="onTechChange()">
                    <option value="0">Todos os técnicos</option>
                    <?php foreach ($technicians as $tech): ?>
                    <option value="<?= (int)$tech['id'] ?>">
                        <?= htmlspecialchars($tech['full_name'] ?: $tech['username']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button class="btn-primary w-full justify-center" onclick="loadReport()">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Buscar
                </button>
            </div>
            <div class="flex items-end">
                <button class="btn-secondary w-full justify-center" onclick="openManualModal()">
                    <i data-lucide="square-pen" class="w-4 h-4"></i>
                    Cadastro manual
                </button>
            </div>
        </div>

        <!-- Atalhos de período — só aparecem quando técnico selecionado -->
        <div id="period-shortcuts" class="period-shortcuts flex-wrap gap-2 pt-3 border-t border-white/10">
            <span class="text-xs text-gray-500 self-center mr-1">Período:</span>
            <button class="shortcut-btn" id="btn-hoje"        onclick="setRange('hoje')">Hoje</button>
            <button class="shortcut-btn" id="btn-7dias"       onclick="setRange('7dias')">Últimos 7 dias</button>
            <button class="shortcut-btn" id="btn-mes-atual"   onclick="setRange('mes-atual')">Mês atual</button>
        </div>
    </div>

    <!-- Cards de resumo por técnico -->
    <div id="summary-cards" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8"></div>

    <!-- Tabela de registros -->
    <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
        <div class="p-6 border-b border-white/10 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="list" class="w-5 h-5"></i>
                Registros
            </h2>
            <span id="total-badge" class="text-gray-400 text-sm"></span>
        </div>

        <div id="table-loading" class="p-6 space-y-3">
            <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="skeleton w-full" style="height:18px;"></div>
            <?php endfor; ?>
        </div>

        <div id="table-empty" class="hidden p-16 text-center text-gray-500">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p>Nenhum registro encontrado.</p>
        </div>

        <div id="table-wrap" class="hidden overflow-x-auto">
            <table class="km-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Técnico</th>
                        <th class="text-right">KM Inicial</th>
                        <th class="text-center">Entrada</th>
                        <th class="text-center">Saída</th>
                        <th class="text-center">Tempo</th>
                        <th class="text-right">KM Final</th>
                        <th class="text-right">Total rodado</th>
                        <th class="text-center">Localização inicial</th>
                        <th class="text-center">Localização final</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Evidências</th>
                    </tr>
                </thead>
                <tbody id="table-body"></tbody>
            </table>
        </div>
    </div>

</div>
</main>
</div>
</div>

<!-- Modal de cadastro manual -->
<div id="manual-modal" onclick="closeManualModal(event)">
    <div class="modal-box">
        <div class="p-6 border-b border-white/10 flex items-center justify-between">
            <div>
                <h3 class="text-white font-bold text-lg">Cadastro manual</h3>
                <p class="text-gray-400 text-sm mt-1">Preencha os dados obrigatórios para lançar o KM manualmente.</p>
            </div>
            <button onclick="document.getElementById('manual-modal').classList.remove('open')" class="btn-secondary text-sm px-4 py-2">
                <i data-lucide="x" class="w-4 h-4"></i>
                Fechar
            </button>
        </div>

        <form id="manual-form" class="p-6 space-y-5" onsubmit="submitManualEntry(event)">
            <div class="manual-form-grid">
                <div>
                    <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Data</label>
                    <input type="date" id="manual-date" class="filter-input" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Técnico</label>
                    <select id="manual-user" class="filter-input" required>
                        <option value="">Selecione</option>
                        <?php foreach ($technicians as $tech): ?>
                        <option value="<?= (int)$tech['id'] ?>">
                            <?= htmlspecialchars($tech['full_name'] ?: $tech['username']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">KM inicial</label>
                    <input type="number" id="manual-km-start" class="filter-input" min="0" required oninput="updateManualTotal()">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">KM final</label>
                    <input type="number" id="manual-km-end" class="filter-input" min="0" required oninput="updateManualTotal()">
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Total rodado</label>
                <input type="text" id="manual-total" class="filter-input" readonly value="—">
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-end">
                <button type="button" class="btn-secondary justify-center" onclick="document.getElementById('manual-modal').classList.remove('open')">
                    Cancelar
                </button>
                <button type="submit" id="manual-submit-btn" class="btn-primary justify-center">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Salvar cadastro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de foto -->
<div id="photo-modal" onclick="closeModal(event)">
    <div class="modal-box">
        <img id="modal-img" src="" alt="Evidência KM">
        <div class="modal-footer">
            <div>
                <p id="modal-title" class="text-white font-semibold text-sm"></p>
                <p id="modal-subtitle" class="text-gray-400 text-xs mt-0.5"></p>
            </div>
            <button onclick="document.getElementById('photo-modal').classList.remove('open')"
                class="btn-secondary text-sm px-4 py-2">
                <i data-lucide="x" class="w-4 h-4"></i>
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
// ── Estado ────────────────────────────────────────────────────────────────────
let activeShortcut = null;
const MANUAL_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

// ── Init: padrão = hoje, sem técnico selecionado ──────────────────────────────
(function init() {
    document.getElementById('filter-date').value = fmtDate(new Date());
    loadReport();
})();

// ── Quando muda o técnico ─────────────────────────────────────────────────────
function onTechChange() {
    const hasTech = document.getElementById('filter-user').value !== '0';
    const shortcuts = document.getElementById('period-shortcuts');
    shortcuts.classList.toggle('visible', hasTech);

    // Limpa shortcut ativo ao trocar técnico
    setActiveShortcut(null);

    if (!hasTech) {
        // Volta para data única (hoje)
        document.getElementById('filter-date').value = fmtDate(new Date());
    }
}

// ── Atalhos de período ────────────────────────────────────────────────────────
function setRange(range) {
    setActiveShortcut(range);
    const today = new Date();

    if (range === 'hoje') {
        document.getElementById('filter-date').value = fmtDate(today);
    } else if (range === '7dias') {
        // Armazena range nos data attributes do input (usamos dataset)
        const from = new Date(today); from.setDate(from.getDate() - 6);
        document.getElementById('filter-date').dataset.rangeFrom = fmtDate(from);
        document.getElementById('filter-date').dataset.rangeTo   = fmtDate(today);
        document.getElementById('filter-date').value = '';
    } else if (range === 'mes-atual') {
        const from = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('filter-date').dataset.rangeFrom = fmtDate(from);
        document.getElementById('filter-date').dataset.rangeTo   = fmtDate(today);
        document.getElementById('filter-date').value = '';
    }

    loadReport();
}

function setActiveShortcut(range) {
    activeShortcut = range;
    ['hoje','7dias','mes-atual'].forEach(r => {
        const btn = document.getElementById('btn-' + r);
        if (btn) btn.classList.toggle('active', r === range);
    });
}

// Limpa o range armazenado quando o usuário edita a data manualmente
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('filter-date').addEventListener('input', () => {
        delete document.getElementById('filter-date').dataset.rangeFrom;
        delete document.getElementById('filter-date').dataset.rangeTo;
        setActiveShortcut(null);
    });
});

// ── Monta params e carrega dados ──────────────────────────────────────────────
async function loadReport() {
    const userId    = document.getElementById('filter-user').value;
    const dateInput = document.getElementById('filter-date');
    const hasTech   = userId !== '0';

    let dateFrom, dateTo;

    if (hasTech && dateInput.dataset.rangeFrom) {
        // Range de período (atalhos)
        dateFrom = dateInput.dataset.rangeFrom;
        dateTo   = dateInput.dataset.rangeTo;
    } else {
        // Data única
        const d = dateInput.value || fmtDate(new Date());
        dateFrom = d;
        dateTo   = d;
    }

    showLoading(true);

    const params = new URLSearchParams({ user_id: userId, date_from: dateFrom, date_to: dateTo });

    try {
        const res  = await fetch(`${window.APP_ROUTES?.getKmReport || '/app/actions/km/get_km_report.php'}?${params}`);
        const data = await res.json();

        if (!data.success) { showLoading(false); showEmpty(true); return; }

        renderSummary(data.summary);
        renderTable(data.records);
        document.getElementById('total-badge').textContent =
            data.total === 0 ? '' : `${data.total} registro${data.total !== 1 ? 's' : ''}`;

    } catch (e) {
        console.error(e);
        showLoading(false);
        showEmpty(true);
    }
}

// ── Cards de resumo ───────────────────────────────────────────────────────────
function renderSummary(summary) {
    const container = document.getElementById('summary-cards');
    if (!summary || summary.length === 0) { container.innerHTML = ''; return; }
    container.innerHTML = summary.map(s => `
        <div class="summary-card">
            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1 truncate">${esc(s.full_name)}</p>
            <p class="text-white text-2xl font-bold">${s.total_driven.toLocaleString('pt-BR')} <span class="text-gray-400 text-sm font-normal">km</span></p>
            <p class="text-gray-500 text-xs mt-1">${s.days_complete} dia${s.days_complete !== 1 ? 's' : ''} completo${s.days_complete !== 1 ? 's' : ''}</p>
        </div>
    `).join('');
}

function openManualModal() {
    document.getElementById('manual-date').value = document.getElementById('filter-date').value || fmtDate(new Date());
    document.getElementById('manual-user').value = document.getElementById('filter-user').value !== '0'
        ? document.getElementById('filter-user').value
        : '';
    document.getElementById('manual-km-start').value = '';
    document.getElementById('manual-km-end').value = '';
    document.getElementById('manual-total').value = '—';
    document.getElementById('manual-modal').classList.add('open');
    lucide.createIcons();
}

function closeManualModal(e) {
    if (e.target.id === 'manual-modal') e.target.classList.remove('open');
}

function updateManualTotal() {
    const kmStart = Number(document.getElementById('manual-km-start').value);
    const kmEnd = Number(document.getElementById('manual-km-end').value);
    const totalField = document.getElementById('manual-total');

    if (!Number.isFinite(kmStart) || !Number.isFinite(kmEnd) || kmStart < 0 || kmEnd < kmStart) {
        totalField.value = '—';
        return;
    }

    totalField.value = `${(kmEnd - kmStart).toLocaleString('pt-BR')} km`;
}

async function submitManualEntry(event) {
    event.preventDefault();

    const date = document.getElementById('manual-date').value;
    const userId = document.getElementById('manual-user').value;
    const kmStart = Number(document.getElementById('manual-km-start').value);
    const kmEnd = Number(document.getElementById('manual-km-end').value);
    const submitBtn = document.getElementById('manual-submit-btn');

    if (!date || !userId || !Number.isFinite(kmStart) || !Number.isFinite(kmEnd)) {
        showToast('Preencha todos os campos obrigatorios.', 'error');
        return;
    }

    if (kmStart < 0 || kmEnd < kmStart) {
        showToast('KM final nao pode ser menor que o KM inicial.', 'error');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> Salvando...';
    lucide.createIcons();

    try {
        const res = await fetch(window.APP_ROUTES?.saveManualKm || '/app/actions/km/save_manual_km.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: MANUAL_CSRF_TOKEN,
                log_date: date,
                user_id: Number(userId),
                km_start: kmStart,
                km_end: kmEnd
            })
        });

        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Erro ao salvar cadastro manual.', 'error');
            return;
        }

        document.getElementById('manual-modal').classList.remove('open');
        showToast('Cadastro manual salvo com sucesso.', 'success');
        loadReport();
    } catch (error) {
        showToast('Erro de conexao ao salvar cadastro manual.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Salvar cadastro';
        lucide.createIcons();
    }
}

// ── Tabela ────────────────────────────────────────────────────────────────────
function renderTable(records) {
    showLoading(false);
    if (!records || records.length === 0) { showEmpty(true); return; }
    showEmpty(false);
    document.getElementById('table-wrap').classList.remove('hidden');

    const tbody = document.getElementById('table-body');
    tbody.innerHTML = records.map(r => {
        const [y, m, d] = r.log_date.split('-');
        const dateLabel = `${d}/${m}/${y}`;
        const weekday   = new Date(r.log_date + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short' });

        const fmtTime = (ts) => {
            if (!ts) return null;
            return new Date(ts.replace(' ', 'T'));
        };
        const tsStart = fmtTime(r.saved_at_start);
        const tsEnd   = fmtTime(r.saved_at_end);

        const fmtHM = (d) => d
            ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : null;

        const timeStart = fmtHM(tsStart) || '<span class="text-gray-600">—</span>';
        const timeEnd   = fmtHM(tsEnd)   || '<span class="text-gray-600">—</span>';

        // Tempo total entre entrada e saída
        let tempoTotal = '<span class="text-gray-600">—</span>';
        if (tsStart && tsEnd) {
            const diffMs  = tsEnd - tsStart;
            const diffMin = Math.round(diffMs / 60000);
            if (diffMin >= 60) {
                const h = Math.floor(diffMin / 60);
                const m = diffMin % 60;
                tempoTotal = `<span class="text-white font-medium">${h}h${m > 0 ? String(m).padStart(2,'0')+'m' : ''}</span>`;
            } else {
                tempoTotal = `<span class="text-white font-medium">${diffMin}min</span>`;
            }
        }

        let badge;
        if (r.km_start !== null && r.km_end !== null)
            badge = '<span class="badge badge-done"><i data-lucide="check" style="width:11px;height:11px;"></i> Completo</span>';
        else if (r.km_start !== null)
            badge = '<span class="badge badge-partial"><i data-lucide="clock" style="width:11px;height:11px;"></i> Só inicial</span>';
        else
            badge = '<span class="badge badge-missing"><i data-lucide="x" style="width:11px;height:11px;"></i> Incompleto</span>';

        const photoStart = r.photo_start_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_start_url)}','${esc(r.full_name)}','KM Inicial - ${dateLabel}')"><i data-lucide="image" style="width:12px;height:12px;"></i> Inicial</button>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const photoEnd = r.photo_end_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_end_url)}','${esc(r.full_name)}','KM Final - ${dateLabel}')"><i data-lucide="image" style="width:12px;height:12px;"></i> Final</button>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const kmStart  = r.km_start  !== null ? r.km_start.toLocaleString('pt-BR')  : '<span class="text-gray-600">—</span>';
        const kmEnd    = r.km_end    !== null ? r.km_end.toLocaleString('pt-BR')    : '<span class="text-gray-600">—</span>';
        const kmDriven = r.km_driven !== null
            ? `<span class="text-white font-semibold">${r.km_driven.toLocaleString('pt-BR')} km</span>`
            : '<span class="text-gray-600">—</span>';
        const locationStart = r.location_start_url
            ? `<a href="${esc(r.location_start_url)}" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-white text-sm">Abrir mapa</a>`
            : '<span class="text-gray-600 text-xs">—</span>';
        const locationEnd = r.location_end_url
            ? `<a href="${esc(r.location_end_url)}" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-white text-sm">Abrir mapa</a>`
            : '<span class="text-gray-600 text-xs">—</span>';

        return `
        <tr>
            <td><p class="text-white font-medium">${dateLabel}</p><p class="text-gray-500 text-xs">${weekday}</p></td>
            <td class="text-white">${esc(r.full_name)}</td>
            <td class="text-right font-mono text-sm">${kmStart}</td>
            <td class="text-center text-sm text-gray-300">${timeStart}</td>
            <td class="text-center text-sm text-gray-300">${timeEnd}</td>
            <td class="text-center text-sm">${tempoTotal}</td>
            <td class="text-right font-mono text-sm">${kmEnd}</td>
            <td class="text-right">${kmDriven}</td>
            <td class="text-center">${locationStart}</td>
            <td class="text-center">${locationEnd}</td>
            <td class="text-center">${badge}</td>
            <td><div class="flex items-center justify-center gap-2">${photoStart}${photoEnd}</div></td>
        </tr>`;
    }).join('');

    lucide.createIcons();
}

// ── States ────────────────────────────────────────────────────────────────────
function showLoading(show) {
    document.getElementById('table-loading').classList.toggle('hidden', !show);
    document.getElementById('table-wrap').classList.add('hidden');
    document.getElementById('table-empty').classList.add('hidden');
}

function showEmpty(show) {
    document.getElementById('table-empty').classList.toggle('hidden', !show);
    if (show) document.getElementById('table-wrap').classList.add('hidden');
}

function showToast(msg, type) {
    const existing = document.getElementById('manual-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'manual-toast';
    toast.textContent = msg;
    toast.style.cssText = [
        'position:fixed',
        'bottom:24px',
        'left:50%',
        'transform:translateX(-50%)',
        'padding:12px 20px',
        'border-radius:10px',
        'z-index:10000',
        'font-size:14px',
        'font-weight:600',
        type === 'success'
            ? 'background:#166534;color:#dcfce7;border:1px solid #4ade80'
            : 'background:#7f1d1d;color:#fecaca;border:1px solid #ef4444'
    ].join(';');

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
}

// ── Modal de foto ─────────────────────────────────────────────────────────────
function openPhoto(url, name, label) {
    document.getElementById('modal-img').src              = url;
    document.getElementById('modal-title').textContent    = name;
    document.getElementById('modal-subtitle').textContent = label;
    document.getElementById('photo-modal').classList.add('open');
    lucide.createIcons();
}

function closeModal(e) {
    if (e.target.id === 'photo-modal') e.target.classList.remove('open');
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function fmtDate(d) { return d.toISOString().slice(0, 10); }

function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

lucide.createIcons();
</script>
</body>
</html>
