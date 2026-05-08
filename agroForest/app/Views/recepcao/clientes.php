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
$clientes = clientes_contratos_lista();
$indicadores = clientes_contratos_indicadores($clientes);
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3><?= htmlspecialchars((string) $indicadores['clientes']) ?></h3><p>Clientes cadastrados</p></article>
            <article class="card stat-card"><h3><?= htmlspecialchars((string) $indicadores['contratos']) ?></h3><p>Contratos vinculados</p></article>
            <article class="card stat-card"><h3><?= htmlspecialchars((string) $indicadores['contratos_pendentes']) ?></h3><p>Contratos em atenção</p></article>
        </section>

        <section class="table-card">
            <div class="section-header">
                <div><h2>Clientes cadastrados</h2><p>Cadastros usados na abertura de protocolos, com contratos vinculados.</p></div>
                <a href="<?= route_url('recepcao', 'clienteCadastrar') ?>" class="btn-primary">Cadastrar Cliente</a>
            </div>
            <div class="table-responsive">
                <table class="client-contract-table">
                    <thead><tr><th>Cliente</th><th>Telefone</th><th>Documento</th><th>Último atendimento</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php $contratoPrincipal = $cliente['contratos'][0] ?? null; ?>
                            <tr>
                                <td data-label="Cliente">
                                    <div class="client-name"><?= htmlspecialchars($cliente['nome']) ?></div>
                                    <div class="client-sub"><?= htmlspecialchars($cliente['email']) ?></div>
                                </td>
                                <td data-label="Telefone"><?= htmlspecialchars($cliente['telefone']) ?></td>
                                <td data-label="Documento"><?= htmlspecialchars($cliente['documento']) ?></td>
                                <td data-label="Último atendimento"><?= htmlspecialchars($cliente['ultimo_atendimento']) ?></td>
                                <td data-label="Status"><span class="status <?= cliente_status_classe($cliente['status']) ?>"><?= htmlspecialchars($cliente['status']) ?></span></td>
                                <td data-label="Ações">
                                    <div class="table-actions">
                                        <a href="<?= route_url('recepcao', 'clienteVisualizar') ?>" class="btn-outline">Ver</a>
                                        <?php if ($contratoPrincipal): ?>
                                            <a href="<?= htmlspecialchars(contrato_visualizar_url('recepcao', $contratoPrincipal['numero'])) ?>" class="btn-outline">Ver contrato</a>
                                        <?php endif; ?>
                                        <a href="<?= route_url('recepcao', 'clienteEditar') ?>" class="btn-primary">Editar</a>
                                        <button type="button" class="btn-danger" data-delete-name="<?= htmlspecialchars($cliente['nome']) ?>">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
