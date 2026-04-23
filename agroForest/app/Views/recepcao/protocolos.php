<?php
$paginaAtual = 'protocolos';
$paginaTitulo = 'Protocolos';
$paginaDescricao = 'Acompanhe os protocolos abertos, em andamento e encaminhados.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Protocolos';
$cssPagina = 'assets/css/recepcao/protocolos.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header"><h2>Protocolos cadastrados</h2><p>Controle geral dos protocolos da recepção.</p></div>
            <table>
                <thead><tr><th>Protocolo</th><th>Cliente</th><th>Serviço</th><th>Data</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>PRT-2026-0418</td><td>Carlos Henrique</td><td>Orçamento</td><td>22/04/2026</td><td><span class="status ok">Encaminhado</span></td></tr>
                    <tr><td>PRT-2026-0419</td><td>Ana Beatriz</td><td>Análise documental</td><td>22/04/2026</td><td><span class="status pending">Pendente</span></td></tr>
                    <tr><td>PRT-2026-0420</td><td>João Pedro</td><td>Cadastro de serviço</td><td>21/04/2026</td><td><span class="status progress">Em triagem</span></td></tr>
                    <tr><td>PRT-2026-0421</td><td>Fernanda Martins</td><td>Atendimento urgente</td><td>21/04/2026</td><td><span class="status high">Urgente</span></td></tr>
                </tbody>
            </table>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
