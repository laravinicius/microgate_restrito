// js/escala-admin.js
// Calendário administrativo com edição de escala (somente perfil Admin nível 1).

(async function () {
    const routes = window.APP_ROUTES || {};
    const wrap = document.getElementById('calendar-wrap');
    if (!wrap) return;

    // Flags passadas pelo PHP via restricted.php
    const canEdit   = window.IS_ADMIN   === true;
    const csrfToken = window.CSRF_TOKEN || '';

    // Estado do mês carregado (para refresh após salvar)
    let currentYear        = null;
    let currentMonth       = null;
    let currentEventsMap   = {};
    let currentHolidaysMap = {};

    // ── Abas de meses ───────────────────────────────────────────────────────
    const months = [];
    const now = new Date();
    for (let i = -2; i <= 3; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
        months.push({
            year:  d.getFullYear(),
            month: d.getMonth(),
            label: capitalize(d.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }))
        });
    }

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
            ? 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm md:text-xs transition'
            : 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm md:text-xs transition';
        btn.textContent = m.label;
        btn.onclick = () => selectTab(btn, m.year, m.month);
        tabsContainer.appendChild(btn);
    });

    // ── Selecionar aba ──────────────────────────────────────────────────────
    async function selectTab(btn, year, month) {
        tabsContainer.querySelectorAll('button').forEach(b => {
            b.className = 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm transition';
        });
        btn.className = 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm transition';

        document.getElementById('day-detail-panel')?.remove();
        wrap.innerHTML = '<div class="p-8 text-center text-gray-500 text-sm">Carregando...</div>';

        await loadMonth(year, month);
    }

    // ── Carregar dados do mês ────────────────────────────────────────────────
    async function loadMonth(year, month, reopenDate = null) {
        currentYear  = year;
        currentMonth = month;

        const start = new Date(year, month, 1);
        const end   = new Date(year, month + 1, 0);
        const fmt   = d => d.toISOString().slice(0, 10);

        try {
            const res = await fetch(`${routes.getSchedule || '/app/actions/schedule/get_schedule.php'}?start=${fmt(start)}&end=${fmt(end)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            currentEventsMap   = {};
            currentHolidaysMap = {};

            (data.data || []).forEach(ev => {
                currentEventsMap[ev.date] = currentEventsMap[ev.date] || [];
                currentEventsMap[ev.date].push(ev);
            });
            (data.holidays || []).forEach(h => {
                currentHolidaysMap[h.date] = h;
            });

            wrap.innerHTML = '';
            wrap.appendChild(renderMonth(start, currentEventsMap, currentHolidaysMap));
            if (window.lucide) lucide.createIcons();

            // Reabre painel do dia após salvar
            if (reopenDate) {
                const [, , od] = reopenDate.split('-');
                openDayPanel(reopenDate, parseInt(od), month, year, currentEventsMap, currentHolidaysMap);
            }
        } catch (err) {
            wrap.innerHTML = `<div class="p-4 text-red-400">Erro ao carregar escala: ${err.message}</div>`;
        }
    }

    // ── Renderizar mês ──────────────────────────────────────────────────────
    function renderMonth(date, eventsMap, holidaysMap) {
        const el = document.createElement('div');
        el.className = 'w-full bg-brand-dark border border-white/10 rounded-xl overflow-hidden';

        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'block w-full overflow-x-auto';

        const table = document.createElement('table');
        table.className = 'w-full min-w-[720px] border-collapse';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'border-b border-white/10';
        ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'].forEach((d, i) => {
            const th = document.createElement('th');
            th.className = (i >= 5)
                ? 'px-1 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.05em] text-gray-500'
                : 'px-1 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.05em] text-gray-400';
            th.textContent = d;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody       = document.createElement('tbody');
        const firstDay    = (new Date(date.getFullYear(), date.getMonth(), 1).getDay() + 6) % 7;
        const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        const fmt         = d => d.toISOString().slice(0, 10);
        const todayIso    = fmt(new Date());

        let cell = 0;
        let week = newWeekRow();

        for (let i = 0; i < firstDay; i++) { week.appendChild(emptyCell()); cell++; }

        for (let d = 1; d <= daysInMonth; d++) {
            const cur      = new Date(date.getFullYear(), date.getMonth(), d);
            const iso      = fmt(cur);
            const evs      = eventsMap[iso] || [];
            const holiday  = holidaysMap[iso] || null;
            const isToday  = iso === todayIso;
            const isWknd   = (cur.getDay() === 0 || cur.getDay() === 6);

            const td = document.createElement('td');
            td.className = isWknd
                ? 'h-[100px] border border-white/5 bg-white/[0.015] p-2 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5'
                : 'h-[100px] border border-white/5 p-2 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5';
            td.setAttribute('data-date', iso);

            // Número do dia
            const dayRow = document.createElement('div');
            dayRow.className = 'mb-[5px] flex items-start justify-between';

            const numSpan = document.createElement('span');
            if (isToday) {
                numSpan.className = 'inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-black';
            } else {
                numSpan.className = isWknd
                    ? 'text-sm font-bold text-gray-500'
                    : 'text-sm font-bold text-gray-200';
            }
            numSpan.textContent = d;
            dayRow.appendChild(numSpan);

            // Contadores
            if (evs.length > 0) {
                const working = evs.filter(e => (e.shift || '').toUpperCase().includes('AGENDA')).length;
                const folga   = evs.filter(e => (e.shift || '').toUpperCase().includes('FOLGA')).length;
                const ausente = evs.filter(e => {
                    const s = (e.shift || '').toUpperCase();
                    return s.includes('FÉRIAS') || s.includes('FERIAS') || s.includes('AUSENTE');
                }).length;

                const cntWrap = document.createElement('div');
                cntWrap.className = 'flex flex-col items-end gap-px';

                if (working > 0) {
                    const w = document.createElement('span');
                    w.className = 'cal-count whitespace-nowrap font-semibold text-green-400';
                    w.textContent = working + ' trabalhando';
                    cntWrap.appendChild(w);
                }
                if (folga > 0) {
                    const f = document.createElement('span');
                    f.className = 'cal-count whitespace-nowrap text-blue-400';
                    f.textContent = folga + ' sem agenda';
                    cntWrap.appendChild(f);
                }
                if (ausente > 0) {
                    const a = document.createElement('span');
                    a.className = 'cal-count whitespace-nowrap text-orange-400';
                    a.textContent = ausente + ' férias/aus.';
                    cntWrap.appendChild(a);
                }
                dayRow.appendChild(cntWrap);
            }
            td.appendChild(dayRow);

            // Feriado
            if (holiday) {
                const hl = document.createElement('div');
                hl.className = 'mt-0.5 overflow-hidden text-ellipsis whitespace-nowrap text-[10px] font-semibold text-red-400';
                hl.textContent = '● ' + (holiday.name || 'Feriado');
                hl.title = holiday.name || 'Feriado';
                td.appendChild(hl);
            }

            td.onclick = () => openDayPanel(iso, d, date.getMonth(), date.getFullYear(), eventsMap, holidaysMap);

            week.appendChild(td);
            cell++;
            if (cell % 7 === 0) { tbody.appendChild(week); week = newWeekRow(); }
        }

        while (cell % 7 !== 0) { week.appendChild(emptyCell()); cell++; }
        if (week.children.length) tbody.appendChild(week);

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
        td.className = 'h-[90px] border border-white/5 bg-white/[0.005]';
        return td;
    }

    function formatShiftLabel(shift) {
        const normalized = (shift || '').toUpperCase();
        if (normalized.includes('FOLGA')) return 'Sem agenda';
        if (normalized.includes('AGENDA')) return 'Agenda';
        if (normalized.includes('FÉRIAS') || normalized.includes('FERIAS')) return 'Férias';
        if (normalized.includes('AUSENTE')) return 'Ausente';
        return capitalize((shift || '').toLowerCase());
    }

    // ── Painel de detalhes do dia ────────────────────────────────────────────
    function openDayPanel(iso, day, month, year, eventsMap, holidaysMap) {
        document.getElementById('day-detail-panel')?.remove();
        document.querySelectorAll('[data-date].ring-2').forEach(el => el.classList.remove('ring-2', 'ring-white/30'));
        document.querySelectorAll(`[data-date="${iso}"]`).forEach(el => el.classList.add('ring-2', 'ring-white/30'));

        const evs     = eventsMap[iso] || [];
        const holiday = holidaysMap[iso] || null;
        const weekday = new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long' });
        const [y, m, d] = iso.split('-');
        const dateLabel = `${d}/${m}/${y}`;

        const groups = { AGENDA: [], FOLGA: [], 'FÉRIAS': [], OUTRO: [] };
        evs.forEach(ev => {
            const s = (ev.shift || '').toUpperCase();
            if (s.includes('AGENDA'))                                                   groups['AGENDA'].push(ev);
            else if (s.includes('FOLGA'))                                               groups['FOLGA'].push(ev);
            else if (s.includes('FÉRIAS') || s.includes('FERIAS') || s.includes('AUSENTE')) groups['FÉRIAS'].push(ev);
            else                                                                        groups['OUTRO'].push(ev);
        });

        const panel = document.createElement('div');
        panel.id = 'day-detail-panel';
        panel.setAttribute('data-date', iso);
        panel.className = 'mt-4 overflow-visible rounded-xl border border-white/10 bg-brand-dark animate-slide-down';

        panel.innerHTML = `
        <div class="flex items-center justify-between px-4 md:px-6 py-4 border-b border-white/10">
            <div>
                <h3 class="text-white font-bold text-xl md:text-lg capitalize">${weekday}, ${dateLabel}</h3>
                ${holiday ? `<span class="text-red-400 text-sm font-semibold">● ${holiday.name || 'Feriado'}</span>` : ''}
                <div class="flex flex-wrap gap-3 mt-1 text-sm md:text-xs">
                    <span class="font-medium text-green-400">${groups['AGENDA'].length} trabalhando</span>
                    <span class="text-blue-400">${groups['FOLGA'].length} sem agenda</span>
                    ${groups['FÉRIAS'].length ? `<span class="text-orange-400">${groups['FÉRIAS'].length} férias/ausente</span>` : ''}
                    ${groups['OUTRO'].length  ? `<span class="text-gray-400">${groups['OUTRO'].length} outros</span>` : ''}
                </div>
            </div>
            <button onclick="document.getElementById('day-detail-panel').remove();document.querySelectorAll('.ring-2').forEach(e=>e.classList.remove('ring-2','ring-white/30'))"
                class="text-gray-400 hover:text-white transition p-2 rounded-lg hover:bg-white/5 flex-shrink-0 ml-2">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>`;

        if (evs.length === 0) {
            panel.innerHTML += `<p class="px-6 py-8 text-center text-gray-500 text-sm">Nenhum técnico escalado para este dia.</p>`;
        } else {
            const body = document.createElement('div');
            body.className = 'divide-y divide-white/5';

            const order     = ['AGENDA', 'FOLGA', 'FÉRIAS', 'OUTRO'];
            const labels    = { AGENDA: 'Trabalhando', FOLGA: 'Sem agenda', 'FÉRIAS': 'Férias / Ausente', OUTRO: 'Outros' };
            const headColorClass = { AGENDA: 'text-green-400', FOLGA: 'text-blue-400', 'FÉRIAS': 'text-orange-400', OUTRO: 'text-gray-400' };
            const cardClass = {
                AGENDA: 'flex items-center gap-2 rounded-lg bg-green-500/10 px-2.5 py-2 relative',
                FOLGA: 'flex items-center gap-2 rounded-lg bg-blue-500/10 px-2.5 py-2 relative',
                'FÉRIAS': 'flex items-center gap-2 rounded-lg bg-orange-500/10 px-2.5 py-2 relative',
                OUTRO: 'flex items-center gap-2 rounded-lg bg-gray-500/10 px-2.5 py-2 relative'
            };
            const avatarClass = {
                AGENDA: 'flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-full bg-green-500/20 text-[13px] font-bold text-white',
                FOLGA: 'flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-full bg-blue-500/20 text-[13px] font-bold text-white',
                'FÉRIAS': 'flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-full bg-orange-500/20 text-[13px] font-bold text-white',
                OUTRO: 'flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-full bg-gray-500/20 text-[13px] font-bold text-white'
            };

            order.forEach(key => {
                if (!groups[key] || groups[key].length === 0) return;

                const section = document.createElement('div');
                section.className = 'px-6 py-4';
                section.innerHTML = `<p class="mb-2.5 text-xs font-bold uppercase tracking-[0.08em] ${headColorClass[key]}">${labels[key]} (${groups[key].length})</p>`;

                const grid = document.createElement('div');
                grid.className = 'grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(200px,1fr))]';

                groups[key].forEach(ev => {
                    grid.appendChild(buildTechCard(ev, key, iso, cardClass, avatarClass));
                });

                section.appendChild(grid);
                body.appendChild(section);
            });

            panel.appendChild(body);
        }

        const calendarSection = wrap.closest('.bg-brand-dark') || wrap.parentElement;
        calendarSection.appendChild(panel);

        if (window.lucide) lucide.createIcons();
        setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }

    // ── Card de técnico ──────────────────────────────────────────────────────
    function buildTechCard(ev, groupKey, iso, cardClass, avatarClass) {
        const card = document.createElement('div');
        card.className = cardClass[groupKey] || cardClass.OUTRO;

        const displayName = ev.full_name || ev.username || '-';
        const initial     = capitalize(displayName.charAt(0));

        // Avatar
        const avatar = document.createElement('div');
        avatar.className = avatarClass[groupKey] || avatarClass.OUTRO;
        avatar.textContent = initial;

        // Info
        const info = document.createElement('div');
        info.className = 'min-w-0 flex-1';
        info.innerHTML = `<p class="m-0 overflow-hidden text-ellipsis whitespace-nowrap text-sm font-medium text-gray-50">${displayName}</p>
                          <p class="m-0 mt-0.5 text-[11px] text-gray-400">${formatShiftLabel(ev.shift || '')}</p>`;

        card.appendChild(avatar);
        card.appendChild(info);

        // Botão de edição — somente admin nível 1
        if (canEdit) {
            const editBtn = document.createElement('button');
            editBtn.title = 'Editar status';
            editBtn.className = 'flex flex-shrink-0 items-center rounded-md border border-white/15 bg-white/10 px-1.5 py-1 text-gray-400 transition hover:bg-white/20 hover:text-white';
            editBtn.innerHTML = '<i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i>';
            editBtn.onclick = (e) => {
                e.stopPropagation();
                openInlineEditor(ev, iso, card);
            };
            card.appendChild(editBtn);
        }

        return card;
    }

    // ── Editor inline de turno ───────────────────────────────────────────────
    function openInlineEditor(ev, iso, card) {
        // Fecha outros editores abertos
        document.querySelectorAll('.inline-shift-editor').forEach(el => {
            el.parentElement?.classList.remove('z-[30]');
            el.remove();
        });

        const displayName  = ev.full_name || ev.username || '-';
        const currentShift = (ev.shift || '').toUpperCase();

        const shifts      = ['AGENDA', 'FOLGA', 'FÉRIAS', 'AUSENTE'];
        const shiftColors = { AGENDA: '#4ade80', FOLGA: '#60a5fa', 'FÉRIAS': '#fb923c', AUSENTE: '#9ca3af' };
        const shiftLabels = { AGENDA: 'Agenda', FOLGA: 'Sem agenda', 'FÉRIAS': 'Férias', AUSENTE: 'Ausente' };

        const editor = document.createElement('div');
        editor.className = 'inline-shift-editor absolute right-0 top-[calc(100%+6px)] z-[200] min-w-[210px] rounded-[10px] border border-white/15 bg-[#1e1e1e] p-3 shadow-[0_8px_32px_rgba(0,0,0,0.7)]';
        card.classList.add('z-[30]');

        // Cabeçalho do editor
        const editorHeader = document.createElement('p');
        editorHeader.className = 'm-0 mb-2.5 overflow-hidden text-ellipsis whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.06em] text-gray-400';
        editorHeader.textContent = displayName;
        editor.appendChild(editorHeader);

        // Opções de turno
        const optionsWrap = document.createElement('div');
        optionsWrap.className = 'flex flex-col gap-1';

        shifts.forEach(s => {
            const btn = document.createElement('button');
            const isActive = s === currentShift;
            btn.className = isActive
                ? 'flex w-full items-center gap-2 rounded-md border border-white/15 bg-white/10 px-2.5 py-1.5 text-left transition'
                : 'flex w-full items-center gap-2 rounded-md border border-transparent bg-transparent px-2.5 py-1.5 text-left transition hover:bg-white/10';
            const dotClass = {
                AGENDA: 'bg-green-400',
                FOLGA: 'bg-blue-400',
                'FÉRIAS': 'bg-orange-400',
                AUSENTE: 'bg-gray-400'
            };
            btn.innerHTML = `
                <span class="h-2 w-2 flex-shrink-0 rounded-full ${dotClass[s] || 'bg-gray-400'}"></span>
                <span class="flex-1 text-[13px] ${isActive ? 'font-semibold text-gray-50' : 'font-normal text-gray-50'}">${shiftLabels[s]}</span>
                ${isActive ? '<span class="text-[10px] text-gray-500">atual</span>' : ''}`;
            btn.onclick = async (e) => {
                e.stopPropagation();
                if (isActive) { editor.remove(); return; }
                await saveShift(ev.user_id, iso, s, editor);
            };
            optionsWrap.appendChild(btn);
        });

        editor.appendChild(optionsWrap);

        // Separador + remover
        const sep = document.createElement('div');
        sep.className = 'my-2 border-t border-white/10';
        editor.appendChild(sep);

        const delBtn = document.createElement('button');
        delBtn.className = 'flex w-full items-center gap-1.5 rounded-md bg-transparent px-2.5 py-1.5 text-left text-xs text-red-400 transition hover:bg-red-500/10';
        delBtn.innerHTML = '<i data-lucide="trash-2" class="h-3 w-3 pointer-events-none"></i> Remover do dia';
        delBtn.onclick = async (e) => {
            e.stopPropagation();
            if (!confirm(`Remover ${displayName} da escala de ${iso}?`)) return;
            await saveShift(ev.user_id, iso, null, editor, true);
        };
        editor.appendChild(delBtn);

        // Área de status
        const statusEl = document.createElement('div');
        statusEl.id = `editor-status-${ev.user_id}`;
        statusEl.className = 'mt-2 min-h-[14px] text-center text-[11px] text-gray-400';
        editor.appendChild(statusEl);

        card.appendChild(editor);
        if (window.lucide) lucide.createIcons();

        // Fechar ao clicar fora
        const closeEditor = (e) => {
            if (!editor.contains(e.target)) {
                card.classList.remove('z-[30]');
                editor.remove();
                document.removeEventListener('click', closeEditor);
            }
        };
        setTimeout(() => document.addEventListener('click', closeEditor), 10);
    }

    // ── Salvar turno via API ─────────────────────────────────────────────────
    async function saveShift(userId, date, shift, editorEl, isDelete = false) {
        const statusEl = editorEl.querySelector(`[id^="editor-status"]`);
        if (statusEl) {
            statusEl.textContent = 'Salvando…';
            statusEl.className = 'mt-2 min-h-[14px] text-center text-[11px] text-gray-400';
        }
        editorEl.querySelectorAll('button').forEach(b => b.disabled = true);

        try {
            const res = await fetch(routes.saveScheduleDay || '/app/actions/schedule/save_schedule_day.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    csrf_token: csrfToken,
                    user_id:    userId,
                    date:       date,
                    shift:      shift || '',
                    action:     isDelete ? 'delete' : 'upsert'
                })
            });

            const data = await res.json();

            if (!data.success) {
                if (statusEl) {
                    statusEl.textContent = data.message || 'Erro ao salvar.';
                    statusEl.className = 'mt-2 min-h-[14px] text-center text-[11px] text-red-400';
                }
                editorEl.querySelectorAll('button').forEach(b => b.disabled = false);
                return;
            }

            if (statusEl) {
                statusEl.textContent = '✓ Salvo!';
                statusEl.className = 'mt-2 min-h-[14px] text-center text-[11px] text-green-400';
            }

            setTimeout(async () => {
                editorEl.remove();
                await loadMonth(currentYear, currentMonth, date);
            }, 400);

        } catch (err) {
            if (statusEl) {
                statusEl.textContent = 'Erro de conexão.';
                statusEl.className = 'mt-2 min-h-[14px] text-center text-[11px] text-red-400';
            }
            editorEl.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    // ── Inicializa com o mês atual ──────────────────────────────────────────
    const currentMonthIndex = 2;
    const currentBtn = tabsContainer.querySelectorAll('button')[currentMonthIndex];
    if (currentBtn) await selectTab(currentBtn, months[currentMonthIndex].year, months[currentMonthIndex].month);
})();
