<?php
$paginaAtual = 'encaminhar';
$paginaTitulo = 'Encaminhar Protocolos';
$paginaDescricao = 'Envie os protocolos completos para o setor administrativo.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Ver Protocolos';
$linkBotaoAcao = route_url('recepcao', 'protocolos');
$tituloPagina = 'Recepção - Encaminhar';
$cssPagina = 'assets/css/recepcao/recepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header"><h2>Fila de encaminhamento</h2><p>Protocolos prontos para envio ao administrativo.</p></div>
            <table>
                <thead><tr><th>Protocolo</th><th>Cliente</th><th>Serviço</th><th>Prioridade</th><th>Ação</th></tr></thead>
                <tbody>
                    <tr><td>PRT-2026-0418</td><td>Carlos Henrique</td><td>Orçamento</td><td><span class="status progress">Normal</span></td><td><button class="btn-primary" type="button">Encaminhar</button></td></tr>
                    <tr><td>PRT-2026-0421</td><td>Fernanda Martins</td><td>Atendimento urgente</td><td><span class="status high">Urgente</span></td><td><button class="btn-primary" type="button">Encaminhar</button></td></tr>
                    <tr><td>PRT-2026-0424</td><td>Rafael Souza</td><td>Análise documental</td><td><span class="status pending">Média</span></td><td><button class="btn-primary" type="button">Encaminhar</button></td></tr>
                </tbody>
            </table>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
