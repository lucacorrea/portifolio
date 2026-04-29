<?php
$paginaAtual = 'tiposServicos';
$paginaTitulo = 'Visualizar Tipo de Serviço';
$paginaDescricao = 'Detalhes de cadastro e uso do serviço.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Editar Serviço';
$linkBotaoAcao = route_url('dono', 'tipoServicoEditar');
$tituloPagina = 'Dono - Visualizar Tipo de Serviço';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3>5 dias</h3><p>Prazo padrão</p></article>
            <article class="card stat-card"><h3>R$ 1.850</h3><p>Valor base</p></article>
            <article class="card stat-card"><h3>24</h3><p>Protocolos vinculados</p></article>
        </section>

        <section class="bottom-grid compact-card">
            <article class="card panel">
                <div class="panel-header"><div><h2>Licenciamento Ambiental</h2><p>Serviço ativo do catálogo operacional.</p></div><span class="status ok">Ativo</span></div>
                <div class="info-grid owner-info-grid">
                    <div class="info-card"><strong>Categoria</strong><p>Ambiental</p></div>
                    <div class="info-card"><strong>Setor</strong><p>Administrativo</p></div>
                    <div class="info-card"><strong>Prazo</strong><p>5 dias úteis</p></div>
                </div>
                <div class="info-card compact-card"><strong>Descrição</strong><p>Análise documental, abertura de protocolo ambiental e acompanhamento do processo.</p></div>
            </article>
            <article class="card panel">
                <div class="panel-header"><div><h2>Documentos necessários</h2><p>Lista exibida para triagem da recepção.</p></div></div>
                <div class="check-list">
                    <div class="alert-item"><strong>CPF/CNPJ</strong><p>Documento do solicitante ou empresa.</p></div>
                    <div class="alert-item"><strong>Documentos da propriedade</strong><p>Matrícula, posse ou documentação equivalente.</p></div>
                    <div class="alert-item"><strong>Mapa ou croqui</strong><p>Referência de localização da área.</p></div>
                </div>
                <div class="form-actions compact-card"><a href="<?= route_url('dono', 'tiposServicos') ?>" class="btn-secondary">Voltar</a><a href="<?= route_url('dono', 'tipoServicoEditar') ?>" class="btn-primary">Editar Serviço</a></div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
