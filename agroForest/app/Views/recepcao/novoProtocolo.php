<?php
$paginaAtual = 'novoProtocolo';
$paginaTitulo = 'Novo Protocolo';
$paginaDescricao = 'Cadastre o cliente, selecione o serviço e encaminhe o atendimento para análise administrativa.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Voltar ao Dashboard';
$linkBotaoAcao = route_url('recepcao', 'dashboard');
$mostrarBusca = false;
$tituloPagina = 'Recepção - Novo Protocolo';
$cssPagina = 'assets/css/recepcao/novo-protocolo.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="page-section">
            <div class="card-box">
                <div class="card-header"><h2>Abertura de Protocolo</h2><p>Preencha os dados do cliente e do serviço solicitado.</p></div>
                <div class="card-body">
                    <form action="#" method="post" enctype="multipart/form-data" class="form-protocolo">
                        <div class="form-grid">
                            <div class="form-group form-col-2"><h3 class="section-title">Dados do Cliente</h3></div>
                            <div class="form-group"><label>Nome do Cliente</label><input type="text" name="nome_cliente"></div>
                            <div class="form-group"><label>CPF / CNPJ</label><input type="text" name="cpf_cnpj"></div>
                            <div class="form-group"><label>Telefone</label><input type="text" name="telefone"></div>
                            <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
                            <div class="form-group form-col-2"><label>Endereço</label><input type="text" name="endereco"></div>
                            <div class="form-group form-col-2"><h3 class="section-title">Dados do Atendimento</h3></div>
                            <div class="form-group"><label>Tipo de Serviço</label><select name="tipo_servico"><option>Selecione</option><option>Orçamento</option><option>Análise Documental</option></select></div>
                            <div class="form-group"><label>Prioridade</label><select name="prioridade"><option>Normal</option><option>Alta</option></select></div>
                            <div class="form-group form-col-2"><label>Descrição do Serviço</label><textarea name="descricao_servico"></textarea></div>
                            <div class="form-group form-col-2"><label>Observações</label><textarea name="observacoes"></textarea></div>
                        </div>
                        <div class="form-actions"><a href="<?= route_url('recepcao', 'dashboard') ?>" class="btn-secondary">Cancelar</a><button type="submit" class="btn-primary">Salvar Protocolo</button></div>
                    </form>
                </div>
            </div>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
