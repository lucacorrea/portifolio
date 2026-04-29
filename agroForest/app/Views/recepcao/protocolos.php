<?php
$paginaAtual = 'protocolos';
$paginaTitulo = 'Protocolos';
$paginaDescricao = 'Listagem dos protocolos abertos e encaminhados pela recepção.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Protocolos';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header">
                <div><h2>Protocolos cadastrados</h2><p>Controle geral dos protocolos da recepção.</p></div>
                <a href="<?= route_url('recepcao', 'novoProtocolo') ?>" class="btn-primary">Novo Protocolo</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Protocolo</th><th>Cliente</th><th>Serviço</th><th>Data</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td><strong>PRT-2026-0418</strong></td><td>Carlos Henrique</td><td>Orçamento</td><td>22/04/2026</td><td><span class="status ok">Encaminhado</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'protocoloVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'protocoloEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0418">Excluir</button></div></td></tr>
                        <tr><td><strong>PRT-2026-0419</strong></td><td>Ana Beatriz</td><td>Análise documental</td><td>22/04/2026</td><td><span class="status pending">Pendente</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'protocoloVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'protocoloEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0419">Excluir</button></div></td></tr>
                        <tr><td><strong>PRT-2026-0420</strong></td><td>João Pedro</td><td>Cadastro de serviço</td><td>21/04/2026</td><td><span class="status progress">Em triagem</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'protocoloVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'protocoloEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0420">Excluir</button></div></td></tr>
                        <tr><td><strong>PRT-2026-0421</strong></td><td>Fernanda Martins</td><td>Atendimento urgente</td><td>21/04/2026</td><td><span class="status high">Urgente</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'protocoloVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'protocoloEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0421">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este protocolo</strong>? Não haverá página separada para excluir.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
