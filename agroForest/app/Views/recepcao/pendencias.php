<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Pendências';
$paginaDescricao = 'Acompanhe protocolos com ausência de informações ou documentos.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Pendências';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header"><h2>Pendências da recepção</h2><p>Atendimentos que precisam de retorno ou correção antes do envio.</p></div>
            <table>
                <thead><tr><th>Protocolo</th><th>Cliente</th><th>Motivo</th><th>Data</th><th>Situação</th></tr></thead>
                <tbody>
                    <tr><td>PRT-2026-0419</td><td>Ana Beatriz</td><td>Documento pendente</td><td>22/04/2026</td><td><span class="status pending">Aguardando cliente</span></td></tr>
                    <tr><td>PRT-2026-0423</td><td>Rafael Souza</td><td>Telefone incorreto</td><td>22/04/2026</td><td><span class="status progress">Revisão interna</span></td></tr>
                    <tr><td>PRT-2026-0425</td><td>Eliane Silva</td><td>Assinatura ausente</td><td>23/04/2026</td><td><span class="status high">Prioridade alta</span></td></tr>
                </tbody>
            </table>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
