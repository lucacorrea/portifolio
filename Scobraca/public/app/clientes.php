<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Clientes';
$pageDescription = 'Carteira de clientes da empresa.';
$empresaId = current_empresa_id();

$stmt = db()->prepare('SELECT * FROM clientes WHERE empresa_id = :empresa_id ORDER BY id DESC');
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll();
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
                    <h2>Clientes cadastrados</h2>
                    <p class="muted">Controle da base de clientes, mensalidades, vencimentos e status.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/app/clientes-cadastro.php')) ?>">Cadastrar cliente</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Cliente</th><th>Contato</th><th>Documento</th><th>Veículos</th><th>Mensalidade</th><th>Vencimento</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><strong><?= e($cliente['nome']) ?></strong></td>
                            <td><?= e($cliente['telefone']) ?><br><small><?= e($cliente['email']) ?></small></td>
                            <td><?= e($cliente['documento']) ?></td>
                            <td><?= (int) $cliente['quantidade_veiculos'] ?></td>
                            <td><?= moeda_br((float) $cliente['valor_mensalidade']) ?></td>
                            <td>Dia <?= (int) $cliente['dia_vencimento'] ?></td>
                            <td><span class="badge <?= e($cliente['status']) ?>"><?= e($cliente['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clientes): ?>
                        <tr><td colspan="7">Nenhum cliente cadastrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
