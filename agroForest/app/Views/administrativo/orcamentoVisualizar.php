<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Visualizar Orçamento';
$paginaDescricao = 'Consulte o resumo completo do orçamento selecionado.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Editar Orçamento';
$linkBotaoAcao = route_url('administrativo', 'orcamentoEditar');
$tituloPagina = 'Administrativo - Visualizar Orçamento';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">💰</div><span class="trend up">ativo</span></div><h3>ORC-2026-0102</h3><p>Orçamento selecionado</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-secondary">📄</div><span class="trend up">protocolo</span></div><h3>PRT-2026-0502</h3><p>Registro de origem</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">🕒</div><span class="trend warn">prazo</span></div><h3>28/04</h3><p>Retorno previsto</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-danger">⚠️</div><span class="trend down">alta</span></div><h3>Urgente</h3><p>Status atual</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Resumo do orçamento</h2><p>Informações principais registradas na proposta.</p></div>
                <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="chip">Voltar</a>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Cliente</h3><p>Fernanda Martins - Atendimento prioritário vinculado ao protocolo PRT-2026-0502.</p></div>
                <div class="setting-block"><h3>Valor e prazo</h3><p>Valor estimado de R$ 3.980,00 com retorno previsto para 28/04/2026.</p></div>
                <div class="setting-block"><h3>Itens inclusos</h3><p>Análise documental completa, validação dos anexos, atendimento prioritário e retorno administrativo.</p></div>
                <div class="setting-block"><h3>Observações</h3><p>Cliente com prioridade alta. Necessário validar toda documentação antes do envio final.</p></div>
            </div>
        </section>

        <section class="bottom-grid compact-card">
            <article class="card panel">
                <div class="panel-header"><div><h3>Histórico</h3><p>Movimentações recentes.</p></div></div>
                <div class="alert-list">
                    <div class="alert-item"><strong>Orçamento criado</strong><p>Registro aberto com base no protocolo encaminhado pela recepção.</p><span class="alert-tag info">28/04/2026</span></div>
                    <div class="alert-item"><strong>Documentação revisada</strong><p>Anexos conferidos para continuidade da análise.</p><span class="alert-tag attention">29/04/2026</span></div>
                </div>
            </article>
            <article class="card panel">
                <div class="panel-header"><div><h3>Ações</h3><p>Próximos passos do atendimento.</p></div></div>
                <div class="table-actions">
                    <a href="<?= route_url('administrativo', 'orcamentoEditar') ?>" class="btn-primary">Editar</a>
                    <a href="<?= route_url('administrativo', 'documentoVisualizar') ?>" class="btn-outline">Ver documentos</a>
                    <a href="<?= route_url('administrativo', 'pendenciaCadastrar') ?>" class="btn-secondary">Abrir pendência</a>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
