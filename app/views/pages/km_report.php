<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap.php';

requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$techStmt = $pdo->query(
    "SELECT id, full_name, username
     FROM users
     WHERE is_admin = 0 AND is_active = 1 AND allow_fuel = 1
     ORDER BY full_name ASC"
);
$technicians = $techStmt->fetchAll();

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selectedTechnician = null;

if ($selectedUserId > 0) {
    foreach ($technicians as $tech) {
        if ((int)$tech['id'] === $selectedUserId) {
            $selectedTechnician = [
                'id' => (int)$tech['id'],
                'full_name' => (string)($tech['full_name'] ?: $tech['username']),
                'username' => (string)$tech['username'],
            ];
            break;
        }
    }
}

$isDetailMode = $selectedTechnician !== null;
$invalidSelection = $selectedUserId > 0 && !$isDetailMode;
?>
<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatorio KM e Abastecimento | Microgate Informatica</title>
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
                <div class="max-w-site mx-auto px-4">
                    <?php
                        $pageTitle = 'Quilometragem e Abastecimento';
                        $pageSubtitle = $isDetailMode
                            ? 'Relatorio mensal do tecnico selecionado'
                            : 'Selecione um tecnico para visualizar o relatorio mensal';
                        $backUrl = 'restricted.php';
                        require APP_ROOT . '/components/page_header.php';
                    ?>

                    <?php if (!$isDetailMode): ?>
                        <?php if ($invalidSelection): ?>
                            <div class="mb-4 rounded-lg border border-yellow-400/30 bg-yellow-400/10 px-4 py-3 text-sm text-yellow-200">
                                Tecnico nao encontrado ou sem permissao de abastecimento.
                            </div>
                        <?php endif; ?>

                        <div class="bg-brand-dark border border-white/10 rounded-xl p-6 mb-8">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="users" class="w-5 h-5 text-blue-400"></i>
                                <h2 class="text-xl font-bold text-white">Tecnicos com abastecimento habilitado</h2>
                            </div>
                            <p class="text-gray-400 text-sm">Clique em um tecnico para abrir o relatorio mensal por dia.</p>
                        </div>

                        <?php if (empty($technicians)): ?>
                            <div class="bg-brand-dark border border-white/10 rounded-xl p-8 text-center text-gray-400">
                                Nenhum tecnico com permissao de abastecimento encontrado.
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($technicians as $tech): ?>
                                    <?php
                                        $techName = (string)($tech['full_name'] ?: $tech['username']);
                                        $techId = (int)$tech['id'];
                                        $detailUrl = route_url('km_report.php') . '?user_id=' . $techId;
                                    ?>
                                    <a href="<?= htmlspecialchars($detailUrl) ?>" class="bg-brand-dark border border-white/10 rounded-xl p-5 transition group hover:border-white/20">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="text-white font-semibold truncate"><?= htmlspecialchars($techName) ?></p>
                                                <p class="text-gray-400 text-sm truncate">@<?= htmlspecialchars((string)$tech['username']) ?></p>
                                            </div>
                                            <i data-lucide="arrow-right" class="w-5 h-5 text-gray-400 group-hover:translate-x-1 transition"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <a href="<?= htmlspecialchars(route_url('km_report.php')) ?>" class="btn-secondary justify-center">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Voltar para lista de tecnicos
                            </a>
                            <div class="text-left md:text-right">
                                <p class="text-white font-semibold"><?= htmlspecialchars($selectedTechnician['full_name']) ?></p>
                                <p class="text-gray-400 text-sm">@<?= htmlspecialchars($selectedTechnician['username']) ?></p>
                            </div>
                        </div>

                        <div class="bg-brand-dark border border-white/10 rounded-xl overflow-hidden">
                            <div class="px-4 md:px-6 pt-4 pb-3 border-b border-white/10">
                                <div id="month-tab-wrap" class="flex gap-2 overflow-x-auto flex-wrap"></div>
                            </div>

                            <div class="p-4 md:p-6">
                                <div id="report-loading" class="space-y-3">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <div class="skeleton h-[18px] w-full"></div>
                                    <?php endfor; ?>
                                </div>

                                <div id="report-empty" class="hidden p-10 text-center text-gray-500">
                                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                                    <p>Nenhum dado para o periodo selecionado.</p>
                                </div>

                                <div id="report-wrap" class="hidden overflow-x-auto">
                                    <table class="km-table">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th class="text-right">KM inicial</th>
                                                <th class="text-right">KM final</th>
                                                <th class="text-right">KM rodado</th>
                                                <th class="text-right">KM rodado por fora</th>
                                                <th class="text-right">Diferenca (KM interno)</th>
                                                <th class="text-center">Abastecimento</th>
                                                <th class="text-right">Valor gasolina</th>
                                                <th class="text-right">Litros abastecidos</th>
                                                <th class="text-right">Valor total</th>
                                                <th class="text-right">Valor a pagar</th>
                                                <th class="text-right">Media do carro</th>
                                            </tr>
                                        </thead>
                                        <tbody id="report-body"></tbody>
                                        <tfoot id="report-foot"></tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php if ($isDetailMode): ?>
    <script>
    const REPORT_USER_ID = <?= (int)$selectedTechnician['id'] ?>;
    const today = new Date();
    const reportYear = today.getFullYear();
    let currentYear = today.getFullYear();
    let currentMonth = today.getMonth();

    (function init() {
        buildMonthTabs();
        loadReport();
    })();

    function buildMonthTabs() {
        const wrap = document.getElementById('month-tab-wrap');
        if (!wrap) return;
        wrap.innerHTML = '';

        for (let month = 0; month < 12; month++) {
            const date = new Date(reportYear, month, 1);
            const year = reportYear;
            const active = month === today.getMonth();

            const button = document.createElement('button');
            button.type = 'button';
            button.className = active
                ? 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm md:text-xs transition'
                : 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm md:text-xs transition';
            button.textContent = capitalize(date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }));
            button.onclick = () => selectMonth(button, year, month);
            wrap.appendChild(button);
        }
    }

    async function selectMonth(button, year, month) {
        document.getElementById('month-tab-wrap')?.querySelectorAll('button').forEach(tab => {
            tab.className = 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm md:text-xs transition';
        });
        button.className = 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm md:text-xs transition';

        currentYear = year;
        currentMonth = month;
        await loadReport();
    }

    async function loadReport() {
        showLoading(true);

        const periodStart = new Date(currentYear, currentMonth, 1);
        const periodEnd = new Date(currentYear, currentMonth + 1, 0);
        const params = new URLSearchParams({
            user_id: String(REPORT_USER_ID),
            date_from: fmtDate(periodStart),
            date_to: fmtDate(periodEnd),
        });

        try {
            const res = await fetch(`${window.APP_ROUTES?.getKmFuelReport || '/app/actions/km/get_km_fuel_report.php'}?${params}`);
            const data = await res.json();

            if (!data.success) {
                showLoading(false);
                showEmpty(true);
                return;
            }

            renderReport(data.records || [], data.totals || {});
        } catch (error) {
            console.error(error);
            showLoading(false);
            showEmpty(true);
        }
    }

    function renderReport(records, totals) {
        const body = document.getElementById('report-body');
        const foot = document.getElementById('report-foot');
        if (!body || !foot) return;

        body.innerHTML = '';

        for (const record of records) {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${fmtDateBr(record.date)}</td>
                <td class="text-right">${record.km_start !== null ? formatInteger(record.km_start) : '-'}</td>
                <td class="text-right">${record.km_end !== null ? formatInteger(record.km_end) : '-'}</td>
                <td class="text-right">${record.km_driven !== null ? formatInteger(record.km_driven) : '-'}</td>
                <td class="text-right">${record.km_outside_shift !== null ? formatInteger(record.km_outside_shift) : '-'}</td>
                <td class="text-right">${record.km_inside_shift !== null ? formatInteger(record.km_inside_shift) : '-'}</td>
                <td class="text-center">${record.had_fuel ? '<span class="badge badge-done">Sim</span>' : '<span class="badge badge-partial">Nao</span>'}</td>
                <td class="text-right">${record.fuel_price !== null ? formatMoney(record.fuel_price) : '-'}</td>
                <td class="text-right">${record.liters !== null ? formatLiters(record.liters) : '-'}</td>
                <td class="text-right">${record.total_amount !== null ? formatMoney(record.total_amount) : '-'}</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
            `;

            body.appendChild(tr);
        }

        foot.innerHTML = `
            <tr class="bg-white/5 font-semibold text-white">
                <td>Totais do mes</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">${formatInteger(totals.total_km_driven || 0)}</td>
                <td class="text-right">${formatInteger(totals.total_km_outside_shift || 0)}</td>
                <td class="text-right">${formatInteger(totals.total_km_inside_shift || 0)}</td>
                <td class="text-center">${Number(totals.fuel_days || 0)} dia(s)</td>
                <td class="text-right">${totals.average_fuel_price !== null && totals.average_fuel_price !== undefined ? formatMoney(totals.average_fuel_price) : '-'}</td>
                <td class="text-right">${totals.total_liters !== null && totals.total_liters !== undefined ? formatLiters(totals.total_liters) : '-'}</td>
                <td class="text-right">${totals.total_amount !== null && totals.total_amount !== undefined ? formatMoney(totals.total_amount) : '-'}</td>
                <td class="text-right">${totals.payment_amount !== null && totals.payment_amount !== undefined ? formatMoney(totals.payment_amount) : '-'}</td>
                <td class="text-right">${totals.overall_kml !== null && totals.overall_kml !== undefined ? `${Number(totals.overall_kml).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} km/L` : '-'}</td>
            </tr>
        `;

        showLoading(false);
        showEmpty(records.length === 0);
    }

    function showLoading(isLoading) {
        document.getElementById('report-loading')?.classList.toggle('hidden', !isLoading);
        document.getElementById('report-wrap')?.classList.toggle('hidden', isLoading);
        document.getElementById('report-empty')?.classList.add('hidden');
    }

    function showEmpty(show) {
        document.getElementById('report-empty')?.classList.toggle('hidden', !show);
        if (show) {
            document.getElementById('report-wrap')?.classList.add('hidden');
        } else {
            document.getElementById('report-wrap')?.classList.remove('hidden');
        }
    }

    function formatInteger(value) {
        return Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function formatMoney(value) {
        return Number(value).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatLiters(value) {
        return Number(value).toLocaleString('pt-BR', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3,
        });
    }

    function fmtDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function fmtDateBr(iso) {
        const [y, m, d] = String(iso).split('-');
        return `${d}/${m}/${y}`;
    }

    function capitalize(text) {
        if (!text) return '';
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    lucide.createIcons();
    </script>
    <?php else: ?>
    <script>lucide.createIcons();</script>
    <?php endif; ?>
</body>

</html>
