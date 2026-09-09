<aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php"><span class="brand-mark">LJ</span><span><b>L&J</b><small>Comandas</small></span></a>
    <nav class="nav">
        <span class="nav-section">Visão geral</span>
        <a class="nav-link <?= navActive('dashboard',$currentPage) ?>" href="dashboard.php"><span>▦</span>Dashboard</a>

        <span class="nav-section">Operação</span>
        <a class="nav-link <?= navActive('mesas',$currentPage) ?>" href="mesas.php"><span>▤</span>Mesas</a>
        <a class="nav-link <?= navActive('comandas',$currentPage) ?>" href="comandas.php"><span>▧</span>Comandas</a>
        <a class="nav-link <?= navActive('pedidos',$currentPage) ?>" href="pedidos.php"><span>◫</span>Pedidos</a>
        <a class="nav-link <?= navActive('producao',$currentPage) ?>" href="producao.php"><span>⌁</span>Produção</a>
        <a class="nav-link <?= navActive('caixa',$currentPage) ?>" href="caixa.php"><span>◈</span>Caixa</a>
        <a class="nav-link <?= navActive('mapa',$currentPage) ?>" href="mapa.php"><span>⌘</span>Mapa do salão</a>

        <span class="nav-section">Cadastros</span>
        <a class="nav-link <?= navActive('clientes',$currentPage) ?>" href="clientes.php"><span>👥</span>Clientes</a>
        <a class="nav-link <?= navActive('itens',$currentPage) ?>" href="itens.php"><span>▣</span>Produtos e serviços</a>
        <a class="nav-link <?= navActive('categorias',$currentPage) ?>" href="categorias.php"><span>≡</span>Categorias</a>
        <a class="nav-link <?= navActive('adicionais',$currentPage) ?>" href="adicionais.php"><span>＋</span>Adicionais</a>

        <span class="nav-section">Estoque</span>
        <a class="nav-link <?= navActive('estoque',$currentPage) ?>" href="estoque.php"><span>▥</span>Visão geral</a>
        <a class="nav-link <?= navActive('movimentacoes',$currentPage) ?>" href="movimentacoes-estoque.php"><span>⇄</span>Movimentações</a>
        <a class="nav-link <?= navActive('entrada',$currentPage) ?>" href="entrada-estoque.php"><span>⇩</span>Entradas</a>
        <a class="nav-link <?= navActive('inventario',$currentPage) ?>" href="inventario.php"><span>✓</span>Inventário</a>

        <span class="nav-section">Gestão</span>
        <a class="nav-link <?= navActive('relatorios',$currentPage) ?>" href="relatorios.php"><span>⌁</span>Relatórios</a>
        <a class="nav-link <?= navActive('auditoria',$currentPage) ?>" href="auditoria.php"><span>◉</span>Auditoria</a>

        <span class="nav-section">Configurações</span>
        <a class="nav-link <?= navActive('empresa',$currentPage) ?>" href="empresa.php"><span>⚙</span>Empresa</a>
        <a class="nav-link <?= navActive('usuarios',$currentPage) ?>" href="usuarios.php"><span>♙</span>Usuários</a>
        <a class="nav-link <?= navActive('perfis',$currentPage) ?>" href="perfis.php"><span>🔐</span>Perfis e permissões</a>
    </nav>
    <div class="sidebar-footer"><a href="admin.php">Área SaaS</a><a href="perfil.php">Meu perfil</a><a href="login.php">Sair</a></div>
</aside>
<div class="sidebar-overlay" data-sidebar-toggle></div>
