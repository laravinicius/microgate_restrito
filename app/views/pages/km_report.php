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
$technicianPayload = array_map(static function (array $tech): array {
    return [
        'id' => (int)$tech['id'],
        'full_name' => $tech['full_name'] ?: $tech['username'],
        'username' => $tech['username'],
    ];
}, $technicians);
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

        .km-table--daily {
            table-layout: fixed;
        }
        .km-table--daily th,
        .km-table--daily td {
            padding: 9px 8px;
            font-size: 0.78rem;
            white-space: normal;
            word-break: break-word;
        }
        .km-table--daily th {
            font-size: 0.65rem;
            letter-spacing: 0.04em;
        }
        .km-table--daily-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 6px;
            border-radius: 999px;
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(96,165,250,0.35);
            color: #bfdbfe;
            font-size: 0.68rem;
            line-height: 1;
            white-space: nowrap;
        }

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

        .km-view-tabs {
            display: inline-flex;
            padding: 4px;
            gap: 4px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
        }

        .km-view-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            background: transparent;
            border: 1px solid transparent;
            color: #9ca3af;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }

        .km-view-tab:hover {
            background: rgba(255,255,255,0.06);
            color: #f3f4f6;
        }

        .km-view-tab.active {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.14);
            color: #ffffff;
        }

        .km-view-panel.hidden { display: none; }

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
        $pageSubtitle = 'Calendário mensal dos lançamentos de km';
        $backUrl      = isKmManager() ? '' : 'restricted.php';
        require APP_ROOT . '/components/page_header.php';
    ?>

    <div class="mb-6 flex justify-start">
        <div class="km-view-tabs" role="tablist" aria-label="Visões de quilometragem">
            <button type="button" id="km-tab-calendar" class="km-view-tab active" data-view="calendar" onclick="setKmView('calendar')">
                <i data-lucide="calendar-days" class="w-4 h-4"></i>
                Calendário
            </button>
            <button type="button" id="km-tab-report" class="km-view-tab" data-view="report" onclick="setKmView('report')">
                <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                Relatório mensal
            </button>
        </div>
    </div>

    <div id="km-view-calendar" class="km-view-panel">
        <!-- ── Calendário ── -->
        <div class="bg-brand-dark border border-white/10 rounded-xl overflow-visible mb-8">
            <div class="flex flex-col gap-4 px-4 md:px-6 py-4 border-b border-white/10">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h2 class="text-white font-bold text-2xl md:text-xl flex items-center gap-2">
                            <i data-lucide="calendar-days" class="w-6 h-6 md:w-5 md:h-5 text-gray-400"></i>
                            Calendário de km
                        </h2>
                        <p class="text-gray-400 text-sm md:text-xs mt-1">Clique em uma data para ver quem lançou km e quem ficou sem lançamento.</p>
                    </div>
                    <button class="btn-secondary justify-center self-start md:self-auto" onclick="openManualModal()">
                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                        Cadastro manual
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs md:text-[11px] text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--green"></span><span>Lançamento</span></span>
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--blue"></span><span>Sem lançamento</span></span>
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--orange"></span><span>Parcial</span></span>
                </div>
            </div>

            <div class="px-4 md:px-6 pt-4 pb-3 border-b border-white/10">
                <div id="month-tab-wrap" class="flex gap-2 overflow-x-auto flex-wrap"></div>
            </div>

            <div class="p-3 md:p-6 space-y-6">
                <div id="km-calendar-wrap"></div>
                <div id="day-detail-panel"></div>

                <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
                    <div class="p-6 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <i data-lucide="list" class="w-5 h-5"></i>
                            Registros do dia
                        </h2>
                        <span id="daily-report-label" class="text-gray-400 text-sm"></span>
                    </div>

                    <div id="day-table-loading" class="p-6 space-y-3">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="skeleton w-full" style="height:18px;"></div>
                        <?php endfor; ?>
                    </div>

                    <div id="day-table-empty" class="hidden p-10 text-center text-gray-500">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                        <p>Nenhum registro neste dia.</p>
                    </div>

                    <div id="day-table-wrap" class="hidden">
                        <table class="km-table km-table--daily">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Técnico</th>
                                    <th class="text-right">KM Inicial</th>
                                    <th class="text-right">KM Final</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-center">Horários</th>
                                    <th class="text-center">Localização</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Evidências</th>
                                </tr>
                            </thead>
                            <tbody id="day-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="km-view-report" class="km-view-panel hidden">
        <!-- Resumo do mês -->
        <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                    Resumo do mês
                </h2>
                <span class="text-gray-400 text-sm" id="total-badge"></span>
            </div>

            <div id="summary-cards" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
        </div>

        <!-- Tabela de registros do mês -->
        <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
            <div class="p-6 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="list" class="w-5 h-5"></i>
                    Registros do mês
                </h2>
                <span id="monthly-report-label" class="text-gray-400 text-sm"></span>
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
const MANUAL_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
const TECHNICIANS = <?= json_encode($technicianPayload, JSON_UNESCAPED_UNICODE) ?>;

const today = new Date();
let currentYear = today.getFullYear();
let currentMonth = today.getMonth();
let selectedDate = fmtDate(today);
let currentRecords = [];
let currentRecordsMap = {};
let currentSummary = [];
let activeKmView = 'calendar';

// ── Init ─────────────────────────────────────────────────────────────────────
(function init() {
    buildMonthTabs();
    loadReport();
})();

function buildMonthTabs() {
    const tabsContainer = document.getElementById('month-tab-wrap');
    if (!tabsContainer) return;

    tabsContainer.innerHTML = '';

    for (let i = 0; i < 6; i++) {
        const date = new Date(today.getFullYear(), today.getMonth() + i, 1);
        const year = date.getFullYear();
        const month = date.getMonth();
        const label = capitalize(date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }));

        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.year = String(year);
        button.dataset.month = String(month);
        button.className = i === 0
            ? 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm md:text-xs transition'
            : 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm md:text-xs transition';
        button.textContent = label;
        button.onclick = () => selectMonth(button, year, month);
        tabsContainer.appendChild(button);
    }
}

function setKmView(view) {
    activeKmView = view;
    document.getElementById('km-view-calendar')?.classList.toggle('hidden', view !== 'calendar');
    document.getElementById('km-view-report')?.classList.toggle('hidden', view !== 'report');

    document.getElementById('km-tab-calendar')?.classList.toggle('active', view === 'calendar');
    document.getElementById('km-tab-report')?.classList.toggle('active', view === 'report');

    lucide.createIcons();
}

async function selectMonth(button, year, month) {
    document.getElementById('month-tab-wrap')?.querySelectorAll('button').forEach(tab => {
        tab.className = 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm md:text-xs transition';
    });
    if (button) {
        button.className = 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm md:text-xs transition';
    }

    currentYear = year;
    currentMonth = month;
    selectedDate = fmtDate(new Date(year, month, 1));
    await loadReport(selectedDate);
}

async function loadReport(reopenDate = null) {
    const start = new Date(currentYear, currentMonth, 1);
    const end = new Date(currentYear, currentMonth + 1, 0);
    const params = new URLSearchParams({
        user_id: '0',
        date_from: fmtDate(start),
        date_to: fmtDate(end),
    });

    showLoading(true);

    try {
        const res = await fetch(`${window.APP_ROUTES?.getKmReport || '/app/actions/km/get_km_report.php'}?${params}`);
        const data = await res.json();

        if (!data.success) {
            showLoading(false);
            showEmpty(true);
            return;
        }

        currentRecords = Array.isArray(data.records) ? data.records : [];
        currentRecordsMap = groupRecordsByDate(currentRecords);
        currentSummary = Array.isArray(data.summary) ? data.summary : [];

        renderSummary(currentSummary);
        renderMonthlyReport(currentRecords);
        document.getElementById('total-badge').textContent =
            data.total === 0 ? '' : `${data.total} registro${data.total !== 1 ? 's' : ''} no mês`;

        const startIso = fmtDate(start);
        const endIso = fmtDate(end);
        const fallbackDate = reopenDate || selectedDate || startIso;
        const initialDate = fallbackDate >= startIso && fallbackDate <= endIso
            ? fallbackDate
            : startIso;

        renderCalendar();
        selectDate(initialDate, false);
    } catch (error) {
        console.error(error);
        showLoading(false);
        showEmpty(true);
    }
}

function groupRecordsByDate(records) {
    return records.reduce((acc, record) => {
        if (!acc[record.log_date]) acc[record.log_date] = [];
        acc[record.log_date].push(record);
        return acc;
    }, {});
}

function renderCalendar() {
    const wrap = document.getElementById('km-calendar-wrap');
    if (!wrap) return;

    const date = new Date(currentYear, currentMonth, 1);
    const firstDay = (date.getDay() + 6) % 7;
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const todayIso = fmtDate(new Date());

    const table = document.createElement('table');
    table.className = 'border-collapse w-full';
    table.style.minWidth = '720px';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    headerRow.className = 'border-b border-white/10';
    ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].forEach((label, index) => {
        const th = document.createElement('th');
        th.style.cssText = 'padding:10px 4px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:' + (index >= 5 ? '#6b7280' : '#9ca3af') + ';';
        th.textContent = label;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    let cell = 0;
    let week = newWeekRow();

    for (let i = 0; i < firstDay; i++) {
        week.appendChild(emptyCell());
        cell++;
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(currentYear, currentMonth, day);
        const iso = fmtDate(currentDate);
        const records = currentRecordsMap[iso] || [];
        const isToday = iso === todayIso;
        const isWeekend = currentDate.getDay() === 0 || currentDate.getDay() === 6;
        const selected = iso === selectedDate;

        const td = document.createElement('td');
        td.className = 'border border-white/5 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5';
        td.style.cssText = 'height:100px;padding:8px;vertical-align:top;' + (isWeekend ? 'background:rgba(255,255,255,0.015);' : '');
        if (selected) td.classList.add('ring-2', 'ring-white/30');
        td.setAttribute('data-date', iso);

        const dayRow = document.createElement('div');
        dayRow.style.cssText = 'display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:5px;gap:8px;';

        const numSpan = document.createElement('span');
        if (isToday) {
            numSpan.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#ffffff;color:#000000;font-size:12px;font-weight:700;flex-shrink:0;';
        } else {
            numSpan.style.cssText = 'font-size:14px;font-weight:700;color:' + (isWeekend ? '#6b7280' : '#e5e7eb') + ';';
        }
        numSpan.textContent = day;
        dayRow.appendChild(numSpan);

        const countWrap = document.createElement('div');
        countWrap.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;gap:1px;text-align:right;';

        if (records.length > 0) {
            const completed = records.filter(record => record.km_start !== null && record.km_end !== null).length;
            const partial = records.filter(record => record.km_start !== null && record.km_end === null).length;

            const launched = document.createElement('span');
            launched.style.cssText = 'font-weight:600;color:#4ade80;white-space:nowrap;';
            launched.textContent = `${records.length} lançamento${records.length !== 1 ? 's' : ''}`;
            countWrap.appendChild(launched);

            if (completed > 0) {
                const complete = document.createElement('span');
                complete.style.cssText = 'color:#60a5fa;white-space:nowrap;';
                complete.textContent = `${completed} completo${completed !== 1 ? 's' : ''}`;
                countWrap.appendChild(complete);
            }

            if (partial > 0) {
                const partialLabel = document.createElement('span');
                partialLabel.style.cssText = 'color:#fb923c;white-space:nowrap;';
                partialLabel.textContent = `${partial} parcial${partial !== 1 ? 'is' : ''}`;
                countWrap.appendChild(partialLabel);
            }
        } else {
            const emptyState = document.createElement('span');
            emptyState.style.cssText = 'color:#6b7280;white-space:nowrap;';
            emptyState.textContent = 'Sem lançamento';
            countWrap.appendChild(emptyState);
        }

        dayRow.appendChild(countWrap);
        td.appendChild(dayRow);

        td.onclick = () => selectDate(iso);

        week.appendChild(td);
        cell++;
        if (cell % 7 === 0) {
            tbody.appendChild(week);
            week = newWeekRow();
        }
    }

    while (cell % 7 !== 0) {
        week.appendChild(emptyCell());
        cell++;
    }

    if (week.children.length) {
        tbody.appendChild(week);
    }

    table.appendChild(tbody);
    wrap.innerHTML = '';

    const tableWrapper = document.createElement('div');
    tableWrapper.style.cssText = 'width:100%;display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;';
    tableWrapper.appendChild(table);
    wrap.appendChild(tableWrapper);

    if (window.lucide) lucide.createIcons();
}

function newWeekRow() {
    const tr = document.createElement('tr');
    tr.className = 'border-b border-white/5';
    return tr;
}

function emptyCell() {
    const td = document.createElement('td');
    td.className = 'border border-white/5';
    td.style.cssText = 'height:90px;background:rgba(255,255,255,0.005);';
    return td;
}

function selectDate(iso, scrollIntoView = true) {
    selectedDate = iso;
    renderCalendar();
    renderDayPanel(iso);
    renderDailyReport(currentRecordsMap[iso] || [], iso);

    updateManualDefaults(iso);

    if (scrollIntoView) {
        document.getElementById('day-detail-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderDayPanel(iso) {
    const container = document.getElementById('day-detail-panel');
    if (!container) return;

    const records = currentRecordsMap[iso] || [];
    const presentIds = new Set(records.map(record => record.user_id));
    const missingTechnicians = TECHNICIANS.filter(tech => !presentIds.has(tech.id));

    const weekday = new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long' });
    const [year, month, day] = iso.split('-');
    const dateLabel = `${day}/${month}/${year}`;
    const completeCount = records.filter(record => record.km_start !== null && record.km_end !== null).length;
    const partialCount = records.filter(record => record.km_start !== null && record.km_end === null).length;

    container.innerHTML = `
        <style>@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}</style>
        <div class="mt-1 bg-brand-dark border border-white/10 rounded-xl overflow-visible" style="animation:slideDown 0.2s ease;">
            <div class="flex items-center justify-between px-4 md:px-6 py-4 border-b border-white/10">
                <div>
                    <h3 class="text-white font-bold text-xl md:text-lg capitalize">${weekday}, ${dateLabel}</h3>
                    <div class="flex flex-wrap gap-3 mt-1 text-sm md:text-xs">
                        <span style="color:#4ade80;font-weight:500;">${records.length} lançamento${records.length !== 1 ? 's' : ''}</span>
                        <span style="color:#60a5fa;">${completeCount} completo${completeCount !== 1 ? 's' : ''}</span>
                        <span style="color:#fb923c;">${partialCount} parcial${partialCount !== 1 ? 'is' : ''}</span>
                        <span class="text-gray-400">${missingTechnicians.length} sem lançamento</span>
                    </div>
                </div>
                <button type="button" title="Recarregar dia" onclick="selectDate('${iso}', false)"
                    class="text-gray-400 hover:text-white transition p-2 rounded-lg hover:bg-white/5 flex-shrink-0 ml-2">
                    <i data-lucide="rotate-cw" class="w-5 h-5"></i>
                </button>
            </div>
    `;

    const body = document.createElement('div');
    body.className = 'divide-y divide-white/5';

    const presentSection = document.createElement('div');
    presentSection.style.cssText = 'padding:16px 24px;';
    presentSection.innerHTML = `<p style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4ade80;margin-bottom:10px;">Com lançamento (${records.length})</p>`;

    if (records.length > 0) {
        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;';
        records.forEach(record => {
            grid.appendChild(buildKmCard(record));
        });
        presentSection.appendChild(grid);
    } else {
        presentSection.innerHTML += '<p class="text-gray-500 text-sm">Nenhum técnico lançou km nesta data.</p>';
    }
    body.appendChild(presentSection);

    const missingSection = document.createElement('div');
    missingSection.style.cssText = 'padding:16px 24px;';
    missingSection.innerHTML = `<p style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:10px;">Sem lançamento (${missingTechnicians.length})</p>`;

    if (missingTechnicians.length > 0) {
        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;';
        missingTechnicians.forEach(tech => {
            const card = document.createElement('div');
            card.style.cssText = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:10px;';

            const avatar = document.createElement('div');
            avatar.style.cssText = 'width:32px;height:32px;border-radius:50%;background:rgba(239,68,68,0.16);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;font-weight:700;color:#fecaca;';
            avatar.textContent = capitalize((tech.full_name || tech.username || '-').charAt(0));

            const info = document.createElement('div');
            info.style.cssText = 'min-width:0;flex:1;';
            info.innerHTML = `<p style="color:#f9fafb;font-size:14px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(tech.full_name || tech.username || '-')}</p><p style="color:#9ca3af;font-size:11px;margin:2px 0 0 0;">Sem registro</p>`;

            card.appendChild(avatar);
            card.appendChild(info);
            grid.appendChild(card);
        });
        missingSection.appendChild(grid);
    } else {
        missingSection.innerHTML += '<p class="text-gray-500 text-sm">Todos os técnicos cadastrados enviaram km nesta data.</p>';
    }

    body.appendChild(missingSection);
    container.innerHTML += '';
    container.appendChild(body);

    if (window.lucide) lucide.createIcons();
}

function buildKmCard(record) {
    const card = document.createElement('div');
    card.style.cssText = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:10px;';

    const avatar = document.createElement('div');
    avatar.style.cssText = 'width:32px;height:32px;border-radius:50%;background:rgba(34,197,94,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;font-weight:700;color:#dcfce7;';
    avatar.textContent = capitalize((record.full_name || record.username || '-').charAt(0));

    const info = document.createElement('div');
    info.style.cssText = 'min-width:0;flex:1;';

    const [year, month, day] = record.log_date.split('-');
    const dateLabel = `${day}/${month}/${year}`;
    const status = buildStatusBadge(record);
    const kmLabel = record.km_driven !== null
        ? `${record.km_driven.toLocaleString('pt-BR')} km`
        : 'Sem total';

    info.innerHTML = `
        <p style="color:#f9fafb;font-size:14px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(record.full_name || record.username || '-')}</p>
        <p style="color:#9ca3af;font-size:11px;margin:2px 0 0 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span>${dateLabel}</span>
            <span>${kmLabel}</span>
        </p>
    `;

    card.appendChild(avatar);
    card.appendChild(info);
    card.insertAdjacentHTML('beforeend', `<div class="flex-shrink-0">${status}</div>`);
    return card;
}

function buildStatusBadge(record) {
    if (record.km_start !== null && record.km_end !== null) {
        return '<span class="badge badge-done"><i data-lucide="check" style="width:11px;height:11px;"></i> Completo</span>';
    }

    if (record.km_start !== null) {
        return '<span class="badge badge-partial"><i data-lucide="clock" style="width:11px;height:11px;"></i> Só inicial</span>';
    }

    return '<span class="badge badge-missing"><i data-lucide="x" style="width:11px;height:11px;"></i> Incompleto</span>';
}

function renderSummary(summary) {
    const container = document.getElementById('summary-cards');
    if (!container) return;

    if (!summary || summary.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm col-span-full">Sem dados para o período selecionado.</p>';
        return;
    }

    container.innerHTML = summary.map(s => `
        <div class="summary-card">
            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1 truncate">${esc(s.full_name)}</p>
            <p class="text-white text-2xl font-bold">${Number(s.total_driven || 0).toLocaleString('pt-BR')} <span class="text-gray-400 text-sm font-normal">km</span></p>
            <p class="text-gray-500 text-xs mt-1">${s.days_complete} dia${s.days_complete !== 1 ? 's' : ''} completo${s.days_complete !== 1 ? 's' : ''}</p>
        </div>
    `).join('');
}

function openManualModal() {
    document.getElementById('manual-date').value = selectedDate || fmtDate(new Date());
    document.getElementById('manual-user').value = '';
    document.getElementById('manual-km-start').value = '';
    document.getElementById('manual-km-end').value = '';
    document.getElementById('manual-total').value = '—';
    document.getElementById('manual-modal').classList.add('open');
    lucide.createIcons();
}

function updateManualDefaults(iso) {
    const manualDate = document.getElementById('manual-date');
    if (manualDate) manualDate.value = iso;
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
        await loadReport(selectedDate);
    } catch (error) {
        showToast('Erro de conexao ao salvar cadastro manual.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Salvar cadastro';
        lucide.createIcons();
    }
}

function renderDailyReport(records, iso) {
    const loading = document.getElementById('day-table-loading');
    const empty = document.getElementById('day-table-empty');
    const wrap = document.getElementById('day-table-wrap');
    const label = document.getElementById('daily-report-label');
    const tbody = document.getElementById('day-table-body');

    if (loading) loading.classList.add('hidden');

    if (!records || records.length === 0) {
        if (label) label.textContent = `${formatDateLabel(iso)} - 0 registros`;
        if (tbody) tbody.innerHTML = '';
        if (wrap) wrap.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
        return;
    }

    if (label) label.textContent = `${formatDateLabel(iso)} - ${records.length} registro${records.length !== 1 ? 's' : ''}`;
    if (tbody) tbody.innerHTML = buildDailyRows(records);
    if (empty) empty.classList.add('hidden');
    if (wrap) wrap.classList.remove('hidden');
    lucide.createIcons();
}

function buildDailyRows(records) {
    return records.map(r => {
        const [y, m, d] = r.log_date.split('-');
        const dateLabel = `${d}/${m}/${y}`;

        const fmtTime = (ts) => {
            if (!ts) return null;
            return new Date(ts.replace(' ', 'T'));
        };
        const tsStart = fmtTime(r.saved_at_start);
        const tsEnd   = fmtTime(r.saved_at_end);

        const fmtHM = (dateObj) => dateObj
            ? dateObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : '—';

        const timeStart = fmtHM(tsStart);
        const timeEnd   = fmtHM(tsEnd);
        const timeRange = `${timeStart} / ${timeEnd}`;

        let badge;
        if (r.km_start !== null && r.km_end !== null)
            badge = '<span class="badge badge-done"><i data-lucide="check" style="width:11px;height:11px;"></i> Completo</span>';
        else if (r.km_start !== null)
            badge = '<span class="badge badge-partial"><i data-lucide="clock" style="width:11px;height:11px;"></i> Só inicial</span>';
        else
            badge = '<span class="badge badge-missing"><i data-lucide="x" style="width:11px;height:11px;"></i> Incompleto</span>';

        const photoStart = r.photo_start_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_start_url)}','${esc(r.full_name)}','KM Inicial - ${dateLabel}')"><i data-lucide="image" style="width:12px;height:12px;"></i> Inicial</button>`
            : '';

        const photoEnd = r.photo_end_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_end_url)}','${esc(r.full_name)}','KM Final - ${dateLabel}')"><i data-lucide="image" style="width:12px;height:12px;"></i> Final</button>`
            : '';

        const evidencias = (photoStart || photoEnd)
            ? `<div class="flex flex-wrap items-center justify-center gap-1">${photoStart}${photoEnd}</div>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const kmStart  = r.km_start  !== null ? r.km_start.toLocaleString('pt-BR')  : '<span class="text-gray-600">—</span>';
        const kmEnd    = r.km_end    !== null ? r.km_end.toLocaleString('pt-BR')    : '<span class="text-gray-600">—</span>';
        const kmDriven = r.km_driven !== null
            ? `<span class="text-white font-semibold">${r.km_driven.toLocaleString('pt-BR')} km</span>`
            : '<span class="text-gray-600">—</span>';

        const locationStart = r.location_start_url
            ? `<a href="${esc(r.location_start_url)}" target="_blank" rel="noopener noreferrer" class="km-table--daily-link">Inicial</a>`
            : '';
        const locationEnd = r.location_end_url
            ? `<a href="${esc(r.location_end_url)}" target="_blank" rel="noopener noreferrer" class="km-table--daily-link">Final</a>`
            : '';

        const localizacao = (locationStart || locationEnd)
            ? `<div class="flex flex-wrap items-center justify-center gap-1">${locationStart}${locationEnd}</div>`
            : '<span class="text-gray-600 text-xs">—</span>';

        return `
        <tr>
            <td><p class="text-white font-medium">${dateLabel}</p></td>
            <td class="text-white">${esc(r.full_name)}</td>
            <td class="text-right font-mono">${kmStart}</td>
            <td class="text-right font-mono">${kmEnd}</td>
            <td class="text-right">${kmDriven}</td>
            <td class="text-center text-xs text-gray-300">${timeRange}</td>
            <td class="text-center">${localizacao}</td>
            <td class="text-center">${badge}</td>
            <td class="text-center">${evidencias}</td>
        </tr>`;
    }).join('');
}

function buildRecordsRows(records) {
    return records.map(r => {
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
}

// ── Tabela mensal ─────────────────────────────────────────────────────────────
function renderMonthlyReport(records) {
    showLoading(false);
    const selectedLabel = document.getElementById('monthly-report-label');

    if (!records || records.length === 0) {
        if (selectedLabel) {
            selectedLabel.textContent = '0 registros no mês';
        }
        showEmpty(true);
        document.getElementById('table-body').innerHTML = '';
        return;
    }

    showEmpty(false);
    document.getElementById('table-wrap').classList.remove('hidden');

    if (selectedLabel) {
        selectedLabel.textContent = `${records.length} registro${records.length !== 1 ? 's' : ''} no mês`;
    }

    const tbody = document.getElementById('table-body');
    tbody.innerHTML = buildRecordsRows(records);

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

function formatDateLabel(iso) {
    const [year, month, day] = iso.split('-');
    return `${day}/${month}/${year}`;
}

function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

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
