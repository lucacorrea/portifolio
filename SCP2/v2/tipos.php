<?php include 'includes/header.php'; ?>

<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Tipos de Processos</h1>
        <p style="color: var(--text-muted);">Organização e filtros por categoria de atuação.</p>
    </div>
    
    <div style="display: flex; gap: 0.5rem; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 12px; border: 1px solid var(--border);">
        <button class="btn-premium active" onclick="switchTab('ciencia')" id="tab-ciencia" style="padding: 8px 15px; font-size: 0.8rem;">
            Ciência
        </button>
        <button class="btn-premium" onclick="switchTab('cumprimento')" id="tab-cumprimento" style="padding: 8px 15px; font-size: 0.8rem; background:transparent;">
            Cumprimento
        </button>
        <button class="btn-premium" onclick="switchTab('recurso-ciencia')" id="tab-recurso-ciencia" style="padding: 8px 15px; font-size: 0.8rem; background:transparent;">
            Recurso Ciência
        </button>
    </div>
</header>

<div class="glass-card" style="padding: 0;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 id="section-title" style="font-size: 1.2rem;">Processos em Ciência</h2>
        <div style="display: flex; gap: 1rem;">
            <input type="text" id="filtro-tipos" placeholder="Pesquisar..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none; width: 250px;">
        </div>
    </div>
    <div style="padding: 1rem; overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Processo</th>
                    <th>Ato / Natureza</th>
                    <th>Prazo Final</th>
                    <th>Analisador</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="lista-tipos-v2">
                <!-- Dinâmico -->
            </tbody>
        </table>
    </div>
    <div id="paginacao-tipos" style="padding: 1rem; display: flex; justify-content: center; gap: 5px;"></div>
</div>

<script>
    let todosProcessos = [];
    let activeTab = 'CIÊNCIA';
    let paginaAtual = 1;
    const itensPorPagina = 10;

    async function carregarProcessos() {
        const resp = await fetch('../api.php?acao=listar');
        todosProcessos = await resp.json();
        renderizar();
    }

    window.switchTab = (tab) => {
        const tabs = {
            'ciencia': 'CIÊNCIA',
            'cumprimento': 'CUMPRIMENTO',
            'recurso-ciencia': 'RECURSO - CIÊNCIA'
        };
        activeTab = tabs[tab];
        
        document.querySelectorAll('[id^="tab-"]').forEach(b => b.style.background = 'transparent');
        document.getElementById('tab-' + tab).style.background = 'var(--primary)';
        document.getElementById('section-title').innerText = 'Processos em ' + activeTab;
        
        paginaAtual = 1;
        renderizar();
    };

    function renderizar() {
        const tbody = document.getElementById('lista-tipos-v2');
        const search = document.getElementById('filtro-tipos').value.toUpperCase();
        
        let filtrados = todosProcessos.filter(p => 
            (p.tipo_processo || 'CIÊNCIA') === activeTab && 
            (p.numero.includes(search) || p.tipo_ato.toUpperCase().includes(search))
        );

        const inicio = (paginaAtual - 1) * itensPorPagina;
        const paginados = filtrados.slice(inicio, inicio + itensPorPagina);

        tbody.innerHTML = '';
        paginados.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight:600;">${p.numero}</td>
                <td>
                    <div style="font-size:0.7rem; background:rgba(255,255,255,0.1); padding:2px 8px; border-radius:4px; margin-bottom:4px;">${p.tipo_ato}</div>
                    <div style="font-size:0.6rem; opacity:0.6;">${p.natureza}</div>
                </td>
                <td>${p.final_prazo ? p.final_prazo.split('-').reverse().join('/') : 'N/A'}</td>
                <td><span style="font-size:0.75rem;">${p.analisador}</span></td>
                <td><span class="badge-v2 status-${p.status.toLowerCase().replace(/ /g, '-')}">${p.status}</span></td>
                <td><i class="fas fa-edit" style="cursor:pointer; color:var(--primary);"></i></td>
            `;
            tbody.appendChild(tr);
        });
        
        document.getElementById('paginacao-tipos').innerHTML = `<small style="opacity:0.5">Mostrando ${paginados.length} de ${filtrados.length} registros</small>`;
    }

    document.getElementById('filtro-tipos').oninput = () => { paginaAtual = 1; renderizar(); };
    document.addEventListener('DOMContentLoaded', carregarProcessos);
</script>

<style>
    .badge-v2 {
        padding: 4px 10px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .status-pendente { background: var(--status-pendente); color: white; }
    .status-protocolado { background: var(--status-protocolado); color: black; }
    .status-analisado { background: var(--status-analisado); color: black; }
    .status-finalizado { background: var(--status-finalizado); color: white; }
    .status-em-elaboração { background: var(--status-em-elaboracao); color: black; }
    .status-sendo-avaliado { background: var(--status-sendo-avaliado); color: black; }
</style>

<?php include 'includes/footer.php'; ?>
