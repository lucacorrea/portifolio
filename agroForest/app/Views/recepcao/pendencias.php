<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Pendências';
$paginaDescricao = 'Listagem dos atendimentos que precisam de retorno ou correção.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Cadastrar Pendência';
$linkBotaoAcao = route_url('recepcao', 'pendenciaCadastrar');
$tituloPagina = 'Recepção - Pendências';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="table-card">
            <div class="section-header"><div><h2>Pendências da recepção</h2><p>Itens que precisam de retorno antes do envio.</p></div><a href="<?= route_url('recepcao', 'pendenciaCadastrar') ?>" class="btn-primary">Cadastrar Pendência</a></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Protocolo</th><th>Cliente</th><th>Motivo</th><th>Data</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td>PRT-2026-0419</td><td>Ana Beatriz</td><td>Documento pendente</td><td>22/04/2026</td><td><span class="status pending">Aguardando cliente</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'pendenciaVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'pendenciaEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0419">Excluir</button></div></td></tr>
                        <tr><td>PRT-2026-0423</td><td>Rafael Souza</td><td>Telefone incorreto</td><td>22/04/2026</td><td><span class="status progress">Revisão interna</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'pendenciaVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'pendenciaEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0423">Excluir</button></div></td></tr>
                        <tr><td>PRT-2026-0425</td><td>Eliane Silva</td><td>Assinatura ausente</td><td>23/04/2026</td><td><span class="status high">Prioridade alta</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'pendenciaVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'pendenciaEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="PRT-2026-0425">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir a pendência de <strong id="deleteItemName">este protocolo</strong>? Excluir não terá página própria.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
