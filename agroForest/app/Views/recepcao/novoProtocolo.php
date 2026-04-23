<?php
$paginaAtual = 'novoProtocolo';
$paginaTitulo = 'Novo Protocolo';
$paginaDescricao = 'Cadastre o cliente, selecione o serviço e encaminhe o atendimento para análise administrativa.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Voltar ao Dashboard';
$linkBotaoAcao = route_url('recepcao', 'dashboard');
$tituloPagina = 'Recepção - Novo Protocolo';
$cssPagina = 'assets/css/recepcao/novo-protocolo.css';
$mostrarBusca = false;
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="form-card">
            <div class="section-header"><h2>Abertura de Protocolo</h2><p>Preencha os dados do cliente e registre a solicitação corretamente.</p></div>
            <form class="form-grid" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group col-2"><h3>Dados do Cliente</h3></div>
                <div class="form-group"><label for="nome_cliente">Nome do Cliente</label><input type="text" id="nome_cliente" name="nome_cliente" placeholder="Digite o nome completo"></div>
                <div class="form-group"><label for="cpf_cnpj">CPF / CNPJ</label><input type="text" id="cpf_cnpj" name="cpf_cnpj" placeholder="Digite o CPF ou CNPJ"></div>
                <div class="form-group"><label for="telefone">Telefone</label><input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000"></div>
                <div class="form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="cliente@email.com"></div>
                <div class="form-group col-2"><label for="endereco">Endereço</label><input type="text" id="endereco" name="endereco" placeholder="Rua, número, bairro, cidade..."></div>

                <div class="form-group col-2"><h3>Dados do Atendimento</h3></div>
                <div class="form-group"><label for="tipo_servico">Tipo de Serviço</label><select id="tipo_servico" name="tipo_servico"><option value="">Selecione</option><option>Orçamento</option><option>Análise documental</option><option>Cadastro de serviço</option><option>Revisão</option><option>Urgente</option></select></div>
                <div class="form-group"><label for="prioridade">Prioridade</label><select id="prioridade" name="prioridade"><option>Normal</option><option>Média</option><option>Alta</option><option>Urgente</option></select></div>
                <div class="form-group col-2"><label for="descricao">Descrição do Serviço</label><textarea id="descricao" name="descricao" rows="5" placeholder="Descreva a solicitação do cliente"></textarea></div>
                <div class="form-group col-2"><label for="observacoes">Observações da Recepção</label><textarea id="observacoes" name="observacoes" rows="4" placeholder="Observações importantes para o administrativo"></textarea></div>
                <div class="form-group col-2"><label for="anexos">Anexos</label><input type="file" id="anexos" name="anexos[]" multiple><small class="field-help">Você pode anexar documentos, imagens ou comprovantes do atendimento.</small></div>
                <div class="form-actions col-2"><a href="<?= route_url('recepcao', 'dashboard') ?>" class="btn-secondary">Cancelar</a><button type="submit" class="btn-primary">Salvar Protocolo</button></div>
            </form>
        </section>

        <section class="table-card compact-card">
            <div class="section-header"><h2>Orientações para a Recepção</h2><p>Antes de encaminhar para o administrativo, confirme os pontos abaixo.</p></div>
            <div class="info-grid">
                <div class="info-card"><strong>1. Conferir dados</strong><p>Verifique nome, telefone e tipo de solicitação antes de salvar.</p></div>
                <div class="info-card"><strong>2. Registrar observações</strong><p>Inclua tudo que possa ajudar o setor administrativo na elaboração do orçamento.</p></div>
                <div class="info-card"><strong>3. Validar anexos</strong><p>Se houver documento obrigatório, confira antes de encaminhar o protocolo.</p></div>
                <div class="info-card"><strong>4. Classificar prioridade</strong><p>Atendimentos urgentes devem seguir com destaque para o próximo setor.</p></div>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
