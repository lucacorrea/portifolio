<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Gerar cobrança';
$pageDescription = 'Crie uma mensalidade para um cliente da empresa.';
$empresaId = current_empresa_id();

$stmt = db()->prepare("SELECT id, nome, valor_mensalidade, dia_vencimento FROM clientes WHERE empresa_id = :empresa_id AND status IN ('ativo','pendente') ORDER BY nome ASC");
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll();
$referenciaAtual = date('Y-m');
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
                    <h2>Nova cobrança</h2>
                    <p class="muted">Selecione o cliente, referência e vencimento da mensalidade.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/app/cobrancas.php')) ?>">Voltar para listagem</a>
            </div>
            <form class="form-grid">
                <label>Cliente
                    <select name="cliente_id" required>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>"><?= e($cliente['nome']) ?> · <?= moeda_br((float) $cliente['valor_mensalidade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Referência<input name="referencia" required value="<?= e($referenciaAtual) ?>" placeholder="YYYY-MM"></label>
                <label>Valor<input name="valor" required placeholder="199,90"></label>
                <label>Data de vencimento<input type="date" name="data_vencimento" required value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>"></label>
                <label>Status
                    <select name="status">
                        <option value="Em aberto">Em aberto</option>
                        <option value="Paga">Paga</option>
                        <option value="Vencida">Vencida</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </label>
                <div class="form-actions"><button type="button" class="btn btn-primary">Gerar cobrança</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
