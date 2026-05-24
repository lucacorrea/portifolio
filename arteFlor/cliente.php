<?php
require_once __DIR__ . '/includes/orders.php';

$pageTitle = 'Área do cliente';
$activePage = 'cliente';

$requestedCode = order_clean_text($_GET['pedido'] ?? $_GET['codigo'] ?? '', 40);
$pedido = $requestedCode !== '' ? order_find_by_code($requestedCode) : null;
$itens = $pedido ? order_items((int) $pedido['id']) : [];
$historico = $pedido ? order_history((int) $pedido['id']) : [];
$pagamento = $pedido ? order_payment((int) $pedido['id']) : null;

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header compact-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Área do cliente</span>
      <h1 class="section-title">Acompanhe pedidos Arte&Flor</h1>
      <p class="section-subtitle">Consulte o pedido oficial salvo no sistema pelo código gerado no checkout.</p>
    </div>
    <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento sobre meu pedido Arte&Flor.') ?>">Atendimento</a>
  </div>
</section>

<section class="section">
  <div class="container client-layout">
    <aside class="card client-search-card">
      <span class="badge">Consulta</span>
      <h2>Buscar pedido</h2>
      <form class="client-search-form" method="get" action="<?= site_url('cliente.php') ?>">
        <label class="form-group">
          <span>Número do pedido</span>
          <input type="search" name="pedido" value="<?= e($requestedCode) ?>" placeholder="#AF-260524-0001" required>
        </label>
        <button class="btn btn-primary" type="submit">Consultar</button>
      </form>
      <p class="muted">Digite o código gerado no checkout. O pedido oficial é sempre consultado no banco.</p>
    </aside>

    <div class="client-order-panel">
      <?php if ($pedido): ?>
        <article class="card client-order-detail">
          <div class="client-order-header">
            <div>
              <span class="badge">Pedido <?= e($pedido['codigo']) ?></span>
              <h2><?= e($pedido['cliente_nome']) ?></h2>
              <p class="muted">Criado em <?= e(date('d/m/Y H:i', strtotime((string) $pedido['criado_em']))) ?></p>
            </div>
            <div class="client-order-status">
              <span class="status status-ok"><?= e(order_status_label((string) $pedido['status'])) ?></span>
              <small><?= e(order_payment_status_label((string) $pedido['status_pagamento'])) ?></small>
            </div>
          </div>

          <div class="client-order-summary">
            <div><span>Pagamento</span><strong><?= e(order_payment_method_label((string) $pedido['forma_pagamento'])) ?></strong></div>
            <div><span>Recebimento</span><strong><?= e(order_receipt_label((string) $pedido['recebimento'])) ?></strong></div>
            <div><span>Total</span><strong><?= money_br((float) $pedido['total']) ?></strong></div>
          </div>

          <div class="client-order-grid">
            <section>
              <h3>Itens</h3>
              <div class="client-order-items">
                <?php foreach ($itens as $item): ?>
                  <div class="client-order-item">
                    <?php if (!empty($item['imagem'])): ?>
                      <img src="<?= e((string) $item['imagem']) ?>" alt="<?= e((string) $item['produto_nome']) ?>">
                    <?php else: ?>
                      <span class="image-fallback">A&F</span>
                    <?php endif; ?>
                    <div>
                      <strong><?= (int) $item['quantidade'] ?>x <?= e((string) $item['produto_nome']) ?></strong>
                      <small><?= e((string) ($item['produto_sku'] ?? '')) ?> · <?= money_br((float) $item['preco_unitario']) ?></small>
                      <?php if (!empty($item['mensagem_cartao'])): ?>
                        <p class="muted"><?= e((string) $item['mensagem_cartao']) ?></p>
                      <?php endif; ?>
                    </div>
                    <b><?= money_br((float) $item['total_linha']) ?></b>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section>
              <h3>Recebimento</h3>
              <div class="client-order-box">
                <p><strong><?= e(order_receipt_label((string) $pedido['recebimento'])) ?></strong></p>
                <?php if (($pedido['recebimento'] ?? '') === 'entrega'): ?>
                  <p><?= e((string) $pedido['endereco']) ?></p>
                  <p><?= e((string) $pedido['bairro']) ?></p>
                  <?php if (!empty($pedido['referencia'])): ?><p><?= e((string) $pedido['referencia']) ?></p><?php endif; ?>
                <?php else: ?>
                  <p>Retirada combinada diretamente na loja.</p>
                <?php endif; ?>
                <?php if (!empty($pedido['data_desejada'])): ?>
                  <p>Data: <?= e(date('d/m/Y', strtotime((string) $pedido['data_desejada']))) ?> às <?= e(substr((string) $pedido['horario_desejado'], 0, 5)) ?></p>
                <?php endif; ?>
              </div>

              <h3>Pagamento</h3>
              <div class="client-order-box">
                <p><strong><?= e(order_payment_method_label((string) $pedido['forma_pagamento'])) ?></strong></p>
                <p><?= e(order_payment_status_label((string) $pedido['status_pagamento'])) ?></p>
                <?php if ($pagamento && !empty($pagamento['chave_pix'])): ?>
                  <p>Chave Pix: <?= e((string) $pagamento['chave_pix']) ?></p>
                <?php endif; ?>
              </div>
            </section>
          </div>

          <?php if (!empty($pedido['mensagem_cartao']) || !empty($pedido['observacoes'])): ?>
            <div class="client-order-note">
              <?php if (!empty($pedido['mensagem_cartao'])): ?><p><strong>Cartão:</strong> <?= e((string) $pedido['mensagem_cartao']) ?></p><?php endif; ?>
              <?php if (!empty($pedido['observacoes'])): ?><p><strong>Observações:</strong> <?= e((string) $pedido['observacoes']) ?></p><?php endif; ?>
            </div>
          <?php endif; ?>

          <section class="client-timeline">
            <h3>Linha do tempo</h3>
            <?php foreach ($historico as $evento): ?>
              <div class="client-timeline-item">
                <span></span>
                <div>
                  <strong><?= e(order_status_label((string) $evento['status_novo'])) ?></strong>
                  <small><?= e(date('d/m/Y H:i', strtotime((string) $evento['criado_em']))) ?></small>
                  <?php if (!empty($evento['observacao'])): ?><p><?= e((string) $evento['observacao']) ?></p><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </section>
        </article>
      <?php elseif ($requestedCode !== ''): ?>
        <div class="empty-results card">
          <strong>Pedido não encontrado.</strong>
          <p>Confira o código digitado ou volte ao catálogo para criar um novo pedido.</p>
          <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Voltar ao catálogo</a>
        </div>
      <?php else: ?>
        <div class="empty-results card">
          <strong>Informe o código do pedido.</strong>
          <p>Após finalizar o checkout, use o código exibido na tela de sucesso para acompanhar a compra.</p>
          <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
