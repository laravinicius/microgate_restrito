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
                        <p class="text-gray-400 text-sm md:text-xs mt-1">Clique em um dia para ver o status dos lançamentos.</p>
                    </div>
                    <button class="btn-secondary justify-center self-start md:self-auto" onclick="openManualModal()">
                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                        Cadastro manual
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs md:text-[11px] text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--green"></span><span class="legend-label--green">Com lançamento</span></span>
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--blue"></span><span class="legend-label--blue">Sem lançamento</span></span>
                    <span class="flex items-center gap-1.5"><span class="legend-swatch legend-swatch--orange"></span><span class="legend-label--orange">Parcial</span></span>
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
                        <div class="skeleton h-[18px] w-full"></div>
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
                <div class="skeleton h-[18px] w-full"></div>
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

    const container = document.createElement('div');
    container.className = 'w-full bg-brand-dark border border-white/10 rounded-xl overflow-hidden';

    const table = document.createElement('table');
    table.className = 'w-full min-w-[720px] border-collapse';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    headerRow.className = 'border-b border-white/10';
    ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].forEach((label, index) => {
        const th = document.createElement('th');
        th.className = (index >= 5)
            ? 'px-1 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.05em] text-gray-500'
            : 'px-1 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.05em] text-gray-400';
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
        td.className = isWeekend
            ? 'h-[100px] border border-white/5 bg-white/[0.015] p-2 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5'
            : 'h-[100px] border border-white/5 p-2 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5';
        if (selected) td.classList.add('ring-2', 'ring-white/30');
        td.setAttribute('data-date', iso);

        const dayRow = document.createElement('div');
        dayRow.className = 'mb-[5px] flex items-start justify-between gap-2';

        const numSpan = document.createElement('span');
        if (isToday) {
            numSpan.className = 'inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-black';
        } else {
            numSpan.className = isWeekend
                ? 'text-sm font-bold text-gray-500'
                : 'text-sm font-bold text-gray-200';
        }
        numSpan.textContent = day;
        dayRow.appendChild(numSpan);

        const countWrap = document.createElement('div');
        countWrap.className = 'flex flex-col items-end gap-px text-right';

        if (records.length > 0) {
            const completed = records.filter(record => record.km_start !== null && record.km_end !== null).length;
            const partial = records.filter(record => record.km_start !== null && record.km_end === null).length;

            const launched = document.createElement('span');
            launched.className = 'whitespace-nowrap font-semibold text-green-400';
            launched.textContent = `${records.length} lançamento${records.length !== 1 ? 's' : ''}`;
            countWrap.appendChild(launched);

            if (completed > 0) {
                const complete = document.createElement('span');
                complete.className = 'whitespace-nowrap text-blue-400';
                complete.textContent = `${completed} completo${completed !== 1 ? 's' : ''}`;
                countWrap.appendChild(complete);
            }

            if (partial > 0) {
                const partialLabel = document.createElement('span');
                partialLabel.className = 'whitespace-nowrap text-orange-400';
                partialLabel.textContent = `${partial} parcial${partial !== 1 ? 'is' : ''}`;
                countWrap.appendChild(partialLabel);
            }
        } else {
            const emptyState = document.createElement('span');
            emptyState.className = 'whitespace-nowrap text-gray-500';
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
    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'block w-full overflow-x-auto';
    tableWrapper.appendChild(table);
    container.appendChild(tableWrapper);

    wrap.innerHTML = '';
    wrap.appendChild(container);

    if (window.lucide) lucide.createIcons();
}

function newWeekRow() {
    const tr = document.createElement('tr');
    tr.className = 'border-b border-white/5';
    return tr;
}

function emptyCell() {
    const td = document.createElement('td');
    td.className = 'h-[90px] border border-white/5 bg-white/[0.005]';
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
        <div class="mt-1 bg-brand-dark border border-white/10 rounded-xl overflow-visible animate-slide-down">
            <div class="flex items-center justify-between px-4 md:px-6 py-4 border-b border-white/10">
                <div>
                    <h3 class="text-white font-bold text-xl md:text-lg capitalize">${weekday}, ${dateLabel}</h3>
                    <div class="flex flex-wrap gap-3 mt-1 text-sm md:text-xs">
                        <span class="font-medium text-green-400">${records.length} lançamento${records.length !== 1 ? 's' : ''}</span>
                        <span class="text-blue-400">${completeCount} completo${completeCount !== 1 ? 's' : ''}</span>
                        <span class="text-orange-400">${partialCount} parcial${partialCount !== 1 ? 'is' : ''}</span>
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
    presentSection.className = 'px-6 py-4';
    presentSection.innerHTML = `<p class="mb-2.5 text-xs font-bold uppercase tracking-[0.08em] text-green-400">Com lançamento (${records.length})</p>`;

    if (records.length > 0) {
        const grid = document.createElement('div');
        grid.className = 'grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(220px,1fr))]';
        records.forEach(record => {
            grid.appendChild(buildKmCard(record));
        });
        presentSection.appendChild(grid);
    } else {
        presentSection.innerHTML += '<p class="text-gray-500 text-sm">Nenhum técnico lançou km nesta data.</p>';
    }
    body.appendChild(presentSection);

    const missingSection = document.createElement('div');
    missingSection.className = 'px-6 py-4';
    missingSection.innerHTML = `<p class="mb-2.5 text-xs font-bold uppercase tracking-[0.08em] text-gray-400">Sem lançamento (${missingTechnicians.length})</p>`;

    if (missingTechnicians.length > 0) {
        const grid = document.createElement('div');
        grid.className = 'grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(200px,1fr))]';
        missingTechnicians.forEach(tech => {
            const card = document.createElement('div');
            card.className = 'flex items-center gap-2.5 rounded-lg border border-white/10 bg-white/5 px-3 py-2.5';

            const avatar = document.createElement('div');
            avatar.className = 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-red-500/20 text-[13px] font-bold text-red-200';
            avatar.textContent = capitalize((tech.full_name || tech.username || '-').charAt(0));

            const info = document.createElement('div');
            info.className = 'min-w-0 flex-1';
            info.innerHTML = `<p class="m-0 overflow-hidden text-ellipsis whitespace-nowrap text-sm font-medium text-gray-50">${esc(tech.full_name || tech.username || '-')}</p><p class="m-0 mt-0.5 text-[11px] text-gray-400">Sem registro</p>`;

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
    card.className = 'flex items-center gap-2.5 rounded-lg border border-white/10 bg-white/5 px-3 py-2.5';

    const avatar = document.createElement('div');
    avatar.className = 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-green-500/20 text-[13px] font-bold text-green-100';
    avatar.textContent = capitalize((record.full_name || record.username || '-').charAt(0));

    const info = document.createElement('div');
    info.className = 'min-w-0 flex-1';

    const [year, month, day] = record.log_date.split('-');
    const dateLabel = `${day}/${month}/${year}`;
    const status = buildStatusBadge(record);
    const kmLabel = record.km_driven !== null
        ? `${record.km_driven.toLocaleString('pt-BR')} km`
        : 'Sem total';

    info.innerHTML = `
        <p class="m-0 overflow-hidden text-ellipsis whitespace-nowrap text-sm font-medium text-gray-50">${esc(record.full_name || record.username || '-')}</p>
        <p class="m-0 mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-gray-400">
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
        return '<span class="badge badge-done"><i data-lucide="check" class="h-3 w-3"></i> Completo</span>';
    }

    if (record.km_start !== null) {
        return '<span class="badge badge-partial"><i data-lucide="clock" class="h-3 w-3"></i> Só inicial</span>';
    }

    return '<span class="badge badge-missing"><i data-lucide="x" class="h-3 w-3"></i> Incompleto</span>';
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
            badge = '<span class="badge badge-done"><i data-lucide="check" class="h-3 w-3"></i> Completo</span>';
        else if (r.km_start !== null)
            badge = '<span class="badge badge-partial"><i data-lucide="clock" class="h-3 w-3"></i> Só inicial</span>';
        else
            badge = '<span class="badge badge-missing"><i data-lucide="x" class="h-3 w-3"></i> Incompleto</span>';

        const photoStart = r.photo_start_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_start_url)}','${esc(r.full_name)}','KM Inicial - ${dateLabel}')"><i data-lucide="image" class="h-3 w-3"></i> Inicial</button>`
            : '';

        const photoEnd = r.photo_end_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_end_url)}','${esc(r.full_name)}','KM Final - ${dateLabel}')"><i data-lucide="image" class="h-3 w-3"></i> Final</button>`
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
            badge = '<span class="badge badge-done"><i data-lucide="check" class="h-3 w-3"></i> Completo</span>';
        else if (r.km_start !== null)
            badge = '<span class="badge badge-partial"><i data-lucide="clock" class="h-3 w-3"></i> Só inicial</span>';
        else
            badge = '<span class="badge badge-missing"><i data-lucide="x" class="h-3 w-3"></i> Incompleto</span>';

        const photoStart = r.photo_start_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_start_url)}','${esc(r.full_name)}','KM Inicial - ${dateLabel}')"><i data-lucide="image" class="h-3 w-3"></i> Inicial</button>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const photoEnd = r.photo_end_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_end_url)}','${esc(r.full_name)}','KM Final - ${dateLabel}')"><i data-lucide="image" class="h-3 w-3"></i> Final</button>`
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
    toast.className = type === 'success'
        ? 'manual-toast manual-toast-success'
        : 'manual-toast manual-toast-error';

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
