<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Gerar cobrança';
$pageDescription = 'Crie mensalidades fixas ou parcelamentos para clientes da empresa.';
$empresaId = current_empresa_id();

$stmt = db()->prepare("SELECT id, nome, valor_mensalidade, dia_vencimento FROM clientes WHERE empresa_id = :empresa_id AND status IN ('ativo','pendente') ORDER BY nome ASC");
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll();
$referenciaAtual = date('Y-m');
$vencimentoPadrao = date('Y-m-d', strtotime('+7 days'));
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
                    <p class="muted">Escolha entre mensalidade fixa ou parcelamento com entrada opcional.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/app/cobrancas.php')) ?>">Voltar para listagem</a>
            </div>
            <form class="form-grid billing-form" method="post" action="<?= e(public_url('/actions/app/salvar_cobranca.php')) ?>" data-cobranca-form>
                <?= csrf_field() ?>
                <label>Cliente
                    <select name="cliente_id" required data-client-select>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option
                                value="<?= (int) $cliente['id'] ?>"
                                data-valor="<?= e(number_format((float) $cliente['valor_mensalidade'], 2, ',', '.')) ?>"
                                data-dia="<?= (int) $cliente['dia_vencimento'] ?>"
                            >
                                <?= e($cliente['nome']) ?> · mensalidade <?= moeda_br((float) $cliente['valor_mensalidade']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tipo de cobrança
                    <select name="tipo" required data-billing-type>
                        <option value="mensalidade">Mensalidade fixa</option>
                        <option value="parcelada">Parcelamento</option>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="Em aberto">Em aberto</option>
                        <option value="Paga">Paga</option>
                        <option value="Vencida">Vencida</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </label>
                <label class="span-full">Descrição interna
                    <input name="descricao" maxlength="160" placeholder="Ex.: Mensalidade de maio, negociação do contrato, taxa de matrícula">
                </label>

                <div class="billing-mode-panel span-full" data-billing-section="mensalidade">
                    <div class="billing-mode-copy">
                        <strong>Mensalidade fixa</strong>
                        <span>Use quando o cliente paga o mesmo valor em todos os meses. Esta tela cria a cobrança de uma referência por vez.</span>
                    </div>
                    <div class="form-grid inner">
                        <label>Referência
                            <input name="referencia" value="<?= e($referenciaAtual) ?>" placeholder="YYYY-MM" data-required-on-active>
                        </label>
                        <label>Valor mensal
                            <input name="valor_mensalidade" placeholder="199,90" data-monthly-value data-required-on-active>
                        </label>
                        <label>Data de vencimento
                            <input type="date" name="data_vencimento" value="<?= e($vencimentoPadrao) ?>" data-monthly-due-date data-required-on-active>
                        </label>
                    </div>
                </div>

                <div class="billing-mode-panel span-full" data-billing-section="parcelada" hidden>
                    <div class="billing-mode-copy">
                        <strong>Parcelamento com ou sem entrada</strong>
                        <span>Exemplo: dívida de R$ 900,00 em 3 parcelas. Se houver entrada, ela abate o total e as parcelas do saldo começam no mês seguinte.</span>
                    </div>
                    <div class="form-grid inner">
                        <label>Valor total devido
                            <input name="valor_total" placeholder="900,00" inputmode="decimal" data-required-on-active>
                        </label>
                        <label>Entrada opcional
                            <input name="valor_entrada" placeholder="0,00" inputmode="decimal">
                        </label>
                        <label>Quantidade de parcelas do saldo
                            <input type="number" name="quantidade_parcelas" min="1" max="60" value="3" data-required-on-active>
                        </label>
                        <label>Vencimento da entrada ou primeira parcela
                            <input type="date" name="data_primeiro_vencimento" value="<?= e($vencimentoPadrao) ?>" data-required-on-active>
                        </label>
                    </div>
                </div>

                <div class="form-actions"><button type="submit" class="btn btn-primary">Salvar cobrança</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
