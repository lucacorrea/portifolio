<?php include 'includes/header.php'; ?>

        <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Painel de Controle</h1>
                <p style="color: var(--text-muted);">Sincronização automática com TJAM ativa (Modo Demo).</p>
            </div>
            <button class="btn-premium" onclick="location.href='../cadastro.php'">
                <i class="fas fa-plus"></i> Novo Processo
            </button>
        </header>

        <!-- Estatísticas Dinâmicas -->
        <div class="stats-grid">
            <div class="glass-card">
                <div style="color: var(--primary); font-size: 1.2rem;"><i class="fas fa-folder-open"></i></div>
                <div class="stat-value" id="count-total">0</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Total de Processos</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--status-urgente); font-size: 1.2rem;"><i class="fas fa-bolt"></i></div>
                <div class="stat-value" id="count-urgentes" style="color: var(--status-urgente);">0</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Prazos Críticos (Sincronizados)</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--status-protocolado); font-size: 1.2rem;"><i class="fas fa-check-double"></i></div>
                <div class="stat-value" id="count-eficiencia" style="color: var(--status-protocolado);">0%</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Taxa de Eficiência (Mês)</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--secondary); font-size: 1.2rem;"><i class="fas fa-cloud-download-alt"></i></div>
                <div class="stat-value" id="count-novos">0</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Novas Intimações Hoje</div>
            </div>
        </div>

        <!-- Lista de Processos Modernizada -->
        <div class="glass-card" style="padding: 0;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.2rem;">Últimas Atualizações Projudi</h2>
                <div style="display: flex; gap: 1rem;">
                    <input type="text" id="busca-processo" placeholder="Buscar processo..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none;">
                </div>
            </div>
            <div style="padding: 1rem; overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Nº Processo</th>
                            <th>Tipo</th>
                            <th>Natureza</th>
                            <th>Prazo Final</th>
                            <th>Status</th>
                            <th>Sincronia</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-processos-v2">
                        <!-- JS preenche -->
                    </tbody>
                </table>
            </div>
        </div>

<script>
    async function carregarDashboard() {
        // Busca os dados da API (com suporte ao Modo Demo que configuramos)
        const resp = await fetch('../api.php?acao=listar');
        const processos = await resp.json();

        // Atualizar Contadores
        document.getElementById('count-total').innerText = processos.length;
        
        const urgentes = processos.filter(p => {
            if(!p.final_prazo) return false;
            const diff = Math.ceil((new Date(p.final_prazo) - new Date()) / 86400000);
            return diff <= 3 && p.status !== 'PROTOCOLADO';
        }).length;
        document.getElementById('count-urgentes').innerText = urgentes;

        const protocolados = processos.filter(p => p.status === 'PROTOCOLADO').length;
        const eficiencia = processos.length > 0 ? Math.round((protocolados / processos.length) * 100) : 0;
        document.getElementById('count-eficiencia').innerText = eficiencia + '%';

        const hoje = new Date().toISOString().split('T')[0];
        const novos = processos.filter(p => p.data_criacao && p.data_criacao.startsWith(hoje)).length;
        document.getElementById('count-novos').innerText = novos;

        // Preencher Tabela
        const tbody = document.getElementById('lista-processos-v2');
        tbody.innerHTML = '';
        
        processos.slice(0, 10).forEach(p => {
            const tr = document.createElement('tr');
            const corStatus = p.status === 'PROTOCOLADO' ? 'var(--status-protocolado)' : (urgentes > 0 ? 'var(--status-urgente)' : 'var(--primary)');
            
            tr.innerHTML = `
                <td style="font-weight: 700;">${p.numero}</td>
                <td style="font-size: 0.8rem; opacity: 0.8;">${p.tipo_processo || 'CIÊNCIA'}</td>
                <td style="font-size: 0.8rem; opacity: 0.8;">${p.natureza || 'ATO'}</td>
                <td style="font-weight: 600; color: ${corStatus}">${p.final_prazo ? p.final_prazo.split('-').reverse().join('/') : '---'}</td>
                <td><span style="color: ${corStatus}; background: ${corStatus}1a; padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">${p.status}</span></td>
                <td><span style="color: var(--primary); font-size: 0.75rem;"><i class="fas fa-check"></i> Sincronizado</span></td>
                <td>
                    <div class="dropdown">
                        <button class="btn-dots" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
                            <button onclick="abrirNoProjudi('${p.numero}')"><i class="fas fa-external-link-alt"></i> Ver no Projudi</button>
                            <button onclick="editarProcesso(${p.id})"><i class="fas fa-edit"></i> Editar</button>
                            <button onclick="excluirProcesso(${p.id})" style="color: #f87171;"><i class="fas fa-trash"></i> Excluir</button>
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('busca-processo').oninput = function() {
        const query = this.value.toUpperCase();
        document.querySelectorAll('#lista-processos-v2 tr').forEach(tr => {
            tr.style.display = tr.innerText.toUpperCase().includes(query) ? '' : 'none';
        });
    };

    document.addEventListener('DOMContentLoaded', carregarDashboard);
</script>

<?php include 'includes/footer.php'; ?>
