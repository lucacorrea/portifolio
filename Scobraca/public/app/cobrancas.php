<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Cobranças';
$pageDescription = 'Controle de mensalidades e vencimentos.';
$empresaId = current_empresa_id();

$stmt = db()->prepare(
    "SELECT cb.*, c.nome AS cliente
     FROM cobrancas cb
     INNER JOIN clientes c ON c.id = cb.cliente_id
     WHERE cb.empresa_id = :empresa_id
     ORDER BY cb.data_vencimento DESC, cb.id DESC"
);
$stmt->execute([':empresa_id' => $empresaId]);
$cobrancas = $stmt->fetchAll();
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
                    <h2>Cobranças cadastradas</h2>
                    <p class="muted">Mensalidades geradas por cliente, referência e status financeiro.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/app/cobrancas-cadastro.php')) ?>">Gerar cobrança</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Cliente</th><th>Referência</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Criada em</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cobrancas as $cobranca): ?>
                        <tr>
                            <td><strong><?= e($cobranca['cliente']) ?></strong></td>
                            <td><?= e($cobranca['referencia']) ?></td>
                            <td><?= moeda_br((float) $cobranca['valor']) ?></td>
                            <td><?= e(data_br($cobranca['data_vencimento'])) ?></td>
                            <td><span class="badge <?= $cobranca['status'] === 'Vencida' ? 'vencida' : ($cobranca['status'] === 'Paga' ? 'ativa' : 'pendente') ?>"><?= e($cobranca['status']) ?></span></td>
                            <td><?= e(data_br($cobranca['criado_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cobrancas): ?>
                        <tr><td colspan="6">Nenhuma cobrança cadastrada.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
