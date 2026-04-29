<?php
$paginaAtual = 'protocolosRecebidos';
$paginaTitulo = 'Visualizar Protocolo';
$paginaDescricao = 'Confira os dados do protocolo recebido antes de gerar orçamento ou pendência.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Gerar Orçamento';
$linkBotaoAcao = route_url('administrativo', 'orcamentoCadastrar');
$tituloPagina = 'Administrativo - Visualizar Protocolo';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">📥</div><span class="trend up">recebido</span></div><h3>PRT-2026-0502</h3><p>Protocolo em análise</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-secondary">👤</div><span class="trend up">cliente</span></div><h3>Fernanda</h3><p>Cliente vinculado</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">⏱️</div><span class="trend warn">01h30</span></div><h3>Fila</h3><p>Tempo de espera</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-danger">⚠️</div><span class="trend down">alta</span></div><h3>Urgente</h3><p>Prioridade atual</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Dados do protocolo</h2><p>Resumo da solicitação enviada pela recepção.</p></div>
                <a href="<?= route_url('administrativo', 'protocolosRecebidos') ?>" class="chip">Voltar</a>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Cliente</h3><p>Fernanda Martins - (92) 99123-4088 - 987.654.321-00</p></div>
                <div class="setting-block"><h3>Serviço solicitado</h3><p>Atendimento prioritário com análise documental e retorno administrativo.</p></div>
                <div class="setting-block"><h3>Origem</h3><p>Recepção, registrado por Maria Souza em 28/04/2026 às 09:16.</p></div>
                <div class="setting-block"><h3>Observações</h3><p>Cliente solicitou prioridade e anexou comprovantes para conferência antes do orçamento.</p></div>
            </div>
        </section>

        <section class="bottom-grid compact-card">
            <article class="card panel">
                <div class="panel-header"><div><h3>Anexos recebidos</h3><p>Documentos vinculados ao protocolo.</p></div></div>
                <div class="alert-list">
                    <div class="alert-item"><strong>anexo_prioritario_fernanda.jpg</strong><p>Imagem enviada pela recepção aguardando validação.</p><span class="alert-tag attention">Pendente</span></div>
                    <div class="alert-item"><strong>documento_base.pdf</strong><p>Documento principal disponível para conferência.</p><span class="alert-tag info">Recebido</span></div>
                </div>
            </article>
            <article class="card panel">
                <div class="panel-header"><div><h3>Ações do protocolo</h3><p>Encaminhamentos disponíveis para o administrativo.</p></div></div>
                <div class="table-actions">
                    <a href="<?= route_url('administrativo', 'orcamentoCadastrar') ?>" class="btn-primary">Gerar orçamento</a>
                    <a href="<?= route_url('administrativo', 'pendenciaCadastrar') ?>" class="btn-outline">Abrir pendência</a>
                    <a href="<?= route_url('administrativo', 'documentoVisualizar') ?>" class="btn-secondary">Validar documentos</a>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
