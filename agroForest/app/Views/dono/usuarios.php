<?php
$paginaAtual = 'usuarios';
$paginaTitulo = 'Usuários';
$paginaDescricao = 'Listagem de usuários do sistema.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$tituloPagina = 'Dono - Usuários';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3>12</h3><p>Usuários cadastrados</p></article>
            <article class="card stat-card"><h3>09</h3><p>Usuários ativos</p></article>
            <article class="card stat-card"><h3>03</h3><p>Perfis administrativos</p></article>
        </section>
        <section class="card panel">
            <div class="panel-header"><div><h2>Usuários cadastrados</h2><p>Acesso e perfil de cada pessoa no sistema.</p></div><a href="<?= route_url('dono', 'usuarioCadastrar') ?>" class="btn-primary">Cadastrar Usuário</a></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Maria Souza</strong></td><td>maria@agroforest.com</td><td>Recepção</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'usuarioVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'usuarioEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Maria Souza">Excluir</button></div></td></tr>
                        <tr><td><strong>Paulo Martins</strong></td><td>paulo@agroforest.com</td><td>Administrativo</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'usuarioVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'usuarioEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Paulo Martins">Excluir</button></div></td></tr>
                        <tr><td><strong>Lucas Almeida</strong></td><td>lucas@agroforest.com</td><td>Dono</td><td><span class="status progress">Em revisão</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'usuarioVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'usuarioEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Lucas Almeida">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">este usuário</strong>? Excluir abre somente esta modal.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
