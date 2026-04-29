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
