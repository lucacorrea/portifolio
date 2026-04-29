<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Documentos';
$paginaDescricao = 'Listagem dos documentos anexados aos protocolos.';
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
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Protocolo</th><th>Cliente</th><th>Arquivo</th><th>Tipo</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td>PRT-2026-0418</td><td>Carlos Henrique</td><td>documento_cliente.pdf</td><td>PDF</td><td><span class="status ok">Validado</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'documentoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'documentoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="documento_cliente.pdf">Excluir</button></div></td></tr>
                        <tr><td>PRT-2026-0419</td><td>Ana Beatriz</td><td>comprovante.jpg</td><td>Imagem</td><td><span class="status pending">Pendente</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'documentoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'documentoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="comprovante.jpg">Excluir</button></div></td></tr>
                        <tr><td>PRT-2026-0421</td><td>Fernanda Martins</td><td>rg_frente.png</td><td>Imagem</td><td><span class="status progress">Em conferência</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'documentoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'documentoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="rg_frente.png">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este documento</strong>? A exclusão acontece somente pela modal.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
