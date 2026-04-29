<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Cadastrar Pendência';
$paginaDescricao = 'Abra uma pendência vinculada a protocolo, cliente ou documento.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Pendências';
$linkBotaoAcao = route_url('administrativo', 'pendencias');
$tituloPagina = 'Administrativo - Cadastrar Pendência';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Nova pendência</h2><p>Registre o bloqueio e defina prioridade, origem e responsáveis.</p></div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="protocolo">Protocolo</label><select id="protocolo" name="protocolo"><option>PRT-2026-0502</option><option>PRT-2026-0503</option><option>PRT-2026-0505</option></select></div>
                <div class="form-group"><label for="cliente">Cliente</label><input type="text" id="cliente" name="cliente" placeholder="Nome do cliente"></div>
                <div class="form-group"><label for="origem">Origem</label><select id="origem" name="origem"><option>Recepção</option><option>Administrativo</option><option>Cliente</option></select></div>
                <div class="form-group"><label for="prioridade">Prioridade</label><select id="prioridade" name="prioridade"><option>Normal</option><option>Média</option><option>Alta</option></select></div>
                <div class="form-group"><label for="status">Status inicial</label><select id="status" name="status"><option>Pendente</option><option>Aguardando cliente</option><option>Aguardando reenvio</option><option>Em revisão</option></select></div>
                <div class="form-group"><label for="prazo">Prazo de resolução</label><input type="date" id="prazo" name="prazo"></div>
                <div class="form-group col-2"><label for="motivo">Motivo</label><input type="text" id="motivo" name="motivo" placeholder="Resumo objetivo da pendência"></div>
                <div class="form-group col-2"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" rows="5" placeholder="Explique o que precisa ser corrigido ou complementado"></textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'pendencias') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Pendência</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
