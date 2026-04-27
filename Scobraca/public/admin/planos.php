<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Planos do SaaS';
$pageDescription = 'Cadastre os planos que serão vendidos para as empresas.';
$planos = db()->query('SELECT * FROM planos ORDER BY preco ASC')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>
        <section class="card">
            <h2>Novo plano</h2>
            <form method="post" action="/actions/admin/salvar_plano.php" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome<input name="nome" required placeholder="Básico, Profissional, Premium"></label>
                <label>Preço<input name="preco" required placeholder="99,90"></label>
                <label>Limite de clientes<input type="number" name="limite_clientes" placeholder="ex: 50"></label>
                <label>Limite de usuários<input type="number" name="limite_usuarios" placeholder="ex: 3"></label>
                <label class="check"><input type="checkbox" name="whatsapp_ativo" checked> WhatsApp ativo</label>
                <label class="check"><input type="checkbox" name="leitura_comprovante" checked> Leitura de comprovante</label>
                <label class="check"><input type="checkbox" name="relatorios_avancados" checked> Relatórios avançados</label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar plano</button></div>
            </form>
        </section>
        <section class="grid three">
            <?php foreach ($planos as $plano): ?>
                <article class="card plan">
                    <h3><?= e($plano['nome']) ?></h3>
                    <strong><?= moeda_br((float) $plano['preco']) ?></strong>
                    <p>Clientes: <?= $plano['limite_clientes'] ?: 'Ilimitado' ?></p>
                    <p>Usuários: <?= $plano['limite_usuarios'] ?: 'Ilimitado' ?></p>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
</body>
</html>
