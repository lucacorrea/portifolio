/**
 * app.js - Motor da Interface SCP 2.0
 */

document.addEventListener('DOMContentLoaded', () => {
    carregarProcessosV2();
    
    // Simular status de sincronização
    setTimeout(() => {
        const syncBadge = document.getElementById('sync-status');
        syncBadge.innerHTML = '<i class="fas fa-check-circle"></i> Sincronizado com Projudi';
        syncBadge.style.animation = 'none';
        syncBadge.style.background = 'rgba(52, 211, 153, 0.2)';
    }, 3000);
});

async function carregarProcessosV2() {
    const tbody = document.getElementById('lista-processos-v2');
    
    try {
        const response = await fetch('../api.php?acao=listar');
        const processos = await response.json();
        
        tbody.innerHTML = '';
        
        processos.forEach(proc => {
            const tr = document.createElement('tr');
            
            // Lógica de Status Colorido
            const statusClass = getStatusClass(proc.status);
            const syncTime = proc.last_sync ? formatarTempoAtras(proc.last_sync) : 'Nunca';

            tr.innerHTML = `
                <td style="font-weight: 700;">${proc.numero}</td>
                <td>TJAM - 1º Grau</td>
                <td>${proc.magistrado || '<span style="color:var(--text-muted)">Não inf.</span>'}</td>
                <td style="font-size:0.85rem">${proc.tipo_ato || 'Sem ato'}</td>
                <td><span style="${statusClass}">${proc.status}</span></td>
                <td><span style="color: var(--primary); font-size: 0.75rem;"><i class="fas fa-history"></i> ${syncTime}</span></td>
                <td>
                    <div style="display:flex; gap:10px;">
                        <i class="fas fa-eye" title="Ver Detalhes" style="cursor: pointer; color: var(--primary);"></i>
                        <i class="fas fa-sync" title="Sincronizar Agora" style="cursor: pointer; color: var(--secondary);"></i>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Erro ao carregar processos v2:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">Erro ao carregar dados do servidor.</td></tr>';
    }
}

function getStatusClass(status) {
    if (status === 'PROTOCOLADO' || status === 'ANALISADO') {
        return 'color: var(--status-protocolado); background: rgba(52,211,153,0.1); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem;';
    }
    if (status === 'PENDENTE' || status === 'URGENTE') {
        return 'color: var(--status-urgente); background: rgba(248,113,113,0.1); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem;';
    }
    return 'color: var(--status-pendente); background: rgba(251,191,36,0.1); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem;';
}

function formatarTempoAtras(data) {
    const agora = new Date();
    const dataSync = new Date(data);
    const diff = Math.floor((agora - dataSync) / 1000 / 60); // em minutos
    
    if (diff < 1) return 'Agora mesmo';
    if (diff < 60) return `${diff} min atrás`;
    const horas = Math.floor(diff / 60);
    if (horas < 24) return `${horas}h atrás`;
    return dataSync.toLocaleDateString();
}
