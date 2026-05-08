<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Cadastrar plano';
$pageDescription = 'Defina preço, limites e recursos do plano.';
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
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Novo plano</h2>
                    <p class="muted">Defina limites e recursos que serão usados no contrato da empresa locatária.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/admin/planos.php')) ?>">Voltar para listagem</a>
            </div>
            <form method="post" action="<?= e(public_url('/actions/admin/salvar_plano.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome<input name="nome" required placeholder="Básico, Profissional, Premium"></label>
                <label>Preço<input name="preco" required placeholder="99,90"></label>
                <label>Limite de clientes<input type="number" name="limite_clientes" min="0" placeholder="ex: 50"></label>
                <label>Limite de usuários<input type="number" name="limite_usuarios" min="0" placeholder="ex: 3"></label>
                <label class="check"><input type="checkbox" name="whatsapp_ativo" checked> WhatsApp ativo</label>
                <label class="check"><input type="checkbox" name="leitura_comprovante" checked> Leitura de comprovante</label>
                <label class="check"><input type="checkbox" name="relatorios_avancados" checked> Relatórios avançados</label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar plano</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
