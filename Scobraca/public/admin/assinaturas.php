<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Assinaturas';
$pageDescription = 'Listagem das empresas pagantes, em teste, vencidas ou bloqueadas.';
$assinaturas = db()->query(
    "SELECT a.*, e.nome AS empresa, p.nome AS plano
     FROM assinaturas a
     INNER JOIN empresas e ON e.id = a.empresa_id
     INNER JOIN planos p ON p.id = a.plano_id
     ORDER BY a.data_vencimento ASC"
)->fetchAll();

$formatarData = static function (?string $data): string {
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};
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
                    <h2>Assinaturas cadastradas</h2>
                    <p class="muted">Contratos ativos, testes, vencimentos e bloqueios financeiros.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/admin/assinaturas-cadastro.php')) ?>">Cadastrar assinatura</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Valor</th><th>Início</th><th>Vencimento</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assinaturas as $a): ?>
                        <tr>
                            <td><strong><?= e($a['empresa']) ?></strong></td>
                            <td><?= e($a['plano']) ?></td>
                            <td><span class="badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
                            <td><strong><?= moeda_br((float) $a['valor']) ?></strong></td>
                            <td><?= e($formatarData($a['data_inicio'])) ?></td>
                            <td><?= e($formatarData($a['data_vencimento'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assinaturas): ?>
                        <tr><td colspan="6">Nenhuma assinatura cadastrada.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
