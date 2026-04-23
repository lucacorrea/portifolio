<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes';
$paginaDescricao = 'Consulte os clientes cadastrados e acompanhe o histórico básico de atendimento.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Clientes';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3>186</h3><p>Clientes cadastrados</p></article>
            <article class="card stat-card"><h3>24</h3><p>Novos este mês</p></article>
            <article class="card stat-card"><h3>12</h3><p>Com pendência cadastral</p></article>
        </section>

        <section class="table-card">
            <div class="section-header"><h2>Clientes cadastrados</h2><p>Lista de clientes atendidos pela recepção.</p></div>
            <table>
                <thead><tr><th>Cliente</th><th>Telefone</th><th>Documento</th><th>Último atendimento</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td><strong>Carlos Henrique</strong></td><td>(92) 99999-1020</td><td>123.456.789-00</td><td>22/04/2026</td><td><span class="status ok">Ativo</span></td></tr>
                    <tr><td><strong>Ana Beatriz Costa</strong></td><td>(92) 98888-2451</td><td>987.654.321-00</td><td>22/04/2026</td><td><span class="status ok">Ativo</span></td></tr>
                    <tr><td><strong>João Pedro Silva</strong></td><td>(92) 99777-8874</td><td>741.852.963-00</td><td>21/04/2026</td><td><span class="status pending">Pendente</span></td></tr>
                    <tr><td><strong>Fernanda Martins</strong></td><td>(92) 99123-4088</td><td>369.258.147-00</td><td>20/04/2026</td><td><span class="status ok">Ativo</span></td></tr>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
