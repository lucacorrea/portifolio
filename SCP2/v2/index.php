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
                <div class="stat-value">1,284</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Total de Processos</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--status-urgente); font-size: 1.2rem;"><i class="fas fa-bolt"></i></div>
                <div class="stat-value" style="color: var(--status-urgente);">12</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Prazos Críticos (Sincronizados)</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--status-protocolado); font-size: 1.2rem;"><i class="fas fa-check-double"></i></div>
                <div class="stat-value" style="color: var(--status-protocolado);">85%</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Taxa de Eficiência (Mês)</div>
            </div>
            <div class="glass-card">
                <div style="color: var(--secondary); font-size: 1.2rem;"><i class="fas fa-cloud-download-alt"></i></div>
                <div class="stat-value">42</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Novas Intimações Hoje</div>
            </div>
        </div>

        <!-- Lista de Processos Modernizada -->
        <div class="glass-card" style="padding: 0;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.2rem;">Últimas Atualizações Projudi</h2>
                <div style="display: flex; gap: 1rem;">
                    <input type="text" placeholder="Buscar processo..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none;">
                    <button class="btn-premium" style="padding: 0.5rem 1rem; background: var(--border);"><i class="fas fa-filter"></i></button>
                </div>
            </div>
            <div style="padding: 1rem; overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Nº Processo</th>
                            <th>Tribunal</th>
                            <th>Magistrado</th>
                            <th>Última Movimentação</th>
                            <th>Status</th>
                            <th>Sincronia</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-processos-v2">
                        <!-- Exemplo de linha -->
                        <tr>
                            <td style="font-weight: 700;">0001234-56.2024.8.04.0001</td>
                            <td>TJAM - 1º Grau</td>
                            <td>Dr. João Silva</td>
                            <td>Expedição de Intimação</td>
                            <td><span style="color: var(--status-urgente); background: rgba(248,113,113,0.1); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem;">URGENTE</span></td>
                            <td><span style="color: var(--primary); font-size: 0.75rem;"><i class="fas fa-check"></i> 5 min atrás</span></td>
                            <td><i class="fas fa-eye" style="cursor: pointer; color: var(--primary);"></i></td>
                        </tr>
                        <!-- Fim exemplo -->
                    </tbody>
                </table>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>
