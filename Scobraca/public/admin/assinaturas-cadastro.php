<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Cadastrar assinatura';
$pageDescription = 'Vincule uma empresa locatária a um plano comercial.';

$pdo = db();
$empresas = $pdo->query("SELECT id, nome FROM empresas WHERE status IN ('teste', 'ativa') ORDER BY nome ASC")->fetchAll();
$planos = $pdo->query('SELECT id, nome, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC')->fetchAll();
$hoje = date('Y-m-d');
$vencimentoPadrao = date('Y-m-d', strtotime('+30 days'));
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
                    <h2>Nova assinatura</h2>
                    <p class="muted">Cadastre manualmente um contrato entre empresa e plano.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/admin/assinaturas.php')) ?>">Voltar para listagem</a>
            </div>

            <form method="post" action="<?= e(public_url('/actions/admin/salvar_assinatura.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Empresa
                    <select name="empresa_id" required>
                        <option value="">Selecione uma empresa</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= (int) $empresa['id'] ?>"><?= e($empresa['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plano
                    <select name="plano_id" required>
                        <option value="">Selecione um plano</option>
                        <?php foreach ($planos as $plano): ?>
                            <option value="<?= (int) $plano['id'] ?>"><?= e($plano['nome']) ?> - <?= moeda_br((float) $plano['preco']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="teste">Teste</option>
                        <option value="ativa">Ativa</option>
                        <option value="vencida">Vencida</option>
                        <option value="bloqueada">Bloqueada</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </label>
                <label>Valor<input name="valor" required placeholder="149,90"></label>
                <label>Data de início<input type="date" name="data_inicio" required value="<?= e($hoje) ?>"></label>
                <label>Data de vencimento<input type="date" name="data_vencimento" required value="<?= e($vencimentoPadrao) ?>"></label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar assinatura</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
