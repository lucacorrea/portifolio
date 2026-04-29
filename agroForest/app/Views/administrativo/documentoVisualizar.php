<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Visualizar Documento';
$paginaDescricao = 'Valide informações do documento anexado ao protocolo.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Documentos';
$linkBotaoAcao = route_url('administrativo', 'documentos');
$tituloPagina = 'Administrativo - Visualizar Documento';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>anexo_prioritario_fernanda.jpg</h2><p>Documento vinculado ao protocolo PRT-2026-0502.</p></div>
                <span class="status pending">Pendente</span>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Cliente</h3><p>Fernanda Martins - Atendimento prioritário.</p></div>
                <div class="setting-block"><h3>Tipo e envio</h3><p>Imagem enviada em 28/04/2026 às 09:16 pela recepção.</p></div>
                <div class="setting-block"><h3>Validação</h3><p>Arquivo aguardando conferência de legibilidade e aderência ao protocolo.</p></div>
            </div>
        </section>

        <section class="card panel compact-card">
            <div class="panel-header"><div><h2>Checklist de validação</h2><p>Marque os pontos conferidos antes de liberar o orçamento.</p></div></div>
            <form class="form-grid" method="POST" action="">
                <div class="col-2 config-grid">
                    <div class="switch-field"><div class="switch-field-info"><strong>Arquivo legível</strong><small>Conteúdo pode ser lido sem perda de informação.</small></div><label class="switch"><input type="checkbox" name="legivel"><span class="switch-slider"></span></label></div>
                    <div class="switch-field"><div class="switch-field-info"><strong>Documento corresponde ao cliente</strong><small>Nome e dados batem com o cadastro.</small></div><label class="switch"><input type="checkbox" name="cliente"><span class="switch-slider"></span></label></div>
                    <div class="switch-field"><div class="switch-field-info"><strong>Liberado para orçamento</strong><small>Anexo pode seguir no fluxo administrativo.</small></div><label class="switch"><input type="checkbox" name="liberado"><span class="switch-slider"></span></label></div>
                </div>
                <div class="form-group col-2"><label for="observacoes">Observações da validação</label><textarea id="observacoes" name="observacoes" rows="4" placeholder="Registre inconsistências ou comentários"></textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'documentos') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Validação</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
