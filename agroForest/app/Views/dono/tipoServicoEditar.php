<?php
$paginaAtual = 'tiposServicos';
$paginaTitulo = 'Editar Tipo de Serviço';
$paginaDescricao = 'Atualize regras, valores e documentos do serviço.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Voltar para Serviços';
$linkBotaoAcao = route_url('dono', 'tiposServicos');
$tituloPagina = 'Dono - Editar Tipo de Serviço';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="card panel">
            <div class="panel-header"><div><h2>Licenciamento Ambiental</h2><p>Revise as configurações atuais antes de salvar.</p></div></div>
            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="nome">Nome do serviço</label><input id="nome" name="nome" type="text" value="Licenciamento Ambiental"></div>
                <div class="form-group"><label for="categoria">Categoria</label><select id="categoria" name="categoria"><option selected>Ambiental</option><option>Rural</option><option>Técnico</option><option>Consultoria</option></select></div>
                <div class="form-group"><label for="setor">Setor responsável</label><select id="setor" name="setor"><option>Recepção</option><option selected>Administrativo</option><option>Dono</option></select></div>
                <div class="form-group"><label for="prazo">Prazo padrão</label><input id="prazo" name="prazo" type="text" value="5 dias úteis"></div>
                <div class="form-group"><label for="valor">Valor base</label><input id="valor" name="valor" type="text" value="R$ 1.850,00"></div>
                <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option selected>Ativo</option><option>Em revisão</option><option>Inativo</option></select></div>
                <div class="form-group col-2"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao">Análise documental, abertura de protocolo ambiental e acompanhamento do processo.</textarea></div>
                <div class="form-group col-2"><label for="documentos">Documentos necessários</label><textarea id="documentos" name="documentos">CPF/CNPJ, documentos da propriedade, mapa ou croqui, comprovante de endereço.</textarea></div>
                <div class="form-actions col-2"><a href="<?= route_url('dono', 'tiposServicos') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Alterações</button></div>
            </form>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
