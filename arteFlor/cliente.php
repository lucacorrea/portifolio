<?php
$pageTitle = 'Área do cliente';
$activePage = 'cliente';
require_once __DIR__ . '/includes/header.php';

$pedidos = load_json('pedidos-demo.json');
?>
<section class="page-hero">
  <div class="container">
    <span class="badge">Acompanhamento demonstrativo</span>
    <h1 class="section-title">Área do cliente</h1>
    <p class="section-subtitle">Consulta visual de pedidos fictícios e pedidos salvos no localStorage pelo checkout.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <form class="card form-grid" data-demo-form>
      <label class="form-group">
        <span>Código do pedido</span>
        <input placeholder="Ex: AF-1025">
      </label>
      <label class="form-group">
        <span>WhatsApp cadastrado</span>
        <input placeholder="(00) 00000-0000">
      </label>
      <button class="btn btn-primary" type="submit">Consultar pedido</button>
    </form>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Pedidos recentes</span>
      <h2 class="section-title">Status da compra</h2>
    </div>
    <div class="grid-3">
      <?php foreach ($pedidos as $pedido): ?>
        <article class="card order-card">
          <span class="status"><?= e($pedido['status']) ?></span>
          <h3>Pedido #<?= e($pedido['codigo']) ?></h3>
          <p class="muted"><?= e($pedido['itens']) ?> · <?= e($pedido['pagamento']) ?></p>
          <p><strong><?= money_br((float) $pedido['total']) ?></strong></p>
          <ul class="status-track">
            <li class="<?= $pedido['status'] === 'Pedido recebido' ? 'current' : '' ?>">Pedido recebido</li>
            <li class="<?= $pedido['status'] === 'Em preparo' ? 'current' : '' ?>">Em preparo</li>
            <li class="<?= $pedido['status'] === 'Saiu para entrega' ? 'current' : '' ?>">Saiu para entrega</li>
            <li class="<?= $pedido['status'] === 'Finalizado' ? 'current' : '' ?>">Finalizado</li>
          </ul>
          <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, quero falar sobre o pedido ' . $pedido['codigo']) ?>">Falar com a loja</a>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="section-heading" style="margin-top:34px">
      <span class="eyebrow">LocalStorage</span>
      <h2 class="section-title">Pedidos simulados neste navegador</h2>
    </div>
    <div class="grid-3" id="localOrders"></div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
