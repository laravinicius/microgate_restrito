// js/escala-admin.js
// Calendário administrativo com edição de escala (somente perfil Admin nível 1).

(async function () {
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
    for (let i = 0; i < 6; i++) {
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
            ? 'px-4 py-2 rounded-lg bg-white/15 border border-white/20 text-white font-semibold whitespace-nowrap text-sm transition'
            : 'px-4 py-2 rounded-lg bg-white/5 border border-white/5 text-gray-400 hover:bg-white/10 hover:text-white font-medium whitespace-nowrap text-sm transition';
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
            const res = await fetch(`./get_schedule.php?start=${fmt(start)}&end=${fmt(end)}`);
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
        tableWrapper.style.cssText = 'width:100%;display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;';

        const table = document.createElement('table');
        table.className = 'border-collapse w-full';
        table.style.minWidth = '720px';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'border-b border-white/10';
        ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'].forEach((d, i) => {
            const th = document.createElement('th');
            th.style.cssText = 'padding:10px 4px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:' + (i >= 5 ? '#6b7280' : '#9ca3af') + ';';
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
            td.className = 'border border-white/5 align-top cursor-pointer transition-colors duration-100 hover:bg-white/5';
            td.style.cssText = 'height:90px;padding:8px;vertical-align:top;' + (isWknd ? 'background:rgba(255,255,255,0.015);' : '');
            td.setAttribute('data-date', iso);

            // Número do dia
            const dayRow = document.createElement('div');
            dayRow.style.cssText = 'display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:5px;';

            const numSpan = document.createElement('span');
            if (isToday) {
                numSpan.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#ffffff;color:#000000;font-size:11px;font-weight:700;flex-shrink:0;';
            } else {
                numSpan.style.cssText = 'font-size:13px;font-weight:700;color:' + (isWknd ? '#6b7280' : '#e5e7eb') + ';';
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
                if (ausente > 0) {
                    const a = document.createElement('span');
                    a.className = 'cal-count';
                    a.style.cssText = 'color:#fb923c;white-space:nowrap;';
                    a.textContent = ausente + ' férias/aus.';
                    cntWrap.appendChild(a);
                }
                dayRow.appendChild(cntWrap);
            }
            td.appendChild(dayRow);

            // Feriado
            if (holiday) {
                const hl = document.createElement('div');
                hl.style.cssText = 'font-size:10px;font-weight:600;color:#f87171;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;';
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
        td.className = 'border border-white/5';
        td.style.cssText = 'height:90px;background:rgba(255,255,255,0.005);';
        return td;
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
        panel.className = 'mt-4 bg-brand-dark border border-white/10 rounded-xl overflow-hidden';
        panel.style.animation = 'slideDown 0.2s ease';

        panel.innerHTML = `
        <style>@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}</style>
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <div>
                <h3 class="text-white font-bold text-lg capitalize">${weekday}, ${dateLabel}</h3>
                ${holiday ? `<span class="text-red-400 text-xs font-semibold">● ${holiday.name || 'Feriado'}</span>` : ''}
                <div class="flex gap-4 mt-1 text-xs">
                    <span class="text-green-400 font-medium">${groups['AGENDA'].length} trabalhando</span>
                    <span class="text-blue-400">${groups['FOLGA'].length} de folga</span>
                    ${groups['FÉRIAS'].length ? `<span class="text-orange-400">${groups['FÉRIAS'].length} férias/ausente</span>` : ''}
                    ${groups['OUTRO'].length  ? `<span class="text-gray-400">${groups['OUTRO'].length} outros</span>` : ''}
                </div>
            </div>
            <button onclick="document.getElementById('day-detail-panel').remove();document.querySelectorAll('.ring-2').forEach(e=>e.classList.remove('ring-2','ring-white/30'))"
                class="text-gray-400 hover:text-white transition p-2 rounded-lg hover:bg-white/5">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>`;

        if (evs.length === 0) {
            panel.innerHTML += `<p class="px-6 py-8 text-center text-gray-500 text-sm">Nenhum técnico escalado para este dia.</p>`;
        } else {
            const body = document.createElement('div');
            body.className = 'divide-y divide-white/5';

            const order     = ['AGENDA', 'FOLGA', 'FÉRIAS', 'OUTRO'];
            const labels    = { AGENDA: 'Trabalhando', FOLGA: 'De Folga', 'FÉRIAS': 'Férias / Ausente', OUTRO: 'Outros' };
            const headColor = { AGENDA: '#4ade80', FOLGA: '#60a5fa', 'FÉRIAS': '#fb923c', OUTRO: '#9ca3af' };
            const cardBg    = { AGENDA: 'rgba(34,197,94,0.10)',   FOLGA: 'rgba(59,130,246,0.10)',  'FÉRIAS': 'rgba(249,115,22,0.10)',  OUTRO: 'rgba(107,114,128,0.10)' };
            const avatarBg  = { AGENDA: 'rgba(34,197,94,0.20)',   FOLGA: 'rgba(59,130,246,0.20)',  'FÉRIAS': 'rgba(249,115,22,0.20)',  OUTRO: 'rgba(107,114,128,0.20)' };

            order.forEach(key => {
                if (!groups[key] || groups[key].length === 0) return;

                const section = document.createElement('div');
                section.style.cssText = 'padding:16px 24px;';
                section.innerHTML = `<p style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:${headColor[key]};margin-bottom:10px;">${labels[key]} (${groups[key].length})</p>`;

                const grid = document.createElement('div');
                grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;';

                groups[key].forEach(ev => {
                    grid.appendChild(buildTechCard(ev, key, iso, cardBg, avatarBg));
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
    function buildTechCard(ev, groupKey, iso, cardBg, avatarBg) {
        const card = document.createElement('div');
        card.style.cssText = `background:${cardBg[groupKey]};border-radius:8px;padding:8px 10px;display:flex;align-items:center;gap:8px;position:relative;`;

        const displayName = ev.full_name || ev.username || '-';
        const initial     = capitalize(displayName.charAt(0));

        // Avatar
        const avatar = document.createElement('div');
        avatar.style.cssText = `width:30px;height:30px;border-radius:50%;background:${avatarBg[groupKey]};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:700;color:#fff;`;
        avatar.textContent = initial;

        // Info
        const info = document.createElement('div');
        info.style.cssText = 'min-width:0;flex:1;';
        info.innerHTML = `<p style="color:#f9fafb;font-size:13px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${displayName}</p>
                          <p style="color:#9ca3af;font-size:10px;margin:2px 0 0 0;">${capitalize((ev.shift||'').toLowerCase())}</p>`;

        card.appendChild(avatar);
        card.appendChild(info);

        // Botão de edição — somente admin nível 1
        if (canEdit) {
            const editBtn = document.createElement('button');
            editBtn.title = 'Editar status';
            editBtn.style.cssText = 'background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:6px;padding:5px 7px;cursor:pointer;display:flex;align-items:center;color:#9ca3af;flex-shrink:0;transition:background 0.15s,color 0.15s;';
            editBtn.innerHTML = '<i data-lucide="pencil" style="width:12px;height:12px;pointer-events:none;"></i>';
            editBtn.onmouseover = () => { editBtn.style.background = 'rgba(255,255,255,0.14)'; editBtn.style.color = '#fff'; };
            editBtn.onmouseout  = () => { editBtn.style.background = 'rgba(255,255,255,0.06)'; editBtn.style.color = '#9ca3af'; };
            editBtn.onclick = (e) => {
                e.stopPropagation();
                openInlineEditor(ev, iso, card, groupKey, cardBg, avatarBg);
            };
            card.appendChild(editBtn);
        }

        return card;
    }

    // ── Editor inline de turno ───────────────────────────────────────────────
    function openInlineEditor(ev, iso, card, groupKey, cardBg, avatarBg) {
        // Fecha outros editores abertos
        document.querySelectorAll('.inline-shift-editor').forEach(el => el.remove());

        const displayName  = ev.full_name || ev.username || '-';
        const currentShift = (ev.shift || '').toUpperCase();

        const shifts      = ['AGENDA', 'FOLGA', 'FÉRIAS', 'AUSENTE'];
        const shiftColors = { AGENDA: '#4ade80', FOLGA: '#60a5fa', 'FÉRIAS': '#fb923c', AUSENTE: '#9ca3af' };
        const shiftLabels = { AGENDA: 'Agenda', FOLGA: 'Folga', 'FÉRIAS': 'Férias', AUSENTE: 'Ausente' };

        const editor = document.createElement('div');
        editor.className = 'inline-shift-editor';
        editor.style.cssText = 'position:absolute;top:calc(100% + 6px);right:0;z-index:200;background:#1e1e1e;border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:12px;min-width:210px;box-shadow:0 8px 32px rgba(0,0,0,0.7);';

        // Cabeçalho do editor
        const editorHeader = document.createElement('p');
        editorHeader.style.cssText = 'color:#9ca3af;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 10px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
        editorHeader.textContent = displayName;
        editor.appendChild(editorHeader);

        // Opções de turno
        const optionsWrap = document.createElement('div');
        optionsWrap.style.cssText = 'display:flex;flex-direction:column;gap:3px;';

        shifts.forEach(s => {
            const btn = document.createElement('button');
            const isActive = s === currentShift;
            btn.style.cssText = `display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:6px;border:1px solid ${isActive ? 'rgba(255,255,255,0.15)' : 'transparent'};background:${isActive ? 'rgba(255,255,255,0.07)' : 'transparent'};cursor:pointer;width:100%;text-align:left;transition:background 0.1s;`;
            btn.innerHTML = `
                <span style="width:8px;height:8px;border-radius:50%;background:${shiftColors[s]};flex-shrink:0;"></span>
                <span style="color:#f9fafb;font-size:13px;font-weight:${isActive ? '600' : '400'};flex:1;">${shiftLabels[s]}</span>
                ${isActive ? '<span style="color:#6b7280;font-size:10px;">atual</span>' : ''}`;
            btn.onmouseover = () => { if (!isActive) btn.style.background = 'rgba(255,255,255,0.07)'; };
            btn.onmouseout  = () => { if (!isActive) btn.style.background = 'transparent'; };
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
        sep.style.cssText = 'margin:10px 0 8px;border-top:1px solid rgba(255,255,255,0.07);';
        editor.appendChild(sep);

        const delBtn = document.createElement('button');
        delBtn.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;border:none;background:transparent;cursor:pointer;color:#f87171;font-size:12px;width:100%;transition:background 0.1s;';
        delBtn.innerHTML = '<i data-lucide="trash-2" style="width:12px;height:12px;pointer-events:none;"></i> Remover do dia';
        delBtn.onmouseover = () => delBtn.style.background = 'rgba(239,68,68,0.10)';
        delBtn.onmouseout  = () => delBtn.style.background = 'transparent';
        delBtn.onclick = async (e) => {
            e.stopPropagation();
            if (!confirm(`Remover ${displayName} da escala de ${iso}?`)) return;
            await saveShift(ev.user_id, iso, null, editor, true);
        };
        editor.appendChild(delBtn);

        // Área de status
        const statusEl = document.createElement('div');
        statusEl.id = `editor-status-${ev.user_id}`;
        statusEl.style.cssText = 'font-size:11px;color:#9ca3af;margin-top:8px;min-height:14px;text-align:center;';
        editor.appendChild(statusEl);

        card.appendChild(editor);
        if (window.lucide) lucide.createIcons();

        // Fechar ao clicar fora
        const closeEditor = (e) => {
            if (!editor.contains(e.target)) {
                editor.remove();
                document.removeEventListener('click', closeEditor);
            }
        };
        setTimeout(() => document.addEventListener('click', closeEditor), 10);
    }

    // ── Salvar turno via API ─────────────────────────────────────────────────
    async function saveShift(userId, date, shift, editorEl, isDelete = false) {
        const statusEl = editorEl.querySelector(`[id^="editor-status"]`);
        if (statusEl) { statusEl.textContent = 'Salvando…'; statusEl.style.color = '#9ca3af'; }
        editorEl.querySelectorAll('button').forEach(b => b.disabled = true);

        try {
            const res = await fetch('./save_schedule.php', {
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
                if (statusEl) { statusEl.textContent = data.message || 'Erro ao salvar.'; statusEl.style.color = '#f87171'; }
                editorEl.querySelectorAll('button').forEach(b => b.disabled = false);
                return;
            }

            if (statusEl) { statusEl.textContent = '✓ Salvo!'; statusEl.style.color = '#4ade80'; }

            setTimeout(async () => {
                editorEl.remove();
                await loadMonth(currentYear, currentMonth, date);
            }, 400);

        } catch (err) {
            if (statusEl) { statusEl.textContent = 'Erro de conexão.'; statusEl.style.color = '#f87171'; }
            editorEl.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    // ── Inicializa com o mês atual ──────────────────────────────────────────
    const firstBtn = tabsContainer.querySelector('button');
    if (firstBtn) await selectTab(firstBtn, months[0].year, months[0].month);
})();