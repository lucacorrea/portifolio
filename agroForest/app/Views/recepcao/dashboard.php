<?php
$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard da Recepção';
$paginaDescricao = 'Visão geral dos atendimentos, protocolos e encaminhamentos do setor.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Dashboard';
$cssPagina = 'assets/css/recepcao/recepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="stats-grid">
            <article class="stat-card"><h3>28</h3><p>Atendimentos hoje</p></article>
            <article class="stat-card"><h3>14</h3><p>Protocolos abertos</p></article>
            <article class="stat-card"><h3>09</h3><p>Encaminhados</p></article>
            <article class="stat-card"><h3>04</h3><p>Pendências</p></article>
        </section>
        <section class="table-card">
            <h2>Fila da recepção</h2>
            <table>
                <thead><tr><th>Cliente</th><th>Serviço</th><th>Protocolo</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Carlos Henrique</td><td>Orçamento</td><td>PRT-2026-0418</td><td>Encaminhado</td></tr>
                    <tr><td>Ana Beatriz</td><td>Análise documental</td><td>PRT-2026-0419</td><td>Pendente</td></tr>
                </tbody>
            </table>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
