// js/escala-admin.js
// Calendário administrativo: exibe nomes dos técnicos por dia e painel de detalhes ao clicar.

(async function () {
    const wrap = document.getElementById('calendar-wrap');
    if (!wrap) return;

    // ── Abas de meses ───────────────────────────────────────────────────────
    const months = [];
    const now = new Date();
    for (let i = 0; i < 6; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
        months.push({
            year: d.getFullYear(),
            month: d.getMonth(),
            label: capitalize(d.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }))
        });
    }

    // Usa container de abas já existente no DOM, ou cria um novo
    const tabsContainer = document.getElementById('month-tab-wrap') || (() => {
        const div = document.createElement('div');
        div.className = 'mb-4 flex gap-2 overflow-x-auto flex-wrap';
        wrap.parentElement.insertBefore(div, wrap);
        return div;
    })();

    months.forEach((m, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = idx === 0
            ? 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm transition'
            : 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm transition';
        btn.textContent = m.label;
        btn.setAttribute('data-month-idx', idx);
        btn.onclick = () => selectTab(btn, m.year, m.month);
        tabsContainer.appendChild(btn);
    });

    // ── Selecionar aba ──────────────────────────────────────────────────────
    async function selectTab(btn, year, month) {
        tabsContainer.querySelectorAll('button').forEach(b => {
            b.className = 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm transition';
        });
        btn.className = 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm transition';

        // Remove painel de detalhe se aberto
        document.getElementById('day-detail-panel')?.remove();

        wrap.innerHTML = '<div class="p-8 text-center text-gray-500 text-sm">Carregando...</div>';

        const start = new Date(year, month, 1);
        const end   = new Date(year, month + 1, 0);
        const fmt   = d => d.toISOString().slice(0, 10);

        try {
            const res = await fetch(`./get_schedule.php?start=${fmt(start)}&end=${fmt(end)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            const evsByDate     = {};
            const holidaysByDate = {};

            (data.data || []).forEach(ev => {
                evsByDate[ev.date] = evsByDate[ev.date] || [];
                evsByDate[ev.date].push(ev);
            });

            (data.holidays || []).forEach(h => {
                holidaysByDate[h.date] = h;
            });

            wrap.innerHTML = '';
            wrap.appendChild(renderMonth(start, evsByDate, holidaysByDate));

            if (window.lucide) lucide.createIcons();
        } catch (err) {
            wrap.innerHTML = `<div class="p-4 text-red-400">Erro ao carregar escala: ${err.message}</div>`;
        }
    }

    // ── Renderizar mês ──────────────────────────────────────────────────────
    function renderMonth(date, eventsMap, holidaysMap) {
        const el = document.createElement('div');
        el.className = 'w-full bg-brand-dark border border-white/10 rounded-xl overflow-hidden';

        const tableWrapper = document.createElement('div');
        tableWrapper.style.cssText = 'width:100%;display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;';

        const table = document.createElement('table');
        table.className = 'border-collapse w-full';
        table.style.minWidth = '720px';

        // Header dias da semana
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'border-b border-white/10';
        ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].forEach((d, i) => {
            const th = document.createElement('th');
            th.className = 'py-3 text-center text-xs font-semibold uppercase tracking-wider ' + (i >= 5 ? 'text-gray-500' : 'text-gray-400');
            th.textContent = d;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Body
        const tbody       = document.createElement('tbody');
        const firstDay    = (new Date(date.getFullYear(), date.getMonth(), 1).getDay() + 6) % 7;
        const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        const fmt         = d => d.toISOString().slice(0, 10);
        const todayIso    = fmt(new Date());

        let cell = 0;
        let week = newWeekRow();

        // Células vazias
        for (let i = 0; i < firstDay; i++) {
            week.appendChild(emptyCell());
            cell++;
        }

        // Dias
        for (let d = 1; d <= daysInMonth; d++) {
            const cur = new Date(date.getFullYear(), date.getMonth(), d);
            const iso = fmt(cur);
            const evs = eventsMap[iso] || [];
            const holiday = holidaysMap[iso] || null;
            const isToday = iso === todayIso;
            const isWeekend = (cur.getDay() === 0 || cur.getDay() === 6);

            const td = document.createElement('td');
            td.className = 'border border-white/5 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5';
            td.style.cssText = 'height:90px;padding:8px;vertical-align:top;'
                + (isWeekend ? 'background:rgba(255,255,255,0.015);' : '');
            td.setAttribute('data-date', iso);

            // ── Número do dia ─────────────────────────────────────────────
            const dayRow = document.createElement('div');
            dayRow.style.cssText = 'display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:5px;';

            const numSpan = document.createElement('span');
            if (isToday) {
                // Círculo branco com número em preto — tudo inline para garantir render
                numSpan.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;'
                    + 'width:22px;height:22px;border-radius:50%;'
                    + 'background:#ffffff;color:#000000;font-size:11px;font-weight:700;flex-shrink:0;';
            } else if (isWeekend) {
                numSpan.style.cssText = 'font-size:13px;font-weight:700;color:#6b7280;';
            } else {
                numSpan.style.cssText = 'font-size:13px;font-weight:700;color:#e5e7eb;';
            }
            numSpan.textContent = d;
            dayRow.appendChild(numSpan);

            // ── Contador de técnicos em AGENDA ────────────────────────────
            if (evs.length > 0) {
                const working = evs.filter(e => (e.shift || '').toUpperCase().includes('AGENDA')).length;
                const folga   = evs.filter(e => (e.shift || '').toUpperCase().includes('FOLGA')).length;

                const cntWrap = document.createElement('div');
                cntWrap.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;gap:1px;';

                if (working > 0) {
                    const w = document.createElement('span');
                    w.className = 'cal-count';
                    w.style.cssText = 'font-weight:600;color:#4ade80;white-space:nowrap;';
                    w.textContent = working + ' trabalhando';
                    cntWrap.appendChild(w);
                }
                if (folga > 0) {
                    const f = document.createElement('span');
                    f.className = 'cal-count';
                    f.style.cssText = 'color:#60a5fa;white-space:nowrap;';
                    f.textContent = folga + ' folga';
                    cntWrap.appendChild(f);
                }
                dayRow.appendChild(cntWrap);
            }

            td.appendChild(dayRow);

            // ── Feriado ───────────────────────────────────────────────────
            if (holiday) {
                const hl = document.createElement('div');
                hl.style.cssText = 'font-size:10px;font-weight:600;color:#f87171;'
                    + 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;';
                hl.textContent = '● ' + (holiday.name || 'Feriado');
                hl.title = holiday.name || 'Feriado';
                td.appendChild(hl);
            }

            // Click → painel de detalhes
            td.onclick = () => openDayPanel(iso, d, date.getMonth(), date.getFullYear(), eventsMap, holidaysMap);

            week.appendChild(td);
            cell++;

            if (cell % 7 === 0) {
                tbody.appendChild(week);
                week = newWeekRow();
            }
        }

        // Células de fechamento
        while (cell % 7 !== 0) {
            week.appendChild(emptyCell());
            cell++;
        }
        if (cell % 7 === 0 && week.children.length) {
            tbody.appendChild(week);
        }

        table.appendChild(tbody);
        tableWrapper.appendChild(table);
        el.appendChild(tableWrapper);
        return el;
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

    // ── Painel de detalhes do dia ────────────────────────────────────────────
    function openDayPanel(iso, day, month, year, eventsMap, holidaysMap) {
        // Remove painel anterior
        document.getElementById('day-detail-panel')?.remove();
        // Remove destaque anterior
        document.querySelectorAll('[data-date].ring-2').forEach(el => el.classList.remove('ring-2', 'ring-white/30'));

        // Destaca célula clicada
        document.querySelectorAll(`[data-date="${iso}"]`).forEach(el => el.classList.add('ring-2', 'ring-white/30'));

        const evs      = eventsMap[iso] || [];
        const holiday  = holidaysMap[iso] || null;
        const weekday  = new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long' });
        const [y, m, d] = iso.split('-');
        const dateLabel = `${d}/${m}/${y}`;

        // Agrupa técnicos por turno
        const groups = { AGENDA: [], FOLGA: [], 'FÉRIAS': [], OUTRO: [] };
        evs.forEach(ev => {
            const s = (ev.shift || '').toUpperCase();
            if (s.includes('AGENDA'))      groups['AGENDA'].push(ev);
            else if (s.includes('FOLGA'))  groups['FOLGA'].push(ev);
            else if (s.includes('FÉRIAS') || s.includes('FERIAS')) groups['FÉRIAS'].push(ev);
            else groups['OUTRO'].push(ev);
        });

        const panel = document.createElement('div');
        panel.id = 'day-detail-panel';
        panel.className = 'mt-4 bg-brand-dark border border-white/10 rounded-xl overflow-hidden';
        panel.style.cssText = 'animation: slideDown 0.2s ease;';

        // Cabeçalho do painel
        panel.innerHTML = `
        <style>@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}</style>
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <div>
                <h3 class="text-white font-bold text-lg capitalize">${weekday}, ${dateLabel}</h3>
                ${holiday ? `<span class="text-red-400 text-xs font-semibold">🔴 ${holiday.name || 'Feriado'}</span>` : ''}
                <div class="flex gap-4 mt-1 text-xs text-gray-400">
                    <span class="text-green-400 font-medium">${groups['AGENDA'].length} trabalhando</span>
                    <span class="text-blue-400">${groups['FOLGA'].length} de folga</span>
                    ${groups['FÉRIAS'].length ? `<span class="text-orange-400">${groups['FÉRIAS'].length} em férias</span>` : ''}
                </div>
            </div>
            <button onclick="document.getElementById('day-detail-panel').remove();document.querySelectorAll('.ring-2').forEach(e=>e.classList.remove('ring-2','ring-white/30'))"
                class="text-gray-400 hover:text-white transition p-2 rounded-lg hover:bg-white/5">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>`;

        if (evs.length === 0) {
            panel.innerHTML += `<p class="px-6 py-8 text-center text-gray-500">Nenhum técnico escalado para este dia.</p>`;
        } else {
            const body = document.createElement('div');
            body.className = 'divide-y divide-white/5';

            const order = ['AGENDA', 'FOLGA', 'FÉRIAS', 'OUTRO'];
            const labels   = { AGENDA: 'Trabalhando', FOLGA: 'De Folga', 'FÉRIAS': 'Férias / Ausente', OUTRO: 'Outros' };
            const headColor = { AGENDA: '#4ade80',  FOLGA: '#60a5fa',  'FÉRIAS': '#fb923c',  OUTRO: '#9ca3af' };
            const cardBg    = { AGENDA: 'rgba(34,197,94,0.10)',  FOLGA: 'rgba(59,130,246,0.10)',  'FÉRIAS': 'rgba(249,115,22,0.10)',  OUTRO: 'rgba(107,114,128,0.10)' };
            const avatarBg  = { AGENDA: 'rgba(34,197,94,0.20)',  FOLGA: 'rgba(59,130,246,0.20)',  'FÉRIAS': 'rgba(249,115,22,0.20)',  OUTRO: 'rgba(107,114,128,0.20)' };

            order.forEach(key => {
                if (!groups[key] || groups[key].length === 0) return;

                const section = document.createElement('div');
                section.style.cssText = 'padding:16px 24px;';
                section.innerHTML = `<p style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:${headColor[key]};margin-bottom:10px;">${labels[key]} (${groups[key].length})</p>`;

                const grid = document.createElement('div');
                grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;';

                groups[key].forEach(ev => {
                    const card = document.createElement('div');
                    card.style.cssText = `background:${cardBg[key]};border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;`;

                    const initial = capitalize((ev.username || '?').charAt(0));
                    const name    = capitalize(ev.username || '-');
                    const note    = ev.note ? `<p style="color:#9ca3af;font-size:10px;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${ev.note}</p>` : '';

                    card.innerHTML = `
                        <div style="width:28px;height:28px;border-radius:50%;background:${avatarBg[key]};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:700;color:#fff;">
                            ${initial}
                        </div>
                        <div style="min-width:0;">
                            <p style="color:#f9fafb;font-size:13px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</p>
                            ${note}
                        </div>`;
                    grid.appendChild(card);
                });

                section.appendChild(grid);
                body.appendChild(section);
            });

            panel.appendChild(body);
        }

        // Insere painel abaixo do calendário dentro do mesmo card
        const calendarSection = wrap.closest('.bg-brand-dark') || wrap.parentElement;
        calendarSection.appendChild(panel);

        if (window.lucide) lucide.createIcons();

        // Scroll suave para o painel
        setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    // ── Inicializa com o mês atual ──────────────────────────────────────────
    const firstBtn = tabsContainer.querySelector('button');
    if (firstBtn) {
        await selectTab(firstBtn, months[0].year, months[0].month);
    }
})();