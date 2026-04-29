<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Editar Pendência';
$paginaDescricao = 'Atualize a pendência conforme retorno do cliente ou revisão interna.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Visualizar Pendência';
$linkBotaoAcao = route_url('administrativo', 'pendenciaVisualizar');
$tituloPagina = 'Administrativo - Editar Pendência';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid stats-grid-mini">
            <article class="card stat-card"><h3>PRT-2026-0502</h3><p>Protocolo vinculado</p></article>
            <article class="card stat-card"><h3>Alta</h3><p>Prioridade atual</p></article>
            <article class="card stat-card"><h3>Aguardando cliente</h3><p>Status atual</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Edição da pendência</h2><p>Registre a evolução e mantenha a fila atualizada.</p></div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="protocolo">Protocolo</label><input type="text" id="protocolo" name="protocolo" value="PRT-2026-0502" readonly></div>
                <div class="form-group"><label for="cliente">Cliente</label><input type="text" id="cliente" name="cliente" value="Fernanda Martins"></div>
                <div class="form-group"><label for="origem">Origem</label><select id="origem" name="origem"><option selected>Recepção</option><option>Administrativo</option><option>Cliente</option></select></div>
                <div class="form-group"><label for="prioridade">Prioridade</label><select id="prioridade" name="prioridade"><option>Normal</option><option>Média</option><option selected>Alta</option></select></div>
                <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option>Pendente</option><option selected>Aguardando cliente</option><option>Aguardando reenvio</option><option>Em revisão</option><option>Resolvida</option></select></div>
                <div class="form-group"><label for="prazo">Prazo de resolução</label><input type="date" id="prazo" name="prazo" value="2026-04-30"></div>
                <div class="form-group col-2"><label for="motivo">Motivo</label><input type="text" id="motivo" name="motivo" value="Documento complementar não enviado"></div>
                <div class="form-group col-2"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" rows="5">A documentação complementar precisa ser enviada pelo cliente para liberar a validação final do orçamento.</textarea></div>
                <div class="form-group col-2"><label for="retorno">Último retorno</label><textarea id="retorno" name="retorno" rows="4">Cliente avisado pela recepção. Aguardando envio do documento complementar.</textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'pendencias') ?>" class="btn-secondary">Cancelar</a>
                    <a href="<?= route_url('administrativo', 'pendenciaVisualizar') ?>" class="btn-outline">Visualizar</a>
                    <button type="submit" class="btn-primary">Atualizar Pendência</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
