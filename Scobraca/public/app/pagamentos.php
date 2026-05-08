<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Pagamentos';
$pageDescription = 'Recebimentos confirmados da empresa.';
$empresaId = current_empresa_id();

$stmt = db()->prepare(
    "SELECT p.*, c.nome AS cliente, cb.referencia
     FROM pagamentos p
     INNER JOIN clientes c ON c.id = p.cliente_id
     LEFT JOIN cobrancas cb ON cb.id = p.cobranca_id
     WHERE p.empresa_id = :empresa_id
     ORDER BY p.data_pagamento DESC, p.id DESC"
);
$stmt->execute([':empresa_id' => $empresaId]);
$pagamentos = $stmt->fetchAll();
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
                    <h2>Pagamentos registrados</h2>
                    <p class="muted">Histórico de recebimentos, formas de pagamento e vínculos com cobranças.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/app/pagamentos-cadastro.php')) ?>">Registrar pagamento</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Cliente</th><th>Referência</th><th>Valor pago</th><th>Data</th><th>Forma</th><th>Observação</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagamentos as $pagamento): ?>
                        <tr>
                            <td><strong><?= e($pagamento['cliente']) ?></strong></td>
                            <td><?= e($pagamento['referencia'] ?? '-') ?></td>
                            <td><?= moeda_br((float) $pagamento['valor_pago']) ?></td>
                            <td><?= e(data_br($pagamento['data_pagamento'])) ?></td>
                            <td><span class="soft-label success"><?= e($pagamento['forma_pagamento']) ?></span></td>
                            <td><?= e($pagamento['observacao']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pagamentos): ?>
                        <tr><td colspan="6">Nenhum pagamento registrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
