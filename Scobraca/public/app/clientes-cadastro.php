<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Cadastrar cliente';
$pageDescription = 'Inclua um cliente na carteira da empresa.';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Novo cliente</h2>
                    <p class="muted">Dados principais usados para cobranças, mensagens e controle de inadimplência.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/app/clientes.php')) ?>">Voltar para listagem</a>
            </div>
            <form class="form-grid">
                <label>Nome<input name="nome" required placeholder="Nome completo ou razão social"></label>
                <label>Telefone<input name="telefone" placeholder="(00) 00000-0000"></label>
                <label>E-mail<input type="email" name="email" placeholder="cliente@email.com"></label>
                <label>Documento<input name="documento" placeholder="CPF ou CNPJ"></label>
                <label>Quantidade de veículos<input type="number" name="quantidade_veiculos" min="0" value="1"></label>
                <label>Mensalidade<input name="valor_mensalidade" placeholder="199,90"></label>
                <label>Dia de vencimento<input type="number" name="dia_vencimento" min="1" max="31" value="10"></label>
                <label>Status
                    <select name="status">
                        <option value="ativo">Ativo</option>
                        <option value="pendente">Pendente</option>
                        <option value="bloqueado">Bloqueado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </label>
                <div class="form-actions"><button type="button" class="btn btn-primary">Salvar cliente</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
