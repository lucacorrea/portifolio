<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

require_once __DIR__ . '/includes/ui.php';

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

try {
    $authorization = $application->authorization();
    $currentUser = $authorization->requireLogin();
    $authorization->requirePermission('caixa.registrar_venda');
} catch (AuthenticationException) {
    header('Location: ' . $application->redirect()->loginUrl(), true, 303);
    exit;
} catch (AuthorizationException) {
    header('Location: acesso-negado.php', true, 303);
    exit;
}

$csrf = $application->csrf();
$cash = $application->cashManagement();
$cashSession = $cash->currentSession();
$products = $cashSession === null ? [] : $cash->availableProducts();
$settings = $application->companySettings()->get();
$companyName = trim((string) ($settings['nome_fantasia'] ?? $settings['razao_social'] ?? '')) ?: 'K. Yamaguchi';
$companyDocument = trim((string) ($settings['documento'] ?? ''));
$canSearchClients = $authorization->can('cliente.visualizar');
$returnPage = $authorization->can('caixa.visualizar') ? 'caixa.php' : 'dashboard.php';
$productPayload = array_map(static fn(array $product): array => [
    'id' => (int) $product['id'], 'code' => (string) ($product['codigo'] ?? ''),
    'barcode' => (string) ($product['codigo_barras'] ?? ''), 'name' => (string) $product['nome'],
    'unit' => (string) $product['unidade'], 'price' => (string) $product['preco_venda'],
    'stock' => (string) $product['estoque'],
], $products);
$flashMessages = $session->consumeFlashMessages();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($companyName) ?> — Frente de Caixa</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/frente-caixa.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/frente-caixa.css') ?>">
</head>
<body class="pdv-body">
<div class="pdv-app" id="pdv-app">
  <header class="pdv-topbar">
    <div class="pdv-brand"><i class="bi bi-shop-window"></i><div><strong><?= h($companyName) ?> — PDV</strong><?php if ($companyDocument !== ''): ?><small>CNPJ/CPF: <?= h($companyDocument) ?></small><?php endif; ?></div></div>
    <div class="pdv-shortcuts" aria-label="Atalhos do teclado"><span><kbd>F2</kbd> Quantidade</span><b>•</b><span><kbd>F3</kbd> Desconto</span><b>•</b><span><kbd>Enter</kbd> Adicionar</span><b>•</b><span><kbd>F4</kbd> Finalizar</span><b>•</b><span><kbd>F6</kbd> Recebido</span></div>
    <div class="pdv-top-actions"><span class="pdv-status <?= $cashSession === null ? 'is-closed' : '' ?>"><i></i><?= $cashSession === null ? 'CAIXA FECHADO' : h((string) $cashSession['codigo']) . ' ABERTO' ?></span><a href="<?= h($returnPage) ?>"><i class="bi bi-box-arrow-left"></i> Voltar</a></div>
  </header>

  <?php if ($flashMessages !== []): ?><div class="pdv-flashes" role="status" aria-live="polite"><?php foreach ($flashMessages as $message): $type = in_array(($message['type'] ?? ''), ['success','info','warning','danger'], true) ? $message['type'] : 'info'; ?><div class="alert alert-<?= h($type) ?> mb-2"><?= h((string) ($message['message'] ?? '')) ?></div><?php endforeach; ?></div><?php endif; ?>

  <?php if ($cashSession === null): ?>
    <main class="pdv-blocked"><div><i class="bi bi-lock"></i><h1>O Caixa está fechado</h1><p>Uma pessoa com permissão de alto nível precisa abrir a sessão antes de iniciar vendas.</p><a href="<?= h($returnPage) ?>"><?= $returnPage === 'caixa.php' ? 'Ir para o controle do Caixa' : 'Voltar ao sistema' ?></a></div></main>
  <?php else: ?>
  <form class="pdv-shell" id="pdv-form" method="post" action="actions/caixa-venda-salvar.php" autocomplete="off">
    <?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="forma_pagamento" id="pdv-payment-form" value="dinheiro"><input type="hidden" name="acrescimo" value="0"><div id="pdv-hidden-items" hidden></div>
    <aside class="pdv-entry-column">
      <section class="pdv-card pdv-search-card"><label for="pdv-product-search">Produto / código / EAN</label><div class="pdv-search-box"><i class="bi bi-upc-scan"></i><input id="pdv-product-search" placeholder="Digite nome, código ou leia o código de barras" autofocus autocomplete="off"></div><div class="pdv-product-results d-none" id="pdv-product-results" role="listbox"></div></section>
      <section class="pdv-card"><span class="pdv-field-label">Código</span><strong id="pdv-current-code">—</strong></section>
      <section class="pdv-card"><span class="pdv-field-label">Valor unitário</span><strong class="pdv-money-line"><small>R$</small><span id="pdv-unit-price">0,00</span></strong></section>
      <section class="pdv-card"><span class="pdv-field-label">Total do item</span><strong class="pdv-item-total" id="pdv-item-total">R$ 0,00</strong></section>
      <section class="pdv-card"><label class="pdv-field-label" for="pdv-quantity">Quantidade</label><input class="pdv-number-input" id="pdv-quantity" inputmode="decimal" value="1,000"></section>
      <section class="pdv-card pdv-discount-row"><label for="pdv-discount">Desconto da venda</label><div><small>R$</small><input id="pdv-discount" name="desconto" inputmode="decimal" value="0,00"></div></section>
      <div class="pdv-entry-actions"><button class="pdv-add-button" id="pdv-add-product" type="button"><i class="bi bi-plus-lg"></i> Adicionar</button><button class="pdv-clear-button" id="pdv-clear-selection" type="button"><i class="bi bi-trash"></i> Limpar</button></div>
    </aside>

    <main class="pdv-cart-column">
      <section class="pdv-current-product"><div><span>Produto</span><strong id="pdv-current-product">Nenhum produto selecionado</strong></div><div><span>Valor</span><strong id="pdv-current-value">R$ 0,00</strong></div></section>
      <section class="pdv-items-panel"><header>Lista de itens <span id="pdv-items-count">0 item</span></header><div class="pdv-items-scroll"><table><thead><tr><th>Item</th><th>Produto</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th></th></tr></thead><tbody id="pdv-cart-body"><tr class="pdv-empty-row"><td colspan="6">Nenhuma peça adicionada à venda.</td></tr></tbody></table></div></section>
      <section class="pdv-subtotal"><span>Subtotal</span><strong id="pdv-subtotal">R$ 0,00</strong></section>
    </main>

    <aside class="pdv-payment-column">
      <section class="pdv-total-card"><span>Total</span><strong id="pdv-total">R$ 0,00</strong></section>
      <section class="pdv-payment-card"><header><strong>Pagamento</strong><span>Pagamento único</span></header><div class="pdv-document"><span>Tipo de documento</span><div><button class="active" type="button"><i class="bi bi-receipt"></i><strong>Cupom</strong><small>Não fiscal</small></button><button type="button" disabled title="Emissão fiscal é realizada no módulo Notas e Faturamento"><i class="bi bi-file-earmark-check"></i><strong>Nota fiscal</strong><small>Via Faturamento</small></button></div></div>
        <div class="pdv-client-picker"><label for="pdv-client-search">Consumidor (opcional)</label><input type="hidden" id="pdv-client-id" name="cliente_id"><input id="pdv-client-search" placeholder="Nome, código, CPF/CNPJ ou telefone" <?= $canSearchClients ? '' : 'disabled' ?> autocomplete="off"><div class="pdv-client-results d-none" id="pdv-client-results" role="listbox"></div></div>
        <div class="pdv-payment-methods"><button class="active" type="button" data-payment="dinheiro"><i class="bi bi-cash-coin"></i> Dinheiro</button><button type="button" data-payment="pix"><i class="bi bi-qr-code"></i> Pix</button><button type="button" data-payment="cartao_debito"><i class="bi bi-credit-card"></i> Débito</button><button type="button" data-payment="cartao_credito"><i class="bi bi-credit-card-2-front"></i> Crédito</button></div>
        <label class="pdv-other-payment" for="pdv-other-payment">Outras formas<select id="pdv-other-payment"><option value="">Selecione</option><option value="transferencia">Transferência</option><option value="boleto">Boleto</option><option value="cheque">Cheque</option><option value="outro">Outro</option></select></label>
        <label class="pdv-received" for="pdv-received"><span>Total recebido</span><div><small>R$</small><input id="pdv-received" name="valor_recebido" inputmode="decimal" value="0,00"></div></label>
        <div class="pdv-change"><span>Troco</span><strong id="pdv-change">R$ 0,00</strong></div>
        <button class="pdv-finalize" id="pdv-finalize" type="submit" disabled><i class="bi bi-check2-circle"></i> Finalizar venda <kbd>F4</kbd></button>
      </section>
    </aside>
  </form>
  <script type="application/json" id="pdv-products-data"><?= json_encode($productPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
</div>
<script src="assets/js/frente-caixa.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/frente-caixa.js') ?>"></script>
</body>
</html>
