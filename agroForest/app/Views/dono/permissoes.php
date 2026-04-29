<?php
$paginaAtual = 'permissoes';
$paginaTitulo = 'Permissões';
$paginaDescricao = 'Listagem de perfis e permissões por área.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$tituloPagina = 'Dono - Permissões';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="card panel">
            <div class="panel-header"><div><h2>Perfis de acesso</h2><p>Controle do que cada perfil pode acessar.</p></div><a href="<?= route_url('dono', 'permissaoCadastrar') ?>" class="btn-primary">Cadastrar Permissão</a></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Perfil</th><th>Área</th><th>Permissões</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Recepção</strong></td><td>Operação</td><td>Clientes, protocolos, documentos</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'permissaoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'permissaoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Recepção">Excluir</button></div></td></tr>
                        <tr><td><strong>Administrativo</strong></td><td>Análise</td><td>Protocolos, orçamentos, pendências</td><td><span class="status ok">Ativo</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'permissaoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'permissaoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Administrativo">Excluir</button></div></td></tr>
                        <tr><td><strong>Dono</strong></td><td>Gestão</td><td>Usuários, permissões, relatórios</td><td><span class="status high">Restrito</span></td><td><div class="table-actions"><a href="<?= route_url('dono', 'permissaoVisualizar') ?>" class="btn-outline">Ver</a><a href="<?= route_url('dono', 'permissaoEditar') ?>" class="btn-primary">Editar</a><button type="button" class="btn-danger" data-delete-name="Dono">Excluir</button></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<div class="modal-backdrop" id="deleteModal"><div class="modal"><div class="modal-header"><h3>Confirmar exclusão</h3></div><div class="modal-body"><p>Deseja excluir <strong id="deleteItemName">esta permissão</strong>? Não haverá página de exclusão.</p></div><div class="modal-footer"><button type="button" class="btn-secondary" data-modal-close>Cancelar</button><button type="button" class="btn-danger" data-modal-close>Excluir</button></div></div></div>
<script>
document.querySelectorAll('[data-delete-name]').forEach((button)=>button.addEventListener('click',()=>{document.getElementById('deleteItemName').textContent=button.dataset.deleteName;document.getElementById('deleteModal').classList.add('active');}));
document.querySelectorAll('[data-modal-close]').forEach((button)=>button.addEventListener('click',()=>document.getElementById('deleteModal').classList.remove('active')));
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
