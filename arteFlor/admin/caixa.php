<?php
$adminTitle = 'Frente de caixa';
$activeAdmin = 'caixa';
require_once __DIR__ . '/../includes/admin-head.php';
$produtos = load_json('produtos.json');
$pdvProdutos = array_map(fn($p) => [
    'id' => (string) ($p['id'] ?? ''),
    'sku' => $p['sku'] ?? '',
    'nome' => $p['nome'] ?? '',
    'categoria' => $p['categoria'] ?? '',
    'preco' => effective_price($p),
    'imagem' => first_image($p),
], $produtos);
$categorias = array_values(array_unique(array_map(fn($p) => $p['categoria'], $produtos)));
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">PDV</span>
    <h1>Frente de caixa</h1>
    <p>Venda presencial com busca de produto, carrinho do caixa, pagamento demonstrativo e histórico local.</p>
  </div>
  <div class="admin-hero-actions">
    <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender venda</button>
    <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
  </div>
</section>

<script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<section class="pdv-layout">
  <aside class="admin-panel-card pdv-products">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Produtos</span>
        <h2>Busca rápida</h2>
      </div>
    </div>
    <div class="pdv-search-grid">
      <label class="admin-field"><span>Código, SKU ou nome</span><input type="search" data-pdv-search placeholder="Digite para buscar"></label>
      <label class="admin-field"><span>Quantidade</span><input type="number" min="1" value="1" data-pdv-qty></label>
      <button class="btn btn-primary" type="button" data-pdv-add-search>Adicionar</button>
    </div>
    <div class="pdv-category-pills">
      <button class="filter-pill active" type="button" data-pdv-category="todos">Todos</button>
      <?php foreach ($categorias as $categoria): ?>
        <button class="filter-pill" type="button" data-pdv-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="pdv-product-grid" data-pdv-product-grid></div>
  </aside>

  <section class="admin-panel-card pdv-sale">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Venda atual</span>
        <h2>Carrinho do caixa</h2>
      </div>
      <span class="status status-ok">Caixa aberto</span>
    </div>
    <div class="pdv-current-items" data-pdv-current></div>
    <div class="pdv-totals">
      <p><span>Subtotal</span><strong data-pdv-subtotal>R$ 0,00</strong></p>
      <p><span>Desconto</span><input type="number" min="0" step="0.01" value="0" data-pdv-discount></p>
      <p class="pdv-total"><span>Total</span><strong data-pdv-total>R$ 0,00</strong></p>
    </div>
    <div class="admin-action-row">
      <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender venda</button>
      <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar venda</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
    </div>
  </section>

  <aside class="admin-panel-card pdv-side">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Pagamento</span>
        <h2>Dados rápidos</h2>
      </div>
    </div>
    <label class="admin-field"><span>Cliente</span><input data-pdv-client placeholder="Cliente balcão"></label>
    <label class="admin-field"><span>Contato fictício</span><input data-pdv-contact placeholder="(97) 90000-0000"></label>
    <label class="admin-field"><span>Forma de pagamento</span>
      <select data-pdv-payment>
        <option>Pix</option>
        <option>Dinheiro</option>
        <option>Cartão presencial</option>
        <option>Pagamento na retirada</option>
      </select>
    </label>
    <div class="pix-box pdv-pix">
      <span class="badge">Pix demonstrativo</span>
      <div class="qr-placeholder">PIX</div>
      <div class="pix-key-box"><small>Chave Pix</small><strong>arteflor@pix.demo</strong></div>
      <button class="btn btn-soft" type="button" data-copy-value="arteflor@pix.demo">Copiar chave</button>
    </div>

    <div class="pdv-history">
      <div class="admin-panel-header compact"><h2>Últimas vendas</h2></div>
      <div data-pdv-history></div>
    </div>
  </aside>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
