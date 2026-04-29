<?php include 'includes/header.php'; ?>

<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 id="prazos-titulo" style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Prazos Urgentes</h1>
        <p id="prazos-descricao" style="color: var(--text-muted);">Processos com vencimento em até 72 horas.</p>
    </div>
    
    <div style="display: flex; gap: 0.5rem; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 12px; border: 1px solid var(--border);">
        <button class="btn-premium active" onclick="switchTab('urgentes')" id="tab-urgentes" style="padding: 8px 15px; font-size: 0.8rem;">
            <i class="fas fa-exclamation-triangle"></i> Urgentes
        </button>
        <button class="btn-premium" onclick="switchTab('vencidos')" id="tab-vencidos" style="padding: 8px 15px; font-size: 0.8rem; background:transparent;">
            <i class="fas fa-calendar-times"></i> Vencidos
        </button>
        <button class="btn-premium" onclick="switchTab('mensal')" id="tab-mensal" style="padding: 8px 15px; font-size: 0.8rem; background:transparent;">
            <i class="fas fa-calendar-alt"></i> Mensal
        </button>
    </div>
</header>

<div id="month-grid" style="display:none; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <!-- JS preenche -->
</div>

<div class="glass-card" style="padding: 0;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 id="section-title" style="font-size: 1.2rem;">Lista de Prazos</h2>
        <div style="display: flex; gap: 1rem;">
            <select id="filtro-tipo-prazos" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none;">
                <option value="">Todos os Tipos</option>
                <option value="CIÊNCIA">CIÊNCIA</option>
                <option value="CUMPRIMENTO">CUMPRIMENTO</option>
            </select>
            <input type="text" id="filtro-urgentes" placeholder="Pesquisar..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none; width: 250px;">
        </div>
    </div>
    <div style="padding: 1rem; overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Processo</th>
                    <th>Tipo</th>
                    <th>Ato / Natureza</th>
                    <th>Prazo Final</th>
                    <th>Dias</th>
                    <th>Analisador</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="lista-prazos-v2">
                <!-- Dinâmico -->
            </tbody>
        </table>
    </div>
    <div id="paginacao-prazos" style="padding: 1rem; display: flex; justify-content: center; gap: 5px;"></div>
</div>

<script>
    let dadosOriginais = [];
    let activeTab = 'urgentes';
    let paginaAtual = 1;
    const itensPorPagina = 10;
    let selectedMonth = new Date().getMonth();
    let selectedYear = new Date().getFullYear();

    async function carregarPrazos() {
        const resp = await fetch('../api.php?acao=listar');
        dadosOriginais = await resp.json();
        processarDados();
    }

    window.switchTab = (tab) => {
        activeTab = tab;
        document.querySelectorAll('[id^="tab-"]').forEach(b => b.style.background = 'transparent');
        document.getElementById('tab-' + tab).style.background = 'var(--primary)';
        
        const grid = document.getElementById('month-grid');
        const titulo = document.getElementById('prazos-titulo');
        if(tab === 'mensal') {
            grid.style.display = 'grid';
            titulo.innerText = 'Cronograma Mensal';
        } else {
            grid.style.display = 'none';
            titulo.innerText = tab === 'urgentes' ? 'Prazos Urgentes' : 'Prazos Vencidos';
        }
        paginaAtual = 1;
        processarDados();
    };

    function processarDados() {
        const hoje = new Date();
        hoje.setHours(0,0,0,0);
        const hojeStr = hoje.toISOString().split('T')[0];

        let filtrados = dadosOriginais;

        if (activeTab === 'urgentes') {
            filtrados = filtrados.filter(p => {
                if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO' || !p.final_prazo) return false;
                const d = new Date(p.final_prazo + 'T12:00:00');
                const diffDays = Math.ceil((d - hoje) / (86400000));
                return p.final_prazo >= hojeStr && diffDays <= 7;
            }).sort((a,b) => a.final_prazo.localeCompare(b.final_prazo));
        } else if (activeTab === 'vencidos') {
            filtrados = filtrados.filter(p => {
                if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO' || !p.final_prazo) return false;
                return p.final_prazo < hojeStr;
            }).sort((a,b) => b.final_prazo.localeCompare(a.final_prazo));
        } else {
            filtrados = filtrados.filter(p => {
                if(!p.final_prazo) return false;
                const d = new Date(p.final_prazo + 'T12:00:00');
                return d.getMonth() === selectedMonth && d.getFullYear() === selectedYear && p.final_prazo >= hojeStr;
            }).sort((a,b) => a.final_prazo.localeCompare(b.final_prazo));
            renderizarGridMeses();
        }

        renderizarTabela(filtrados);
    }

    function renderizarGridMeses() {
        const grid = document.getElementById('month-grid');
        const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        grid.innerHTML = '';
        meses.forEach((m, i) => {
            const count = dadosOriginais.filter(p => {
                if(!p.final_prazo) return false;
                const d = new Date(p.final_prazo + 'T12:00:00');
                return d.getMonth() === i && d.getFullYear() === selectedYear && p.final_prazo >= new Date().toISOString().split('T')[0];
            }).length;
            
            const card = document.createElement('div');
            card.className = 'glass-card';
            card.style.cssText = `padding:1rem; text-align:center; cursor:pointer; border-color: ${i === selectedMonth ? 'var(--primary)' : 'var(--border)'}`;
            card.onclick = () => { selectedMonth = i; processarDados(); };
            card.innerHTML = `<div style="font-size:0.8rem; opacity:0.7">${m}</div><div style="font-size:1.2rem; font-weight:800">${count}</div>`;
            grid.appendChild(card);
        });
    }

    function renderizarTabela(dados) {
        const tbody = document.getElementById('lista-prazos-v2');
        const search = document.getElementById('filtro-urgentes').value.toUpperCase();
        const tipo = document.getElementById('filtro-tipo-prazos').value;

        let filtrados = dados.filter(p => 
            (!tipo || p.tipo_processo === tipo) && 
            (p.numero.includes(search) || p.tipo_ato.toUpperCase().includes(search))
        );

        const inicio = (paginaAtual - 1) * itensPorPagina;
        const paginados = filtrados.slice(inicio, inicio + itensPorPagina);

        tbody.innerHTML = '';
        paginados.forEach(p => {
            const d = new Date(p.final_prazo + 'T12:00:00');
            const diff = Math.ceil((d - new Date().setHours(0,0,0,0)) / 86400000);
            const cor = diff < 0 ? '#f87171' : (diff <= 2 ? '#fbbf24' : '#38bdf8');

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight:600;">${p.numero}</td>
                <td style="color:var(--primary); font-weight:700;">${p.tipo_processo || 'CIÊNCIA'}</td>
                <td>
                    <div style="font-size:0.7rem; background:rgba(255,255,255,0.1); padding:2px 8px; border-radius:4px; margin-bottom:4px;">${p.tipo_ato}</div>
                    <div style="font-size:0.6rem; opacity:0.6;">${p.natureza}</div>
                </td>
                <td style="color:${cor}; font-weight:800;">${p.final_prazo.split('-').reverse().join('/')}</td>
                <td style="color:${cor}; font-weight:700;">${diff < 0 ? 'VENCIDO' : diff + 'd'}</td>
                <td><span style="font-size:0.75rem;">${p.analisador}</span></td>
                <td><span style="background:${cor}33; color:${cor}; padding:4px 10px; border-radius:50px; font-size:0.7rem; font-weight:800;">${p.status}</span></td>
                <td><i class="fas fa-eye" style="cursor:pointer; color:var(--primary);"></i></td>
            `;
            tbody.appendChild(tr);
        });

        // Paginação simplificada para o demo
        document.getElementById('paginacao-prazos').innerHTML = `<small style="opacity:0.5">Mostrando ${paginados.length} de ${filtrados.length} registros</small>`;
    }

    document.getElementById('filtro-urgentes').oninput = () => { paginaAtual = 1; processarDados(); };
    document.getElementById('filtro-tipo-prazos').onchange = () => { paginaAtual = 1; processarDados(); };

    document.addEventListener('DOMContentLoaded', carregarPrazos);
</script>

<?php include 'includes/footer.php'; ?>
