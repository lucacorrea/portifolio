<?php
$pageTitle = 'Área do cliente';
$activePage = 'cliente';
$pageScripts = ['js/cliente.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header compact-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Área do cliente</span>
      <h1 class="section-title">Acompanhe pedidos Arte&Flor</h1>
      <p class="section-subtitle">Consulte pedidos fictícios e pedidos finalizados neste navegador pelo checkout demonstrativo.</p>
    </div>
    <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento sobre meu pedido Arte&Flor.') ?>">Atendimento</a>
  </div>
</section>

<section class="section">
  <div class="container client-layout">
    <aside class="card client-search-card">
      <span class="badge">Consulta</span>
      <h2>Buscar pedido</h2>
      <label class="form-group">
        <span>Número do pedido</span>
        <input type="search" data-order-search placeholder="#AF-1030">
      </label>
      <p class="muted">Digite o código gerado no checkout, como #AF-1030.</p>
    </aside>

    <div>
      <div class="section-heading compact">
        <div>
          <span class="badge">Histórico</span>
          <h2 class="section-title">Pedidos recentes</h2>
        </div>
      </div>
      <div class="client-orders-grid" data-client-orders></div>
      <div class="empty-results card" data-client-empty hidden>
        <strong>Nenhum pedido encontrado.</strong>
        <p>Confira o código digitado ou finalize um pedido no checkout demonstrativo.</p>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
