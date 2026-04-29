<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes';
$paginaDescricao = 'Listagem dos clientes atendidos pela recepção.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Cadastrar Cliente';
$linkBotaoAcao = route_url('recepcao', 'clienteCadastrar');
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
            <div class="section-header">
                <div><h2>Clientes cadastrados</h2><p>Cadastros usados na abertura de protocolos.</p></div>
                <a href="<?= route_url('recepcao', 'clienteCadastrar') ?>" class="btn-primary">Cadastrar Cliente</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Cliente</th><th>Telefone</th><th>Documento</th><th>Último atendimento</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Carlos Henrique</strong></td><td>(92) 99999-1020</td><td>123.456.789-00</td><td>22/04/2026</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'clienteVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'clienteEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Carlos Henrique">Excluir</button></div></td></tr>
                        <tr><td><strong>Ana Beatriz Costa</strong></td><td>(92) 98888-2451</td><td>987.654.321-00</td><td>22/04/2026</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'clienteVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'clienteEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Ana Beatriz Costa">Excluir</button></div></td></tr>
                        <tr><td><strong>João Pedro Silva</strong></td><td>(92) 99777-8874</td><td>741.852.963-00</td><td>21/04/2026</td><td><span class="status pending">Pendente</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'clienteVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'clienteEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="João Pedro Silva">Excluir</button></div></td></tr>
                        <tr><td><strong>Fernanda Martins</strong></td><td>(92) 99123-4088</td><td>369.258.147-00</td><td>20/04/2026</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('recepcao', 'clienteVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('recepcao', 'clienteEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Fernanda Martins">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este cliente</strong>? A exclusão abre somente esta modal.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
