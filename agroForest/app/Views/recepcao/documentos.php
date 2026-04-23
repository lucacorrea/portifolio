<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Documentos';
$paginaDescricao = 'Gerencie documentos anexados aos protocolos da recepção.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Documentos';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header"><h2>Documentos anexados</h2><p>Arquivos enviados na abertura dos atendimentos.</p></div>
            <table>
                <thead><tr><th>Protocolo</th><th>Cliente</th><th>Arquivo</th><th>Tipo</th><th>Situação</th></tr></thead>
                <tbody>
                    <tr><td>PRT-2026-0418</td><td>Carlos Henrique</td><td>documento_cliente.pdf</td><td>PDF</td><td><span class="status ok">Validado</span></td></tr>
                    <tr><td>PRT-2026-0419</td><td>Ana Beatriz</td><td>comprovante.jpg</td><td>Imagem</td><td><span class="status pending">Pendente</span></td></tr>
                    <tr><td>PRT-2026-0421</td><td>Fernanda Martins</td><td>rg_frente.png</td><td>Imagem</td><td><span class="status progress">Em conferência</span></td></tr>
                </tbody>
            </table>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
