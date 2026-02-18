// js/escala-abas.js
// Gerencia abas de múltiplos meses para visualização de agenda no painel admin

(async function(){
    const wrap = document.getElementById('calendar-wrap');
    if (!wrap) return;

    // Gerar lista de meses (6 meses a partir de hoje)
    const months = [];
    const now = new Date();

    for (let i = 0; i < 6; i++) {
        const date = new Date(now.getFullYear(), now.getMonth() + i, 1);
        months.push({
            year: date.getFullYear(),
            month: date.getMonth(),
            label: date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' })
                .split(' ')
                .map((w, i) => i === 0 ? w.charAt(0).toUpperCase() + w.slice(1) : w)
                .join(' ')
        });
    }

    // Criar container de abas
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'mb-6 flex gap-2 overflow-x-auto pb-2 border-b border-white/10';
    tabsContainer.style.scrollBehavior = 'smooth';

    // Criar botões de abas
    months.forEach((m, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = idx === 0
            ? 'px-4 py-2 rounded-t-lg bg-blue-600 text-white font-semibold whitespace-nowrap transition'
            : 'px-4 py-2 rounded-t-lg bg-white/5 text-gray-300 hover:bg-white/10 font-semibold whitespace-nowrap transition';
        btn.textContent = m.label;
        btn.setAttribute('data-month', m.month);
        btn.setAttribute('data-year', m.year);
        btn.onclick = () => selecionarAba(btn, m.year, m.month);
        tabsContainer.appendChild(btn);
    });

    wrap.parentElement.insertBefore(tabsContainer, wrap);

    // Função para carregar calendário de um mês específico
    async function selecionarAba(btn, year, month) {
        // Atualizar estilos dos botões
        Array.from(tabsContainer.querySelectorAll('button')).forEach(b => {
            b.className = 'px-4 py-2 rounded-t-lg bg-white/5 text-gray-300 hover:bg-white/10 font-semibold whitespace-nowrap transition';
        });
        btn.className = 'px-4 py-2 rounded-t-lg bg-blue-600 text-white font-semibold whitespace-nowrap transition';

        // Atualizar display do mês
        const monthDisplay = document.getElementById('month-display');
        if (monthDisplay) {
            const d = new Date(year, month);
            const label = d.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
            monthDisplay.textContent = label.charAt(0).toUpperCase() + label.slice(1);
        }

        // Limpar calendário anterior
        wrap.innerHTML = '';

        // Carregar dados do mês
        const start = new Date(year, month, 1);
        const end = new Date(year, month + 1, 0);

        const fmt = d => d.toISOString().slice(0, 10);
        let apiUrl = `./get_schedule.php?start=${fmt(start)}&end=${fmt(end)}`;

        if (window.TARGET_USER_ID) {
            apiUrl += `&user_id=${window.TARGET_USER_ID}`;
        }

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const schedule = await response.json();
            const events = schedule.data || [];

            // Map events by date
            const eventsByDate = {};
            events.forEach(ev => {
                const d = ev.date;
                eventsByDate[d] = eventsByDate[d] || [];
                eventsByDate[d].push(ev);
            });

            // ✅ FERIADOS: Map holidays by date
            const holidays = schedule.holidays || [];
            const holidaysByDate = {};
            holidays.forEach(h => {
                holidaysByDate[h.date] = h; // 1 feriado por dia
            });

            // Render month (com feriados)
            const monthEl = renderMonth(start, eventsByDate, holidaysByDate);
            wrap.appendChild(monthEl);

            // Re-render Lucide icons
            if (window.lucide) {
                lucide.createIcons();
            }

        } catch (err) {
            console.error("[ERRO] Falha ao buscar agenda:", err);
            wrap.innerHTML = `<div class="p-4 text-red-400">Erro ao carregar escala: ${err.message}</div>`;
        }
    }

    // Render month function (similar to escala.js)
    function renderMonth(date, eventsMap, holidaysMap) {
        const el = document.createElement('div');
        el.className = 'w-full bg-brand-dark border border-white/10 rounded-lg p-2 md:p-6';

        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'pb-2';
        tableWrapper.style.width = '100%';
        tableWrapper.style.display = 'block';
        tableWrapper.style.overflowX = 'auto';
        tableWrapper.style.webkitOverflowScrolling = 'touch';

        const table = document.createElement('table');
        table.className = 'border-collapse text-[11px] md:text-base w-full';
        table.style.minWidth = '700px';

        // Header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'bg-white/5 border-b border-white/10';
        ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'].forEach(d => {
            const th = document.createElement('th');
            th.className = 'px-2 md:px-4 py-2 md:py-3 text-center text-[8px] md:text-xs font-semibold text-gray-400 uppercase';
            th.textContent = d;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Body
        const tbody = document.createElement('tbody');
        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1).getDay();
        const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();

        let currentCell = 0;
        let week = document.createElement('tr');
        week.className = 'border-b border-white/10';

        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5';
            week.appendChild(td);
            currentCell++;
        }

        // Days
        const fmt = d => d.toISOString().slice(0, 10);

        for (let d = 1; d <= daysInMonth; d++) {
            const cur = new Date(date.getFullYear(), date.getMonth(), d);
            const iso = fmt(cur);

            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5 hover:bg-white/10 transition align-top cursor-pointer';
            td.style.cursor = 'pointer';

            // Day number
            const dayNum = document.createElement('div');
            dayNum.className = 'text-[10px] md:text-xs text-gray-300 font-semibold mb-1 md:mb-2';
            dayNum.textContent = d;
            td.appendChild(dayNum);

            // ✅ FERIADO (camada separada)
            if (holidaysMap && holidaysMap[iso]) {
                const holidayLabel = document.createElement('div');
                holidayLabel.textContent = 'FERIADO';
                holidayLabel.className = 'text-[9px] md:text-[10px] font-bold mb-1';
                holidayLabel.style.color = '#ef4444';
                holidayLabel.title = (holidaysMap[iso].name || 'Feriado');
                td.appendChild(holidayLabel);
            }

            // Events
            const evs = eventsMap[iso] || [];
            const list = document.createElement('div');
            list.className = 'flex flex-col gap-1 md:gap-1 text-[8px] md:text-[10px]';

            if (evs.length > 0) {
                evs.slice(0, 2).forEach(e => {
                    const badge = document.createElement('div');

                    let bgColor = '#4b5563';
                    const s = (e.shift || '').toUpperCase();

                    if (s.includes('AGENDA')) {
                        bgColor = '#16a34a';
                    } else if (s.includes('FOLGA')) {
                        bgColor = '#60a5fa';
                    } else if (s.includes('FÉRIAS') || s.includes('FERIAS') || s.includes('AUSENTE')) {
                        bgColor = '#f97316';
                    }

                    badge.style.backgroundColor = bgColor;
                    badge.className = 'text-white px-1 md:px-2 py-0.5 md:py-1 rounded truncate';

                    const label = s || (e.note || '').toString().slice(0, 15);
                    badge.textContent = label;
                    list.appendChild(badge);
                });

                if (evs.length > 2) {
                    const more = document.createElement('div');
                    more.className = 'text-gray-400 text-[9px]';
                    more.textContent = '+' + (evs.length - 2);
                    list.appendChild(more);
                }
            }

            td.appendChild(list);

            // Evento de clique para mostrar todos os técnicos do dia
            td.onclick = async (e) => {
                e.stopPropagation();
                await mostrarDiaDetalhado(iso, d);
            };

            week.appendChild(td);
            currentCell++;

            if (currentCell % 7 === 0) {
                tbody.appendChild(week);
                week = document.createElement('tr');
                week.className = 'border-b border-white/10';
            }
        }

        // Fill remaining cells
        while (currentCell % 7 !== 0) {
            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5';
            week.appendChild(td);
            currentCell++;
        }

        if (currentCell % 7 === 0) {
            tbody.appendChild(week);
        }

        table.appendChild(tbody);
        tableWrapper.appendChild(table);
        el.appendChild(tableWrapper);
        return el;
    }

    // Função para mostrar detalhes do dia (todos os técnicos)
    async function mostrarDiaDetalhado(dateStr, dayOfMonth) {
        // Fechar se já estava aberta
        const existingDetail = document.getElementById('day-detail-container');
        if (existingDetail) {
            existingDetail.remove();
        }

        // Criar container
        const container = document.createElement('div');
        container.id = 'day-detail-container';
        container.className = 'mt-8 p-6 bg-brand-dark border border-white/10 rounded-lg';

        // Título (placeholder; será refeito depois do fetch)
        const title = document.createElement('h3');
        title.className = 'text-xl font-bold text-white mb-4 flex items-center justify-between';
        title.innerHTML = `
            <span>Escala de Todos os Técnicos - ${dayOfMonth}/${dateStr.split('-')[1]}/${dateStr.split('-')[0]}</span>
            <button onclick="document.getElementById('day-detail-container').remove()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;">✕</button>
        `;
        container.appendChild(title);

        // Loading
        container.innerHTML += '<p class="text-gray-400">Carregando...</p>';
        wrap.parentElement.appendChild(container);

        try {
            // Buscar dados de todos os técnicos para esse dia
            const apiUrl = `./get_schedule.php?start=${dateStr}&end=${dateStr}`;
            const response = await fetch(apiUrl);

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            const eventos = data.data || [];
            const holidays = data.holidays || [];

            // Verifica se esse dia é feriado (opcional: mostrar no detalhe)
            let holidayText = '';
            if (holidays.length > 0 && holidays[0]?.date === dateStr) {
                const name = holidays[0]?.name ? ` - ${holidays[0].name}` : '';
                holidayText = `<span style="color:#ef4444;font-weight:700;margin-left:10px;">FERIADO${name}</span>`;
            }

            // Limpar loading + refazer header
            container.innerHTML = `
                <h3 class="text-xl font-bold text-white mb-4 flex items-center justify-between">
                    <span>
                        Escala de Todos os Técnicos - ${dayOfMonth}/${dateStr.split('-')[1]}/${dateStr.split('-')[0]}
                        ${holidayText}
                    </span>
                    <button onclick="document.getElementById('day-detail-container').remove()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;">✕</button>
                </h3>
            `;

            // Criar tabela
            const table = document.createElement('table');
            table.className = 'w-full border-collapse text-sm';
            table.style.width = '100%';

            // Header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            headerRow.className = 'bg-white/5 border-b border-white/10';

            ['Técnico', 'Status', 'Turno/Observação'].forEach(header => {
                const th = document.createElement('th');
                th.className = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase';
                th.textContent = header;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);

            // Body
            const tbody = document.createElement('tbody');
            tbody.className = 'divide-y divide-white/10';

            if (eventos.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="3" class="px-4 py-3 text-center text-gray-400">Nenhum técnico cadastrado para este dia</td>';
                tbody.appendChild(tr);
            } else {
                // Agrupar por técnico
                const porTecnico = {};
                eventos.forEach(ev => {
                    if (!porTecnico[ev.username]) {
                        porTecnico[ev.username] = [];
                    }
                    porTecnico[ev.username].push(ev);
                });

                // Montar linhas
                Object.keys(porTecnico).sort().forEach(username => {
                    const evs = porTecnico[username];
                    const ev = evs[0]; // Pega primeiro (deve ser apenas 1 por dia)

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-white/5 transition';

                    // Técnico
                    const tdTecnico = document.createElement('td');
                    tdTecnico.className = 'px-4 py-3 text-white font-medium';
                    tdTecnico.textContent = username;
                    tr.appendChild(tdTecnico);

                    // Status (cor)
                    const tdStatus = document.createElement('td');
                    tdStatus.className = 'px-4 py-3';
                    const statusBadge = document.createElement('span');

                    let bgColor = '#4b5563';
                    let textColor = '#d1d5db';
                    const shift = (ev.shift || '').toUpperCase();

                    if (shift.includes('AGENDA')) {
                        bgColor = '#16a34a';
                        textColor = '#dcfce7';
                    } else if (shift.includes('FOLGA')) {
                        bgColor = '#2563eb';
                        textColor = '#dbeafe';
                    } else if (shift.includes('FÉRIAS') || shift.includes('FERIAS') || shift.includes('AUSENTE')) {
                        bgColor = '#ea580c';
                        textColor = '#fed7aa';
                    }

                    statusBadge.style.backgroundColor = bgColor;
                    statusBadge.style.color = textColor;
                    statusBadge.className = 'px-3 py-1 rounded text-xs font-semibold inline-block';
                    statusBadge.textContent = shift || 'Sem info';
                    tdStatus.appendChild(statusBadge);
                    tr.appendChild(tdStatus);

                    // Observação
                    const tdNote = document.createElement('td');
                    tdNote.className = 'px-4 py-3 text-gray-300 text-sm';
                    tdNote.textContent = ev.note || '-';
                    tr.appendChild(tdNote);

                    tbody.appendChild(tr);
                });
            }

            table.appendChild(tbody);
            container.appendChild(table);

        } catch (err) {
            console.error('Erro ao buscar dia:', err);
            container.innerHTML = `<p class="text-red-400">Erro ao carregar dados: ${err.message}</p>`;
        }
    }

    // Carregar primeiro mês automaticamente
    const firstMonth = months[0];
    const firstBtn = tabsContainer.querySelector('button:first-child');
    if (firstBtn) {
        await selecionarAba(firstBtn, firstMonth.year, firstMonth.month);
    }

})();
