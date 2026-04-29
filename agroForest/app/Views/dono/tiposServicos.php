<?php
$paginaAtual = 'tiposServicos';
$paginaTitulo = 'Tipos de Serviços';
$paginaDescricao = 'Cadastre e organize os serviços usados em protocolos e orçamentos.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Cadastrar Serviço';
$linkBotaoAcao = route_url('dono', 'tipoServicoCadastrar');
$tituloPagina = 'Dono - Tipos de Serviços';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">🧾</div><span class="trend up">6 ativos</span></div><h3>08</h3><p>Serviços cadastrados</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">⏳</div><span class="trend warn">2 revisar</span></div><h3>03</h3><p>Sem valor base</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-success">📌</div><span class="trend up">4 setores</span></div><h3>04</h3><p>Categorias operacionais</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Catálogo de serviços</h2>
                    <p>Use esses tipos no cadastro de protocolos, orçamentos e relatórios.</p>
                </div>
                <a href="<?= route_url('dono', 'tipoServicoCadastrar') ?>" class="btn-primary">Cadastrar Serviço</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Serviço</th><th>Categoria</th><th>Setor</th><th>Prazo</th><th>Valor base</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Licenciamento Ambiental</strong><div class="client-sub">Análise documental e acompanhamento</div></td><td>Ambiental</td><td>Administrativo</td><td>5 dias úteis</td><td>R$ 1.850,00</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'tipoServicoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'tipoServicoEditar') ?>" class="btn-primary">Editar</a></div></td></tr>
                        <tr><td><strong>Cadastro Rural</strong><div class="client-sub">CAR, atualização e revisão cadastral</div></td><td>Rural</td><td>Recepção</td><td>3 dias úteis</td><td>R$ 740,00</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'tipoServicoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'tipoServicoEditar') ?>" class="btn-primary">Editar</a></div></td></tr>
                        <tr><td><strong>Georreferenciamento</strong><div class="client-sub">Levantamento técnico de área</div></td><td>Técnico</td><td>Administrativo</td><td>10 dias úteis</td><td>R$ 2.900,00</td><td><span class="status progress">Em revisão</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'tipoServicoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'tipoServicoEditar') ?>" class="btn-primary">Editar</a></div></td></tr>
                        <tr><td><strong>Consultoria Agroflorestal</strong><div class="client-sub">Plano técnico e orientação produtiva</div></td><td>Consultoria</td><td>Dono</td><td>7 dias úteis</td><td>A definir</td><td><span class="status pending">Pendente</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'tipoServicoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'tipoServicoEditar') ?>" class="btn-primary">Editar</a></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
