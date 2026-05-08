<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Visualizar Cliente';
$paginaDescricao = 'Consulte dados, protocolos e pendências vinculadas ao cliente.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Editar Cliente';
$linkBotaoAcao = route_url('administrativo', 'clienteEditar');
$tituloPagina = 'Administrativo - Visualizar Cliente';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Carlos Henrique</h2><p>CPF 123.456.789-00 - (92) 99999-1020 - carlos@email.com</p></div>
                <a href="<?= route_url('administrativo', 'clientes') ?>" class="chip">Voltar</a>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Situação cadastral</h3><p>Cliente ativo, sem pendências cadastrais abertas no momento.</p></div>
                <div class="setting-block"><h3>Endereço</h3><p>Rua das Castanheiras, 120, Manaus - AM.</p></div>
                <div class="setting-block"><h3>Último atendimento</h3><p>PRT-2026-0501 - Solicitação de orçamento encaminhada ao administrativo.</p></div>
                <div class="setting-block setting-block-row">
                    <div><h3>Terreno vinculado</h3><p>Sítio Castanheira com coordenadas UTM cadastradas.</p></div>
                    <a href="<?= htmlspecialchars(terreno_url('administrativo', 'terrenoVisualizar', 'TER-2026-001')) ?>" class="btn-outline">Ver terreno</a>
                </div>
            </div>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header"><div><h2>Histórico do cliente</h2><p>Protocolos e movimentações recentes.</p></div></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Protocolo</th><th>Serviço</th><th>Data</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <tr><td><strong>PRT-2026-0501</strong></td><td>Solicitação de orçamento</td><td>28/04/2026</td><td><span class="status progress">Em análise</span></td><td><a href="<?= route_url('administrativo', 'protocoloVisualizar') ?>" class="btn-outline">Ver</a></td></tr>
                        <tr><td><strong>ORC-2026-0101</strong></td><td>Orçamento administrativo</td><td>29/04/2026</td><td><span class="status ok">Concluído</span></td><td><a href="<?= route_url('administrativo', 'orcamentoVisualizar') ?>" class="btn-outline">Ver</a></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
