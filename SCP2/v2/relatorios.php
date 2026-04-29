<?php include 'includes/header.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Relatórios de Performance</h1>
        <p style="color: var(--text-muted);">Análise detalhada de produtividade e prazos.</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button id="btn-exportar" class="btn-premium" style="background: #10b981;">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button id="btn-pdf" class="btn-premium" style="background: #ef4444;">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</header>

<div class="glass-card" style="margin-bottom: 2rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; align-items: flex-end;">
        <div class="form-group">
            <label>Mês de Referência</label>
            <input type="month" id="filtro-mes" style="width: 100%;">
        </div>
        <div class="form-group">
            <label>Analisador</label>
            <select id="filtro-analisador" style="width: 100%;">
                <option value="">Todos</option>
            </select>
        </div>
        <button id="btn-filtrar" class="btn-premium" style="height: 45px;">
            <i class="fas fa-filter"></i> Gerar Relatório
        </button>
    </div>
</div>

<div id="resultado-relatorio" style="display: none;">
    <div class="stats-grid">
        <div class="glass-card">
            <div style="font-size: 0.8rem; opacity: 0.7;">TOTAL DE PROCESSOS</div>
            <div id="res-total" style="font-size: 2rem; font-weight: 800;">0</div>
        </div>
        <div class="glass-card" style="border-left: 4px solid #fbbf24;">
            <div style="font-size: 0.8rem; opacity: 0.7;">PENDENTES</div>
            <div id="res-pendentes" style="font-size: 2rem; font-weight: 800; color: #fbbf24;">0</div>
        </div>
        <div class="glass-card" style="border-left: 4px solid #34d399;">
            <div style="font-size: 0.8rem; opacity: 0.7;">PROTOCOLADOS</div>
            <div id="res-protocolados" style="font-size: 2rem; font-weight: 800; color: #34d399;">0</div>
        </div>
        <div class="glass-card" style="border-left: 4px solid #f87171;">
            <div style="font-size: 0.8rem; opacity: 0.7;">VENCIDOS</div>
            <div id="res-vencidos" style="font-size: 2rem; font-weight: 800; color: #f87171;">0</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
        <div class="glass-card" style="padding: 0;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                <h3 style="font-weight: 800;">Matriz de Produtividade</h3>
            </div>
            <div style="padding: 1rem;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Analisador</th>
                            <th>Total</th>
                            <th>Prot.</th>
                            <th>Anal.</th>
                            <th>Eficácia</th>
                        </tr>
                    </thead>
                    <tbody id="res-matrix"></tbody>
                </table>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="font-weight: 800; margin-bottom: 1.5rem;">Mix de Naturezas</h3>
            <div id="res-naturezas" style="display: flex; flex-direction: column; gap: 1.5rem;"></div>
        </div>
    </div>
</div>

<script>
    let todosDados = [];

    document.addEventListener('DOMContentLoaded', async () => {
        const resp = await fetch('../api.php?acao=listar');
        todosDados = await resp.json();

        // Preencher analisadores
        const analisadores = [...new Set(todosDados.map(p => p.analisador))].sort();
        const select = document.getElementById('filtro-analisador');
        analisadores.forEach(a => {
            if(a) {
                const opt = document.createElement('option');
                opt.value = a; opt.text = a;
                select.appendChild(opt);
            }
        });

        document.getElementById('filtro-mes').value = new Date().toISOString().slice(0, 7);
    });

    document.getElementById('btn-filtrar').onclick = () => {
        const mes = document.getElementById('filtro-mes').value;
        const anal = document.getElementById('filtro-analisador').value;
        
        const filtrados = todosDados.filter(p => {
            const dataP = p.data_protocolo || p.data_criacao;
            if(!dataP) return false;
            let match = dataP.startsWith(mes);
            if(anal) match = match && p.analisador === anal;
            return match;
        });

        document.getElementById('res-total').innerText = filtrados.length;
        document.getElementById('res-pendentes').innerText = filtrados.filter(p => p.status === 'PENDENTE').length;
        document.getElementById('res-protocolados').innerText = filtrados.filter(p => p.status === 'PROTOCOLADO').length;
        
        // Matriz de Produtividade
        const matrix = {};
        filtrados.forEach(p => {
            if(!matrix[p.analisador]) matrix[p.analisador] = { total: 0, prot: 0, anal: 0 };
            matrix[p.analisador].total++;
            if(p.status === 'PROTOCOLADO') matrix[p.analisador].prot++;
            if(p.status === 'ANALISADO') matrix[p.analisador].anal++;
        });

        const matrixBody = document.getElementById('res-matrix');
        matrixBody.innerHTML = '';
        Object.entries(matrix).sort((a,b) => b[1].total - a[1].total).forEach(([nome, s]) => {
            const eff = Math.round(((s.prot + s.anal) / s.total) * 100);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight:700;">${nome}</td>
                <td>${s.total}</td>
                <td>${s.prot}</td>
                <td>${s.anal}</td>
                <td><div style="background:rgba(255,255,255,0.1); border-radius:10px; height:6px; width:100px; margin-top:5px;"><div style="background:var(--primary); height:100%; border-radius:10px; width:${eff}%"></div></div><small>${eff}%</small></td>
            `;
            matrixBody.appendChild(tr);
        });

        // Naturezas
        const nats = {};
        filtrados.forEach(p => { nats[p.natureza] = (nats[p.natureza] || 0) + 1; });
        const natsBox = document.getElementById('res-naturezas');
        natsBox.innerHTML = '';
        Object.entries(nats).sort((a,b) => b[1] - a[1]).slice(0, 5).forEach(([label, val]) => {
            const perc = Math.round((val / filtrados.length) * 100);
            natsBox.innerHTML += `
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:5px;"><span>${label}</span><span>${val}</span></div>
                    <div style="background:rgba(255,255,255,0.1); height:4px; border-radius:2px;"><div style="background:var(--secondary); height:100%; width:${perc}%"></div></div>
                </div>
            `;
        });

        document.getElementById('resultado-relatorio').style.display = 'block';
        window.dadosFiltrados = filtrados;
    };

    // Reutilizando sua lógica de exportação (Simplificada para o padrão v2)
    document.getElementById('btn-exportar').onclick = () => {
        if(!window.dadosFiltrados) return alert('Filtre os dados primeiro!');
        const ws = XLSX.utils.json_to_sheet(window.dadosFiltrados);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Relatório");
        XLSX.writeFile(wb, "Relatorio_SCP_2_0.xlsx");
    };
</script>

<?php include 'includes/footer.php'; ?>
