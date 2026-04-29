<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Editar Cliente';
$paginaDescricao = 'Atualize os dados cadastrais e a situação administrativa do cliente.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Visualizar Cliente';
$linkBotaoAcao = route_url('administrativo', 'clienteVisualizar');
$tituloPagina = 'Administrativo - Editar Cliente';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3>Carlos Henrique</h3><p>Cliente selecionado</p></article>
            <article class="card stat-card"><h3>PRT-2026-0501</h3><p>Último protocolo</p></article>
            <article class="card stat-card"><h3>Ativo</h3><p>Status atual</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Edição do cliente</h2><p>Mantenha os dados consistentes com os protocolos vinculados.</p></div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="nome">Nome completo</label><input type="text" id="nome" name="nome" value="Carlos Henrique"></div>
                <div class="form-group"><label for="documento">CPF / CNPJ</label><input type="text" id="documento" name="documento" value="123.456.789-00"></div>
                <div class="form-group"><label for="telefone">Telefone</label><input type="text" id="telefone" name="telefone" value="(92) 99999-1020"></div>
                <div class="form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" value="carlos@email.com"></div>
                <div class="form-group col-2"><label for="endereco">Endereço</label><input type="text" id="endereco" name="endereco" value="Rua das Castanheiras, 120, Manaus"></div>
                <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option selected>Ativo</option><option>Pendente</option><option>Prioritário</option><option>Em análise</option></select></div>
                <div class="form-group"><label for="ultimo_protocolo">Último protocolo</label><input type="text" id="ultimo_protocolo" name="ultimo_protocolo" value="PRT-2026-0501" readonly></div>
                <div class="form-group col-2"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="4">Cliente com histórico regular e documentação validada no último atendimento.</textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'clientes') ?>" class="btn-secondary">Cancelar</a>
                    <a href="<?= route_url('administrativo', 'clienteVisualizar') ?>" class="btn-outline">Visualizar</a>
                    <button type="submit" class="btn-primary">Atualizar Cliente</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
