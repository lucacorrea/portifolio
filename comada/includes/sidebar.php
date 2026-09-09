<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <a class="brand" href="dashboard.php" aria-label="L&J Comandas">
            <span class="brand-mark">LJ</span>
            <span class="brand-copy"><b>L&J Comandas</b><small>Gestão inteligente</small></span>
        </a>
        <button class="sidebar-collapse" type="button" data-sidebar-toggle aria-label="Fechar menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
    </div>

    <nav class="nav">
        <span class="nav-section">Visão geral</span>
        <a class="nav-link <?= navActive('dashboard',$currentPage) ?>" href="dashboard.php">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9zm0 7h6v-4H4v4zm10 0h6v-9h-6v9zm0-16v4h6V4h-6z"/></svg></span>
            <span>Dashboard</span>
        </a>

        <span class="nav-section">Operação</span>
        <a class="nav-link <?= navActive('mesas',$currentPage) ?>" href="mesas.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 9h14M7 9v10m10-10v10M4 5h16v4H4V5z"/></svg></span><span>Mesas</span></a>
        <a class="nav-link <?= navActive('comandas',$currentPage) ?>" href="comandas.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6m-6 4h6"/></svg></span><span>Comandas</span></a>
        <a class="nav-link <?= navActive('pedidos',$currentPage) ?>" href="pedidos.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16l-1.5 12h-13L4 7zm3 0a5 5 0 0110 0"/></svg></span><span>Pedidos</span><span class="nav-count">21</span></a>
        <a class="nav-link <?= navActive('producao',$currentPage) ?>" href="producao.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 18h16M7 18a5 5 0 0110 0M12 5v3m-6 .5l2 2M18 8.5l-2 2"/></svg></span><span>Produção</span></a>
        <a class="nav-link <?= navActive('caixa',$currentPage) ?>" href="caixa.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v13H4V6zm0 4h16M8 15h3"/></svg></span><span>Caixa</span></a>
        <a class="nav-link <?= navActive('mapa',$currentPage) ?>" href="mapa.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M9 18l-5 2V6l5-2 6 2 5-2v14l-5 2-6-2zm0 0V4m6 16V6"/></svg></span><span>Mapa do salão</span></a>

        <span class="nav-section">Cadastros</span>
        <a class="nav-link <?= navActive('clientes',$currentPage) ?>" href="clientes.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m7-10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span><span>Clientes</span></a>
        <a class="nav-link <?= navActive('itens',$currentPage) ?>" href="itens.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4V5zm4 4h8m-8 4h5"/></svg></span><span>Produtos e serviços</span></a>
        <a class="nav-link <?= navActive('categorias',$currentPage) ?>" href="categorias.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg></span><span>Categorias</span></a>
        <a class="nav-link <?= navActive('adicionais',$currentPage) ?>" href="adicionais.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span><span>Adicionais</span></a>

        <span class="nav-section">Estoque</span>
        <a class="nav-link <?= navActive('estoque',$currentPage) ?>" href="estoque.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7m-8 4v10"/></svg></span><span>Visão geral</span></a>
        <a class="nav-link <?= navActive('movimentacoes',$currentPage) ?>" href="movimentacoes-estoque.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M7 7h13l-3-3m3 3l-3 3M17 17H4l3 3m-3-3l3-3"/></svg></span><span>Movimentações</span></a>
        <a class="nav-link <?= navActive('entrada',$currentPage) ?>" href="entrada-estoque.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3v12m-5-5l5 5 5-5M5 21h14"/></svg></span><span>Entradas</span></a>
        <a class="nav-link <?= navActive('inventario',$currentPage) ?>" href="inventario.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M9 11l2 2 4-4M5 4h14v16H5V4z"/></svg></span><span>Inventário</span></a>

        <span class="nav-section">Gestão</span>
        <a class="nav-link <?= navActive('relatorios',$currentPage) ?>" href="relatorios.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 20V10m7 10V4m7 16v-7"/></svg></span><span>Relatórios</span></a>
        <a class="nav-link <?= navActive('auditoria',$currentPage) ?>" href="auditoria.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4zm-3 9l2 2 4-4"/></svg></span><span>Auditoria</span></a>

        <span class="nav-section">Configurações</span>
        <a class="nav-link <?= navActive('empresa',$currentPage) ?>" href="empresa.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1012 8a3.5 3.5 0 000 7.5zM19.4 15a7.8 7.8 0 000-6l2-1.5-2-3.4-2.4 1a8.2 8.2 0 00-5.2-3L11.5 0h-4L7 2.1a8.2 8.2 0 00-5.2 3l-2.4-1-2 3.4L-.6 9a7.8 7.8 0 000 6l-2 1.5 2 3.4 2.4-1a8.2 8.2 0 005.2 3l.5 2.1h4l.5-2.1a8.2 8.2 0 005.2-3l2.4 1 2-3.4-2-1.5z" transform="scale(.75) translate(4 4)"/></svg></span><span>Empresa</span></a>
        <a class="nav-link <?= navActive('usuarios',$currentPage) ?>" href="usuarios.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0"/></svg></span><span>Usuários</span></a>
        <a class="nav-link <?= navActive('perfis',$currentPage) ?>" href="perfis.php"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0110 0v3m-11 0h12v10H6V11z"/></svg></span><span>Perfis e permissões</span></a>
    </nav>

    <div class="sidebar-footer">
        <div class="workspace-card">
            <span class="workspace-dot"></span>
            <div><strong>Bar Central</strong><small>Unidade Centro</small></div>
            <span class="workspace-chevron">›</span>
        </div>
        <div class="sidebar-footer-links"><a href="admin.php">Área SaaS</a><a href="perfil.php">Meu perfil</a><a href="login.php">Sair</a></div>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-toggle></div>
