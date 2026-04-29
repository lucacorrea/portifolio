<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Cadastrar Cliente';
$paginaDescricao = 'Registre os dados básicos do cliente para uso nos protocolos e orçamentos.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Clientes';
$linkBotaoAcao = route_url('administrativo', 'clientes');
$tituloPagina = 'Administrativo - Cadastrar Cliente';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="card panel">
            <div class="panel-header">
                <div><h2>Dados cadastrais</h2><p>Informe os dados de identificação e contato do cliente.</p></div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group"><label for="nome">Nome completo</label><input type="text" id="nome" name="nome" placeholder="Digite o nome do cliente"></div>
                <div class="form-group"><label for="documento">CPF / CNPJ</label><input type="text" id="documento" name="documento" placeholder="000.000.000-00"></div>
                <div class="form-group"><label for="telefone">Telefone</label><input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000"></div>
                <div class="form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="cliente@email.com"></div>
                <div class="form-group col-2"><label for="endereco">Endereço</label><input type="text" id="endereco" name="endereco" placeholder="Rua, número, bairro, cidade"></div>
                <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option>Ativo</option><option>Pendente</option><option>Prioritário</option><option>Em análise</option></select></div>
                <div class="form-group"><label for="origem">Origem do cadastro</label><select id="origem" name="origem"><option>Recepção</option><option>Administrativo</option><option>Indicação</option></select></div>
                <div class="form-group col-2"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="4" placeholder="Informações relevantes para atendimento futuro"></textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'clientes') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Cliente</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
