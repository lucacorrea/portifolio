<?php
$paginaAtual = 'tiposServicos';
$paginaTitulo = 'Cadastrar Tipo de Serviço';
$paginaDescricao = 'Defina nome, categoria, prazo, valor base e regras do serviço.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Voltar para Serviços';
$linkBotaoAcao = route_url('dono', 'tiposServicos');
$tituloPagina = 'Dono - Cadastrar Tipo de Serviço';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="card panel">
            <div class="panel-header"><div><h2>Dados do serviço</h2><p>Essas informações aparecem no protocolo e ajudam a montar o orçamento.</p></div></div>
            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="nome">Nome do serviço</label><input id="nome" name="nome" type="text" placeholder="Ex: Licenciamento Ambiental"></div>
                <div class="form-group"><label for="categoria">Categoria</label><select id="categoria" name="categoria"><option>Ambiental</option><option>Rural</option><option>Técnico</option><option>Consultoria</option></select></div>
                <div class="form-group"><label for="setor">Setor responsável</label><select id="setor" name="setor"><option>Recepção</option><option>Administrativo</option><option>Dono</option></select></div>
                <div class="form-group"><label for="prazo">Prazo padrão</label><input id="prazo" name="prazo" type="text" placeholder="Ex: 5 dias úteis"></div>
                <div class="form-group"><label for="valor">Valor base</label><input id="valor" name="valor" type="text" placeholder="R$ 0,00"></div>
                <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option>Ativo</option><option>Em revisão</option><option>Inativo</option></select></div>
                <div class="form-group col-2"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" placeholder="Explique quando este serviço deve ser usado"></textarea></div>
                <div class="form-group col-2"><label for="documentos">Documentos necessários</label><textarea id="documentos" name="documentos" placeholder="Liste os documentos solicitados na recepção"></textarea></div>
                <div class="form-actions col-2"><a href="<?= route_url('dono', 'tiposServicos') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Serviço</button></div>
            </form>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
