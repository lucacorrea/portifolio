<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Planos do SaaS';
$pageDescription = 'Listagem dos planos vendidos para empresas locatárias.';
$planos = db()->query('SELECT * FROM planos ORDER BY preco ASC')->fetchAll();
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
                    <h2>Planos cadastrados</h2>
                    <p class="muted">Lista dos pacotes comerciais disponíveis para contratação no FluxPay.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/admin/planos-cadastro.php')) ?>">Cadastrar plano</a>
            </div>
        </section>

        <section class="grid three">
            <?php foreach ($planos as $plano): ?>
                <article class="card plan plan-card">
                    <div class="section-heading">
                        <div>
                            <h3><?= e($plano['nome']) ?></h3>
                            <p><?= (int) $plano['ativo'] === 1 ? 'Disponível para venda' : 'Fora do catálogo' ?></p>
                        </div>
                        <span class="soft-label <?= (int) $plano['ativo'] === 1 ? 'success' : 'danger' ?>"><?= (int) $plano['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                    </div>
                    <strong><?= moeda_br((float) $plano['preco']) ?></strong>
                    <ul class="plan-feature-list">
                        <li><?= $plano['limite_clientes'] ? 'Até ' . (int) $plano['limite_clientes'] . ' clientes' : 'Clientes ilimitados' ?></li>
                        <li><?= $plano['limite_usuarios'] ? 'Até ' . (int) $plano['limite_usuarios'] . ' usuários' : 'Usuários ilimitados' ?></li>
                        <li class="<?= (int) $plano['whatsapp_ativo'] === 1 ? '' : 'feature-disabled' ?>">WhatsApp <?= (int) $plano['whatsapp_ativo'] === 1 ? 'incluído' : 'não incluído' ?></li>
                        <li class="<?= (int) $plano['leitura_comprovante'] === 1 ? '' : 'feature-disabled' ?>">Leitura de comprovantes <?= (int) $plano['leitura_comprovante'] === 1 ? 'incluída' : 'indisponível' ?></li>
                        <li class="<?= (int) $plano['relatorios_avancados'] === 1 ? '' : 'feature-disabled' ?>">Relatórios avançados <?= (int) $plano['relatorios_avancados'] === 1 ? 'incluídos' : 'indisponíveis' ?></li>
                    </ul>
                </article>
            <?php endforeach; ?>
            <?php if (!$planos): ?>
                <article class="empty-state">Nenhum plano cadastrado ainda.</article>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
