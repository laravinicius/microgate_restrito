<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Somente gerente / admin
requireAdmin();

// Lista de técnicos para o filtro (is_admin = 0)
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

        #header-placeholder nav { top: 0 !important; }

        /* ── Filtros ── */
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
        .filter-input:focus {
            outline: none;
            border-color: rgba(167,139,250,0.5);
        }
        .filter-input option { background: #1a1a2e; color: white; }

        /* ── Cards de resumo ── */
        .summary-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
        }

        /* ── Tabela ── */
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

        /* ── Badges ── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600; white-space: nowrap;
        }
        .badge-done    { background: rgba(74,222,128,0.12);  color: #4ade80; }
        .badge-partial { background: rgba(251,191,36,0.12);  color: #fbbf24; }
        .badge-missing { background: rgba(239,68,68,0.12);   color: #f87171; }

        /* ── Botão de foto ── */
        .btn-photo {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 500;
            background: rgba(167,139,250,0.1);
            color: #a78bfa;
            border: 1px solid rgba(167,139,250,0.2);
            cursor: pointer; transition: background 0.2s;
        }
        .btn-photo:hover { background: rgba(167,139,250,0.2); }

        /* ── Modal de foto ── */
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
            border-radius: 16px;
            overflow: hidden;
            max-width: 600px; width: 100%;
        }
        #photo-modal img {
            width: 100%; display: block;
            max-height: 70vh; object-fit: contain;
            background: #000;
        }
        #photo-modal .modal-footer {
            padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        /* ── Skeleton ── */
        .skeleton {
            background: linear-gradient(90deg,rgba(255,255,255,0.04) 25%,rgba(255,255,255,0.08) 50%,rgba(255,255,255,0.04) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 4px; height: 14px;
        }
        @keyframes shimmer { to { background-position: -200% 0; } }

        /* ── Btn primário ── */
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
    </style>
</head>

<body>
<div class="boxed-layout">
<div class="content-wrapper min-h-screen flex flex-col">
<div id="header-placeholder"></div>

<main class="flex-1 pt-32 md:pt-52 pb-20">
<div class="max-w-7xl mx-auto px-4">

    <!-- Cabeçalho -->
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Quilometragem</h1>
            <p class="text-gray-400">Acompanhe o KM rodado por cada técnico</p>
        </div>
        <a href="restricted.php" class="btn-secondary w-full md:w-auto justify-center">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Painel principal
        </a>
    </div>

    <!-- ── Filtros ── -->
    <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Técnico</label>
                <select id="filter-user" class="filter-input">
                    <option value="0">Todos os técnicos</option>
                    <?php foreach ($technicians as $tech): ?>
                    <option value="<?= (int)$tech['id'] ?>">
                        <?= htmlspecialchars($tech['full_name'] ?: $tech['username']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Data inicial</label>
                <input type="date" id="filter-from" class="filter-input">
            </div>
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Data final</label>
                <input type="date" id="filter-to" class="filter-input">
            </div>
            <div class="flex items-end">
                <button class="btn-primary w-full justify-center" onclick="loadReport()">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Buscar
                </button>
            </div>
        </div>
        <!-- Atalhos de período -->
        <div class="flex flex-wrap gap-2">
            <button class="badge badge-done cursor-pointer" onclick="setRange(0)">Hoje</button>
            <button class="badge badge-partial cursor-pointer" onclick="setRange(7)">Últimos 7 dias</button>
            <button class="badge badge-missing cursor-pointer" onclick="setRange(30)">Últimos 30 dias</button>
            <button class="text-gray-400 text-xs underline cursor-pointer" onclick="setRange('month')">Mês atual</button>
        </div>
    </div>

    <!-- ── Cards de resumo por técnico ── -->
    <div id="summary-cards" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8"></div>

    <!-- ── Tabela de registros ── -->
    <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
        <div class="p-6 border-b border-white/10 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="list" class="w-5 h-5"></i>
                Registros
            </h2>
            <span id="total-badge" class="text-gray-400 text-sm"></span>
        </div>

        <!-- Estado de carregamento -->
        <div id="table-loading" class="p-6 space-y-3">
            <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="skeleton w-full" style="height:18px;"></div>
            <?php endfor; ?>
        </div>

        <!-- Estado vazio -->
        <div id="table-empty" class="hidden p-16 text-center text-gray-500">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p>Nenhum registro encontrado para o período selecionado.</p>
        </div>

        <!-- Tabela real -->
        <div id="table-wrap" class="hidden overflow-x-auto">
            <table class="km-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Técnico</th>
                        <th class="text-right">KM Inicial</th>
                        <th class="text-right">KM Final</th>
                        <th class="text-right">Total rodado</th>
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

<!-- ── Modal de foto ── -->
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
// ── Inicialização com período padrão: últimos 7 dias ─────────────────────────
(function init() {
    const today = new Date();
    const from  = new Date(today);
    from.setDate(from.getDate() - 7);

    document.getElementById('filter-to').value   = fmtDate(today);
    document.getElementById('filter-from').value = fmtDate(from);

    loadReport();
})();

function fmtDate(d) {
    return d.toISOString().slice(0, 10);
}

function setRange(days) {
    const today = new Date();
    document.getElementById('filter-to').value = fmtDate(today);

    if (days === 'month') {
        const from = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('filter-from').value = fmtDate(from);
    } else {
        const from = new Date(today);
        from.setDate(from.getDate() - (days === 0 ? 0 : days - 1));
        document.getElementById('filter-from').value = fmtDate(from);
    }
    loadReport();
}

// ── Carrega dados da API ─────────────────────────────────────────────────────
async function loadReport() {
    const userId   = document.getElementById('filter-user').value;
    const dateFrom = document.getElementById('filter-from').value;
    const dateTo   = document.getElementById('filter-to').value;

    showLoading(true);

    const params = new URLSearchParams({ user_id: userId, date_from: dateFrom, date_to: dateTo });

    try {
        const res  = await fetch(`get_km_report.php?${params}`);
        const data = await res.json();

        if (!data.success) {
            showLoading(false);
            showEmpty(true);
            return;
        }

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

// ── Cards de resumo ──────────────────────────────────────────────────────────
function renderSummary(summary) {
    const container = document.getElementById('summary-cards');

    if (!summary || summary.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = summary.map(s => `
        <div class="summary-card">
            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1 truncate">${esc(s.full_name)}</p>
            <p class="text-white text-2xl font-bold">${s.total_driven.toLocaleString('pt-BR')} <span class="text-gray-400 text-sm font-normal">km</span></p>
            <p class="text-gray-500 text-xs mt-1">${s.days_complete} dia${s.days_complete !== 1 ? 's' : ''} completo${s.days_complete !== 1 ? 's' : ''}</p>
        </div>
    `).join('');
}

// ── Tabela de registros ───────────────────────────────────────────────────────
function renderTable(records) {
    showLoading(false);

    if (!records || records.length === 0) {
        showEmpty(true);
        return;
    }

    showEmpty(false);
    document.getElementById('table-wrap').classList.remove('hidden');

    const tbody = document.getElementById('table-body');

    tbody.innerHTML = records.map(r => {
        // Formata a data: YYYY-MM-DD → DD/MM/YYYY
        const [y, m, d] = r.log_date.split('-');
        const dateLabel = `${d}/${m}/${y}`;
        const weekday   = new Date(r.log_date + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short' });

        // Status badge
        let badge;
        if (r.km_start !== null && r.km_end !== null) {
            badge = '<span class="badge badge-done"><i data-lucide="check" style="width:11px;height:11px;"></i> Completo</span>';
        } else if (r.km_start !== null) {
            badge = '<span class="badge badge-partial"><i data-lucide="clock" style="width:11px;height:11px;"></i> Só inicial</span>';
        } else {
            badge = '<span class="badge badge-missing"><i data-lucide="x" style="width:11px;height:11px;"></i> Incompleto</span>';
        }

        // Botões de foto
        const photoStart = r.photo_start_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_start_url)}','${esc(r.full_name)}','KM Inicial - ${dateLabel}')">
                <i data-lucide="image" style="width:12px;height:12px;"></i> Inicial
               </button>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const photoEnd = r.photo_end_url
            ? `<button class="btn-photo" onclick="openPhoto('${esc(r.photo_end_url)}','${esc(r.full_name)}','KM Final - ${dateLabel}')">
                <i data-lucide="image" style="width:12px;height:12px;"></i> Final
               </button>`
            : '<span class="text-gray-600 text-xs">—</span>';

        const kmStart   = r.km_start  !== null ? r.km_start.toLocaleString('pt-BR')  : '<span class="text-gray-600">—</span>';
        const kmEnd     = r.km_end    !== null ? r.km_end.toLocaleString('pt-BR')    : '<span class="text-gray-600">—</span>';
        const kmDriven  = r.km_driven !== null
            ? `<span class="text-white font-semibold">${r.km_driven.toLocaleString('pt-BR')} km</span>`
            : '<span class="text-gray-600">—</span>';

        return `
        <tr>
            <td>
                <p class="text-white font-medium">${dateLabel}</p>
                <p class="text-gray-500 text-xs">${weekday}</p>
            </td>
            <td class="text-white">${esc(r.full_name)}</td>
            <td class="text-right font-mono text-sm">${kmStart}</td>
            <td class="text-right font-mono text-sm">${kmEnd}</td>
            <td class="text-right">${kmDriven}</td>
            <td class="text-center">${badge}</td>
            <td>
                <div class="flex items-center justify-center gap-2">
                    ${photoStart}
                    ${photoEnd}
                </div>
            </td>
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

// ── Modal de foto ─────────────────────────────────────────────────────────────
function openPhoto(url, name, label) {
    document.getElementById('modal-img').src       = url;
    document.getElementById('modal-title').textContent    = name;
    document.getElementById('modal-subtitle').textContent = label;
    document.getElementById('photo-modal').classList.add('open');
    lucide.createIcons();
}

function closeModal(e) {
    // Fecha apenas se clicar no backdrop
    if (e.target.id === 'photo-modal') {
        e.target.classList.remove('open');
    }
}

// ── Escape para XSS ──────────────────────────────────────────────────────────
function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

lucide.createIcons();
</script>
</body>
</html>
