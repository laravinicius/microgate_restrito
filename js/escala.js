// js/escala.js
// Mobile-first calendar for 1 or 2 months (mês atual; e a partir do dia 15 libera também o próximo mês)

(async function(){
    const wrap = document.getElementById('calendar-wrap');
    if (!wrap) return;

    // --- CONFIGURAÇÃO DO MÊS ALVO ---
    // Usa automaticamente o mês atual do sistema
    const now = new Date();
    const targetYear = now.getFullYear();
    const targetMonth = now.getMonth(); // 0 = Jan, 1 = Fev, 2 = Mar, etc.

    const allowNextMonth = now.getDate() >= 15;

    // Range de busca
    const start = new Date(targetYear, targetMonth, 1);
    const end = allowNextMonth
        ? new Date(targetYear, targetMonth + 2, 0) // último dia do próximo mês
        : new Date(targetYear, targetMonth + 1, 0); // último dia do mês atual

    const fmt = d => d.toISOString().slice(0,10);

    // --- CORREÇÃO DE CAMINHO (LINUX/SSH) ---
    // Usa './' para garantir que busque na pasta do projeto, e não na raiz do servidor.
    let apiUrl = `./get_schedule.php?start=${fmt(start)}&end=${fmt(end)}`;

    // Verifica se estamos no modo "Visualizador" (escala_viewer.php)
    // A variável window.TARGET_USER_ID é definida no PHP do viewer.
    if (window.TARGET_USER_ID) {
        apiUrl += `&user_id=${window.TARGET_USER_ID}`;
        console.log(`[DEBUG] Modo Admin: Buscando agenda do usuário ID ${window.TARGET_USER_ID}`);
    } else {
        console.log(`[DEBUG] Modo Padrão: Buscando agenda do usuário logado (Sessão)`);
    }

    console.log(`[DEBUG] URL da API: ${apiUrl}`);

    const schedule = await fetch(apiUrl)
        .then(async r => {
            if (!r.ok) {
                const text = await r.text();
                throw new Error(`Erro HTTP ${r.status}: ${text}`);
            }
            return r.json();
        })
        .catch(err => {
            console.error("[ERRO] Falha ao buscar agenda:", err);
            wrap.innerHTML = `<div class="p-4 text-red-400">Erro de conexão: ${err.message}</div>`;
            return { data: [], holidays: [] };
        });

    const events = schedule.data || [];
    console.log(`[DEBUG] Eventos encontrados: ${events.length}`);

    // Map date -> array of events
    const eventsByDate = {};
    events.forEach(ev => {
        const d = ev.date;
        eventsByDate[d] = eventsByDate[d] || [];
        eventsByDate[d].push(ev);
    });

    // Feriados (camada separada da escala)
    const holidays = schedule.holidays || [];
    const holidaysByDate = {};
    holidays.forEach(h => {
        holidaysByDate[h.date] = h; // um feriado por dia (se precisar múltiplos, troque para array)
    });

    // Render: mês atual
    wrap.appendChild(renderMonth(new Date(targetYear, targetMonth, 1), eventsByDate, holidaysByDate));

    // Render: próximo mês (somente se >= 15)
    if (allowNextMonth) {
        wrap.appendChild(renderMonth(new Date(targetYear, targetMonth + 1, 1), eventsByDate, holidaysByDate));
    }

    // Select default date: hoje se estiver dentro do range exibido; senão primeiro dia do mês atual
    const today = new Date();
    const inRange = (today >= start) && (today <= end);
    const defaultDate = inRange ? today : start;
    selectDate(fmt(defaultDate));

    // Render month function
    function renderMonth(date, eventsMap, holidaysMap){
        const monthName = date.toLocaleString('pt-BR', { month:'long', year:'numeric' });

        // CONTAINER DO CARD
        const el = document.createElement('div');
        el.className = 'w-full bg-brand-dark border border-white/10 rounded-lg p-2 md:p-6';

        // TÍTULO DO MÊS
        const hdr = document.createElement('div');
        hdr.className = 'mb-4 px-2 md:px-0';
        hdr.innerHTML = `<h3 class="text-white font-bold text-lg md:text-2xl">${capitalize(monthName)}</h3>`;
        el.appendChild(hdr);

        // WRAPPER DE ROLAGEM
        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'pb-2';

        // Estilos para forçar a rolagem interna
        tableWrapper.style.width = '100%';
        tableWrapper.style.display = 'block';
        tableWrapper.style.overflowX = 'auto';
        tableWrapper.style.webkitOverflowScrolling = 'touch';

        // TABELA
        const table = document.createElement('table');
        table.className = 'border-collapse text-[11px] md:text-base w-full';
        table.style.minWidth = '700px';

        // Header row
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.className = 'bg-white/5 border-b border-white/10';

        ['Dom','Seg','Ter','Qua','Qui','Sex','Sab'].forEach(d=>{
            const th = document.createElement('th');
            th.className = 'px-2 md:px-4 py-2 md:py-3 text-center text-[8px] md:text-xs font-semibold text-gray-400 uppercase';
            th.textContent = d;
            headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Body rows
        const tbody = document.createElement('tbody');
        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1).getDay();
        const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();

        let currentCell = 0;
        let week = document.createElement('tr');
        week.className = 'border-b border-white/10';

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++){
            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5';
            week.appendChild(td);
            currentCell++;
        }

        // Days of month
        for (let d = 1; d <= daysInMonth; d++){
            const cur = new Date(date.getFullYear(), date.getMonth(), d);
            const iso = fmt(cur);

            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5 hover:bg-white/5 transition cursor-pointer align-top';
            td.setAttribute('data-date', iso);

            // Day number
            const dayNum = document.createElement('div');
            dayNum.className = 'text-sm md:text-lg text-gray-300 font-bold mb-1 md:mb-2';
            dayNum.textContent = d;
            td.appendChild(dayNum);

            // Feriado (camada separada)
            if (holidaysMap && holidaysMap[iso]) {
                const holidayLabel = document.createElement('div');
                holidayLabel.textContent = 'FERIADO';
                holidayLabel.className = 'text-[9px] md:text-[10px] font-bold mb-1';
                holidayLabel.style.color = '#ef4444';
                holidayLabel.title = (holidaysMap[iso].name || 'Feriado');
                td.appendChild(holidayLabel);
            }

            // Events list
            const evs = eventsMap[iso] || [];
            const list = document.createElement('div');
            list.className = 'flex flex-col gap-1 md:gap-1 text-[8px] md:text-[10px]';

            if (evs.length > 0){
                evs.slice(0,2).forEach(e=>{
                    const badge = document.createElement('div');

                    // --- CORES VIA HEX (Para garantir que funcionem sem build) ---
                    let bgColor = '#4b5563'; // Cinza (Padrão)
                    const s = (e.shift || '').toUpperCase();

                    if (s.includes('AGENDA')) {
                        bgColor = '#16a34a'; // Verde
                    } else if (s.includes('FOLGA')) {
                        bgColor = '#60a5fa'; // Azul Claro
                    } else if (s.includes('FÉRIAS') || s.includes('FERIAS') || s.includes('AUSENTE')) {
                        bgColor = '#f97316'; // Laranja
                    }

                    badge.style.backgroundColor = bgColor;
                    badge.className = `text-white px-1 md:px-2 py-0.5 md:py-1 rounded truncate`;

                    const label = s || (e.note || '').toString().slice(0,15);
                    badge.textContent = label;
                    list.appendChild(badge);
                });

                if (evs.length > 2){
                    const more = document.createElement('div');
                    more.className = 'text-gray-400 text-[9px]';
                    more.textContent = '+' + (evs.length - 2);
                    list.appendChild(more);
                }
            }

            td.appendChild(list);
            td.addEventListener('click', ()=> selectDate(iso));
            week.appendChild(td);
            currentCell++;

            if (currentCell % 7 === 0){
                tbody.appendChild(week);
                week = document.createElement('tr');
                week.className = 'border-b border-white/10';
            }
        }

        // Fill remaining cells
        while (currentCell % 7 !== 0){
            const td = document.createElement('td');
            td.className = 'h-24 md:h-32 p-2 md:p-3 bg-white/2 border border-white/5';
            week.appendChild(td);
            currentCell++;
        }

        if (currentCell % 7 === 0){
            tbody.appendChild(week);
        }

        table.appendChild(tbody);
        tableWrapper.appendChild(table);
        el.appendChild(tableWrapper);
        return el;
    }

    async function selectDate(iso){
        document.querySelectorAll('[data-date]').forEach(el=>{
            if (el.getAttribute('data-date') === iso) {
                el.classList.add('ring-2','ring-gray-500','bg-gray-500/10');
            } else {
                el.classList.remove('ring-2','ring-gray-500','bg-gray-500/10');
            }
        });
    }

    function capitalize(s){
        return s.charAt(0).toUpperCase() + s.slice(1);
    }
})();
