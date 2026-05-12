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
    "SELECT
        cb.id,
        cb.cliente_id,
        cb.referencia,
        cb.valor,
        cb.tipo,
        cb.numero_parcela,
        cb.total_parcelas,
        c.nome AS cliente,
        COALESCE(SUM(p.valor_pago), 0) AS total_pago,
        cb.valor - COALESCE(SUM(p.valor_pago), 0) AS saldo
     FROM cobrancas cb
     INNER JOIN clientes c ON c.id = cb.cliente_id
     LEFT JOIN pagamentos p ON p.cobranca_id = cb.id AND p.empresa_id = cb.empresa_id
     WHERE cb.empresa_id = :empresa_id AND cb.status IN ('Em aberto','Vencida')
     GROUP BY cb.id, cb.cliente_id, cb.referencia, cb.valor, cb.tipo, cb.numero_parcela, cb.total_parcelas, c.nome
     HAVING saldo > 0
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
            <form class="form-grid" method="post" action="<?= e(public_url('/actions/app/salvar_pagamento.php')) ?>" data-payment-form>
                <?= csrf_field() ?>
                <label>Cliente
                    <select name="cliente_id" required data-payment-client>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>"><?= e($cliente['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Cobrança
                    <select name="cobranca_id" data-payment-charge>
                        <option value="">Sem vínculo / pagamento avulso</option>
                        <?php foreach ($cobrancas as $cobranca): ?>
                            <?php
                            $tipoCobranca = (string) ($cobranca['tipo'] ?? 'mensalidade');
                            $detalheCobranca = $tipoCobranca === 'parcelada'
                                ? (((int) ($cobranca['numero_parcela'] ?? 1)) === 0 ? 'entrada' : 'parcela ' . (int) $cobranca['numero_parcela'] . '/' . (int) $cobranca['total_parcelas'])
                                : 'mensalidade';
                            $saldo = (float) $cobranca['saldo'];
                            ?>
                            <option
                                value="<?= (int) $cobranca['id'] ?>"
                                data-cliente-id="<?= (int) $cobranca['cliente_id'] ?>"
                                data-saldo="<?= e(number_format($saldo, 2, ',', '.')) ?>"
                                data-saldo-label="<?= e(moeda_br($saldo)) ?>"
                                data-valor-label="<?= e(moeda_br((float) $cobranca['valor'])) ?>"
                            >
                                <?= e($cobranca['cliente']) ?> · <?= e($detalheCobranca) ?> · <?= e($cobranca['referencia']) ?> · saldo <?= moeda_br($saldo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Valor recebido
                    <input name="valor_pago" required placeholder="199,90" inputmode="decimal" data-payment-amount>
                </label>
                <div class="payment-helper span-full" data-payment-helper>
                    <strong>Pagamento parcial permitido</strong>
                    <span>Selecione uma cobrança e informe o valor recebido. Pode ser o saldo total ou apenas uma parte da parcela.</span>
                </div>
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
                <div class="form-actions"><button type="submit" class="btn btn-primary">Registrar pagamento</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
