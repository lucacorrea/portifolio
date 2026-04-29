<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Visualizar Pendência';
$paginaDescricao = 'Consulte detalhes e histórico da pendência aberta.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Editar Pendência';
$linkBotaoAcao = route_url('administrativo', 'pendenciaEditar');
$tituloPagina = 'Administrativo - Visualizar Pendência';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">⏳</div><span class="trend warn">aberta</span></div><h3>PRT-2026-0502</h3><p>Protocolo vinculado</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-secondary">👤</div><span class="trend up">cliente</span></div><h3>Fernanda</h3><p>Cliente relacionado</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">📎</div><span class="trend warn">documento</span></div><h3>Pendente</h3><p>Motivo principal</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-danger">⚠️</div><span class="trend down">alta</span></div><h3>Alta</h3><p>Prioridade atual</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Documento complementar não enviado</h2><p>Pendência aberta em 28/04/2026 às 09:18.</p></div>
                <span class="status high">Aguardando cliente</span>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Descrição</h3><p>A documentação complementar precisa ser enviada pelo cliente para liberar a validação final do orçamento.</p></div>
                <div class="setting-block"><h3>Responsável</h3><p>Administrativo - Paulo Martins, com apoio da recepção para contato com o cliente.</p></div>
                <div class="setting-block"><h3>Prazo</h3><p>Resolução esperada até 30/04/2026 para evitar impacto no orçamento.</p></div>
            </div>
        </section>

        <section class="bottom-grid compact-card">
            <article class="card panel">
                <div class="panel-header"><div><h3>Histórico</h3><p>Movimentações registradas.</p></div></div>
                <div class="alert-list">
                    <div class="alert-item"><strong>Pendência aberta</strong><p>Bloqueio criado após conferência inicial do protocolo.</p><span class="alert-tag info">28/04/2026</span></div>
                    <div class="alert-item"><strong>Cliente avisado</strong><p>Recepção entrou em contato solicitando complemento documental.</p><span class="alert-tag attention">29/04/2026</span></div>
                </div>
            </article>
            <article class="card panel">
                <div class="panel-header"><div><h3>Ações</h3><p>Tratativas disponíveis para a pendência.</p></div></div>
                <div class="table-actions">
                    <a href="<?= route_url('administrativo', 'pendenciaEditar') ?>" class="btn-primary">Editar</a>
                    <a href="<?= route_url('administrativo', 'documentoVisualizar') ?>" class="btn-outline">Ver documento</a>
                    <a href="<?= route_url('administrativo', 'protocoloVisualizar') ?>" class="btn-secondary">Ver protocolo</a>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
