<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Registrar pagamento';
$pageDescription = 'Confirme um recebimento de cliente.';
$empresaId = current_empresa_id();
$pdo = db();

$stmt = $pdo->prepare("SELECT id, nome FROM clientes WHERE empresa_id = :empresa_id AND status <> 'cancelado' ORDER BY nome ASC");
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT cb.id, cb.referencia, cb.valor, c.nome AS cliente
     FROM cobrancas cb
     INNER JOIN clientes c ON c.id = cb.cliente_id
     WHERE cb.empresa_id = :empresa_id AND cb.status IN ('Em aberto','Vencida')
     ORDER BY cb.data_vencimento ASC"
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
                    <h2>Novo pagamento</h2>
                    <p class="muted">Registre valor recebido, forma de pagamento e cobrança vinculada.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/app/pagamentos.php')) ?>">Voltar para listagem</a>
            </div>
            <form class="form-grid">
                <label>Cliente
                    <select name="cliente_id" required>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>"><?= e($cliente['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Cobrança
                    <select name="cobranca_id">
                        <option value="">Sem vínculo</option>
                        <?php foreach ($cobrancas as $cobranca): ?>
                            <option value="<?= (int) $cobranca['id'] ?>"><?= e($cobranca['cliente']) ?> · <?= e($cobranca['referencia']) ?> · <?= moeda_br((float) $cobranca['valor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Valor pago<input name="valor_pago" required placeholder="199,90"></label>
                <label>Data do pagamento<input type="date" name="data_pagamento" required value="<?= e(date('Y-m-d')) ?>"></label>
                <label>Forma de pagamento
                    <select name="forma_pagamento">
                        <option value="PIX">PIX</option>
                        <option value="Cartão">Cartão</option>
                        <option value="Boleto">Boleto</option>
                        <option value="Dinheiro">Dinheiro</option>
                    </select>
                </label>
                <label>Observação<textarea name="observacao" rows="3" placeholder="Detalhes internos"></textarea></label>
                <div class="form-actions"><button type="button" class="btn btn-primary">Registrar pagamento</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
