// js/escala-abas.js
// Visualização de agenda por abas de meses — com edição inline para admins

(async function () {
    const routes = window.APP_ROUTES || {};

    const wrap = document.getElementById('calendar-wrap');
    if (!wrap) return;

    const EDIT_MODE  = !!window.ADMIN_EDIT_MODE;
    const TARGET_UID = window.TARGET_USER_ID || null;
    const CSRF       = window.CSRF_TOKEN || '';

    // ── Estado dos eventos (mutável durante edição) ────────────────────────
    // Chave: "YYYY-MM-DD", valor: objeto de evento (ou null = sem registro)
    const eventsState = {};

    // ── Abas de meses ──────────────────────────────────────────────────────
    const months = [];
    const now = new Date();
    const historyOnly = window.HISTORY_ONLY === true;
    const monthOffsets = historyOnly ? [-1, -2] : [-2, -1, 0, 1, 2, 3];

    for (const offset of monthOffsets) {
        const d = new Date(now.getFullYear(), now.getMonth() + offset, 1);
        months.push({
            year: d.getFullYear(),
            month: d.getMonth(),
            label: capitalize(d.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }))
        });
    }

    const currentMonthIndex = historyOnly ? 0 : 2;

    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'mb-6 flex flex-wrap gap-2 pb-2 border-b border-white/10';

    months.forEach((m, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = idx === currentMonthIndex
            ? 'px-4 py-2 rounded-t-lg bg-gray-600 text-white font-semibold whitespace-nowrap transition'
            : 'px-4 py-2 rounded-t-lg bg-white/5 text-gray-300 hover:bg-white/10 font-semibold whitespace-nowrap transition';
        btn.textContent = m.label;
        btn.onclick = () => selectTab(btn, m.year, m.month);
        tabsContainer.appendChild(btn);
    });

    wrap.parentElement.insertBefore(tabsContainer, wrap);

    // ── Selecionar aba ─────────────────────────────────────────────────────
    async function selectTab(btn, year, month) {
        tabsContainer.querySelectorAll('button').forEach(b => {
            b.className = 'px-4 py-2 rounded-t-lg bg-white/5 text-gray-300 hover:bg-white/10 font-semibold whitespace-nowrap transition';
        });
        btn.className = 'px-4 py-2 rounded-t-lg bg-gray-600 text-white font-semibold whitespace-nowrap transition';

        wrap.innerHTML = '<div class="p-6 text-center text-sm text-gray-500">Carregando...</div>';

        const start = new Date(year, month, 1);
        const end   = new Date(year, month + 1, 0);
        const fmt   = d => d.toISOString().slice(0, 10);

        let apiUrl = `${routes.getSchedule || '/app/actions/schedule/get_schedule.php'}?start=${fmt(start)}&end=${fmt(end)}`;
        if (TARGET_UID) apiUrl += `&user_id=${TARGET_UID}`;

        try {
            const res = await fetch(apiUrl);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const schedule = await res.json();
            const events   = schedule.data || [];

            // Popula o estado mutável
            events.forEach(ev => { eventsState[ev.date] = ev; });

            const eventsByDate = {};
            events.forEach(ev => {
                eventsByDate[ev.date] = eventsByDate[ev.date] || [];
                eventsByDate[ev.date].push(ev);
            });

            const holidaysByDate = {};
            (schedule.holidays || []).forEach(h => { holidaysByDate[h.date] = h; });

            wrap.innerHTML = '';
            wrap.appendChild(renderMonth(start, eventsByDate, holidaysByDate));

            if (window.lucide) lucide.createIcons();

        } catch (err) {
            wrap.innerHTML = `<div class="p-4 text-red-400">Erro ao carregar escala: ${err.message}</div>`;
        }
    }

    // ── Renderizar mês ─────────────────────────────────────────────────────
    function renderMonth(date, eventsMap, holidaysMap) {
        const el = document.createElement('div');
        el.className = 'w-full bg-brand-dark border border-white/10 rounded-lg';

        const monthName = date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
        const hdr = document.createElement('div');
        hdr.className = 'mb-4 px-2';
        hdr.innerHTML = `<h3 class="text-white font-bold text-lg md:text-2xl">${capitalize(monthName)}</h3>`;
        el.appendChild(hdr);

        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'w-full block overflow-x-auto';

        const table = document.createElement('table');
        table.className = 'border-collapse w-full min-w-[560px]';

        // Header semana
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'bg-white/5 border-b border-white/10';
        ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].forEach(d => {
            const th = document.createElement('th');
            th.className = 'px-2 md:px-4 py-2 md:py-3 text-center text-[8px] md:text-xs font-semibold text-gray-400 uppercase';
            th.textContent = d;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        const firstDay    = (new Date(date.getFullYear(), date.getMonth(), 1).getDay() + 6) % 7;
        const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        const fmt         = d => d.toISOString().slice(0, 10);
        const todayIso    = fmt(new Date());

        let cell = 0;
        let week = newWeekRow();

        for (let i = 0; i < firstDay; i++) {
            week.appendChild(emptyCell());
            cell++;
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const cur     = new Date(date.getFullYear(), date.getMonth(), d);
            const iso     = fmt(cur);
            const isToday = iso === todayIso;
            const holiday = holidaysMap[iso] || null;
            const evs     = eventsMap[iso] || [];

            const td = document.createElement('td');
            td.className = 'relative h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5 hover:bg-white/5 transition cursor-pointer align-top';
            td.setAttribute('data-date', iso);

            // Número do dia
            const dayHeader = document.createElement('div');
            dayHeader.className = 'mb-1 flex items-start justify-between';

            const numSpan = document.createElement('span');
            if (isToday) {
                numSpan.className = 'inline-flex h-[22px] w-[22px] flex-shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-bold text-black';
            } else {
                numSpan.className = 'text-sm md:text-lg text-gray-300 font-bold';
            }
            numSpan.textContent = d;
            dayHeader.appendChild(numSpan);

            // Botão de edição (somente admin)
            if (EDIT_MODE && TARGET_UID) {
                const editBtn = document.createElement('button');
                editBtn.className = 'day-edit-btn';
                editBtn.type = 'button';
                editBtn.innerHTML = '<i data-lucide="pencil" class="w-2.5 h-2.5"></i> Editar';
                editBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    openEditPopover(editBtn, iso, TARGET_UID, td, eventsMap);
                });
                dayHeader.appendChild(editBtn);
            }

            td.appendChild(dayHeader);

            // Feriado
            if (holiday) {
                const hl = document.createElement('div');
                hl.className = 'mb-1 text-[9px] font-bold text-red-400 md:text-[10px]';
                hl.textContent = '● ' + (holiday.name || 'Feriado');
                td.appendChild(hl);
            }

            // Badges de status
            const list = document.createElement('div');
            list.className = 'flex flex-col gap-1';
            list.setAttribute('data-badges', iso);

            renderBadges(list, evs);
            td.appendChild(list);

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
        if (week.children.length) tbody.appendChild(week);

        table.appendChild(tbody);
        tableWrapper.appendChild(table);
        el.appendChild(tableWrapper);
        return el;
    }

    // ── Renderiza badges de status numa célula ─────────────────────────────
    function renderBadges(container, evs) {
        container.innerHTML = '';
        evs.slice(0, 3).forEach(ev => {
            const badge = document.createElement('div');
            const shift = (ev.shift || '').toUpperCase();
            let variant = 'bg-gray-600 text-gray-200';
            if (shift.includes('AGENDA')) {
                variant = 'bg-green-800 text-green-200';
            } else if (shift.includes('FOLGA')) {
                variant = 'bg-blue-800 text-blue-200';
            } else if (shift.includes('FÉRIAS') || shift.includes('FERIAS')) {
                variant = 'bg-orange-800 text-orange-200';
            } else if (shift.includes('AUSENTE')) {
                variant = 'bg-gray-600 text-gray-200';
            }
            badge.className = `overflow-hidden whitespace-nowrap text-ellipsis rounded px-1.5 py-0.5 text-[10px] font-medium ${variant}`;
            badge.textContent = shift.includes('FOLGA') ? 'SEM AGENDA' : (shift || ev.note || '—');
            container.appendChild(badge);
        });
        if (evs.length > 3) {
            const more = document.createElement('div');
            more.className = 'text-[10px] text-gray-400';
            more.textContent = `+${evs.length - 3} mais`;
            container.appendChild(more);
        }
    }

    // ── Popover de edição ──────────────────────────────────────────────────
    let activePopover = null;

    function closeActivePopover() {
        if (activePopover) {
            activePopover.remove();
            activePopover = null;
        }
    }

    document.addEventListener('click', e => {
        if (activePopover && !activePopover.contains(e.target)) closeActivePopover();
    });

    function openEditPopover(anchorBtn, iso, userId, tdCell, eventsMap) {
        closeActivePopover();

        const currentEvs = eventsMap[iso] || [];
        const currentShift = currentEvs.length > 0 ? (currentEvs[0].shift || '').toUpperCase() : '';

        const popover = document.createElement('div');
        popover.className = 'edit-popover';

        // Título
        const [y, m, d] = iso.split('-');
        const titleEl = document.createElement('div');
        titleEl.className = 'px-1 py-[2px] pb-1.5 text-[11px] font-semibold tracking-[0.04em] text-gray-500';
        titleEl.textContent = `${d}/${m}/${y}`;
        popover.appendChild(titleEl);

        const options = [
            { label: '✓ Agenda',  value: 'AGENDA',  cls: 'popover-agenda'  },
            { label: '◌ Sem agenda', value: 'FOLGA', cls: 'popover-folga' },
            { label: '✈ Férias',  value: 'FÉRIAS',  cls: 'popover-ferias'  },
            { label: '— Ausente', value: 'AUSENTE', cls: 'popover-ausente' },
        ];

        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = opt.cls;
            btn.textContent = opt.label;
            if (currentShift === opt.value) {
                btn.classList.add('ring-2', 'ring-inset', 'ring-white/40');
            }
            btn.addEventListener('click', e => {
                e.stopPropagation();
                saveDay(userId, iso, opt.value, tdCell, eventsMap, popover);
            });
            popover.appendChild(btn);
        });

        // Divisor
        if (currentShift !== '') {
            const divider = document.createElement('div');
            divider.className = 'popover-divider';
            popover.appendChild(divider);

            const removeBtn = document.createElement('button');
            removeBtn.className = 'popover-remover';
            removeBtn.textContent = '✕ Remover';
            removeBtn.addEventListener('click', e => {
                e.stopPropagation();
                saveDay(userId, iso, '', tdCell, eventsMap, popover);
            });
            popover.appendChild(removeBtn);
        }

        // Posiciona o popover abaixo do botão
        tdCell.appendChild(popover);
        activePopover = popover;

        // Ajuste de posição se sair da tela
        const rect = popover.getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) {
            popover.classList.add('edit-popover--align-right');
        }

        if (window.lucide) lucide.createIcons();
    }

    // ── Salvar alteração via API ────────────────────────────────────────────
    async function saveDay(userId, iso, shift, tdCell, eventsMap, popover) {
        // Feedback visual imediato
        popover.classList.add('is-saving');

        try {
            const res = await fetch(routes.saveScheduleDay || '/app/actions/schedule/save_schedule_day.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: CSRF, user_id: userId, date: iso, shift })
            });

            // Tenta parsear JSON mesmo em caso de erro HTTP
            let data;
            const text = await res.text();
            try {
                data = JSON.parse(text);
            } catch (_) {
                console.error('Resposta não-JSON do servidor:', text);
                showToast('Erro do servidor. Ver console para detalhes.', 'error');
                popover.classList.remove('is-saving');
                return;
            }

            if (!data.success) {
                showToast(data.message || 'Erro ao salvar.', 'error');
                popover.classList.remove('is-saving');
                return;
            }

            // Atualiza o estado local
            if (shift === '') {
                delete eventsMap[iso];
            } else {
                eventsMap[iso] = [{ shift: shift.toUpperCase(), note: '' }];
            }

            // Re-renderiza apenas os badges da célula
            const badgesContainer = tdCell.querySelector(`[data-badges="${iso}"]`);
            if (badgesContainer) renderBadges(badgesContainer, eventsMap[iso] || []);

            closeActivePopover();
            showToast(shift === '' ? 'Registro removido.' : `Salvo: ${data.shift || shift}`, 'success');

        } catch (err) {
            console.error('Fetch error:', err);
            showToast('Erro de conexão: ' + err.message, 'error');
            popover.classList.remove('is-saving');
        }
    }

    // ── Toast de feedback ──────────────────────────────────────────────────
    function showToast(msg, type) {
        let toast = document.getElementById('schedule-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'schedule-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.className   = `show ${type}`;
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => { toast.className = ''; }, 2800);
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    function newWeekRow() {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-white/10';
        return tr;
    }

    function emptyCell() {
        const td = document.createElement('td');
        td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5';
        return td;
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    // ── Inicializa no mês atual ────────────────────────────────────────────
    const currentBtn = tabsContainer.querySelectorAll('button')[currentMonthIndex];
    if (currentBtn) {
        await selectTab(currentBtn, months[currentMonthIndex].year, months[currentMonthIndex].month);
    }

})();
