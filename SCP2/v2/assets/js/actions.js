// Motor de Ações SCP 2.0
window.toggleDropdown = (btn) => {
    // Fecha outros dropdowns abertos
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('active'));
    
    const menu = btn.nextElementSibling;
    menu.classList.toggle('active');
    
    // Fechar ao clicar fora
    document.addEventListener('click', function closeMenu(e) {
        if (!btn.contains(e.target)) {
            menu.classList.remove('active');
            document.removeEventListener('click', closeMenu);
        }
    });
};

window.excluirProcesso = async (id) => {
    const result = await Swal.fire({
        title: 'Tem certeza?',
        text: "Esta ação não poderá ser desfeita!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f87171',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
        background: '#1e293b',
        color: '#fff'
    });

    if (result.isConfirmed) {
        try {
            const resp = await fetch(`../api.php?acao=excluir&id=${id}`, { method: 'DELETE' });
            const res = await resp.json();
            if (res.status === 'sucesso') {
                Swal.fire({ title: 'Excluído!', icon: 'success', background: '#1e293b', color: '#fff' });
                if (typeof renderizar === 'function') renderizar(); // Recarrega a lista na página atual
                else location.reload();
            }
        } catch (e) {
            Swal.fire({ title: 'Erro!', text: 'Não foi possível excluir.', icon: 'error', background: '#1e293b', color: '#fff' });
        }
    }
};

window.abrirNoProjudi = (numero) => {
    const limpo = numero.replace(/[^0-9]/g, '');
    window.open(`https://projudi.tjam.jus.br/projudi/listagemPublicaProcessos.do?actionType=pesquisar&numeroProcesso=${limpo}`, '_blank');
};

window.editarProcesso = (id) => {
    // Redireciona para o cadastro original passando o ID para edição
    location.href = `../cadastro.php?id=${id}`;
};

// --- Notificações SEEU (Alertas de Prazos Críticos) ---
window.carregarNotificacoes = async () => {
    try {
        const resp = await fetch('../api.php?acao=listar');
        const processos = await resp.json();
        
        const hoje = new Date();
        hoje.setHours(0,0,0,0);
        const hojeStr = hoje.toISOString().split('T')[0];

        // Filtra prazos vencendo em até 3 dias (Críticos) e não protocolados
        const criticos = processos.filter(p => {
            if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO' || !p.final_prazo) return false;
            const d = new Date(p.final_prazo + 'T12:00:00');
            const diffDays = Math.ceil((d - hoje) / 86400000);
            return diffDays >= 0 && diffDays <= 3;
        }).sort((a,b) => a.final_prazo.localeCompare(b.final_prazo));

        const badge = document.getElementById('badge-notificacoes');
        const lista = document.getElementById('lista-notificacoes');

        if(badge && lista) {
            badge.innerText = criticos.length;
            badge.style.display = criticos.length > 0 ? 'block' : 'none';

            if(criticos.length > 0) {
                lista.innerHTML = '';
                criticos.slice(0, 5).forEach(p => { // Mostra os 5 primeiros
                    const d = new Date(p.final_prazo + 'T12:00:00');
                    const diffDays = Math.ceil((d - hoje) / 86400000);
                    const msg = diffDays === 0 ? 'Vence HOJE!' : `Vence em ${diffDays} dias`;
                    
                    lista.innerHTML += `
                        <div style="background: rgba(248,113,113,0.1); padding: 8px; border-radius: 6px; border-left: 3px solid var(--status-urgente);">
                            <div style="font-weight: 700; color: white;">${p.numero}</div>
                            <div style="color: var(--status-urgente); font-weight: 800; font-size: 0.7rem;">${msg}</div>
                        </div>
                    `;
                });
                if(criticos.length > 5) {
                    lista.innerHTML += `<div style="text-align:center; opacity:0.7; font-size:0.7rem; cursor:pointer;" onclick="location.href='prazos.php'">Ver todos os ${criticos.length} prazos...</div>`;
                }
            } else {
                lista.innerHTML = '<div style="opacity: 0.7; text-align: center;">Nenhum prazo crítico no momento.</div>';
            }
        }
    } catch(e) {
        console.error("Erro ao carregar notificações", e);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('badge-notificacoes')) {
        carregarNotificacoes();
    }
});
