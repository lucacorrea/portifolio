<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();


$pageId = 'produto-form';
$pageTitle = 'Produto';
$activeMenu = 'produtos';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
    <div class="statusbar dark">
      <strong data-time>09:41</strong>
      <div class="device-icons" aria-hidden="true">
        <span class="signal"></span>
        <span class="wifi"></span>
        <span class="battery"></span>
      </div>
    </div>
    
  <div class="page-title-row">
    <a class="back-btn" href="produtos.php">‹</a>
    <div>
      <p class="micro-label dark-text">Cadastro</p>
      <h1 id="productFormTitle">Produto</h1>
    </div>
    <span></span>
  </div>
</header>

<section class="content-pad">
  <form class="form-card" id="productForm">
    <div class="product-form-preview">
      <img id="productPreview" src="../assets/img/prod-placeholder.svg" alt="Prévia do produto">
      <div>
        <label class="file-btn">
          Escolher imagem
          <input id="productImageInput" type="file" accept="image/*" capture="environment">
        </label>
        <button type="button" class="file-btn" data-select-product-image>Tirar foto</button>
      </div>
    </div>

    <div class="form-grid section-gap-small">
      <div class="field"><label>Nome do produto</label><input id="productName" required></div>
      <div class="field"><label>SKU / Código interno</label><input id="productSku" required></div>
      <div class="field"><label>Código de barras / QR</label><input id="productBarcode"></div>
      <div class="field"><label>Categoria</label><input id="productCategory" required></div>
      <div class="field"><label>Preço de custo</label><input id="productCost" type="number" step="0.01" required></div>
      <div class="field"><label>Preço de venda</label><input id="productPrice" type="number" step="0.01" required></div>
      <div class="field"><label>Quantidade em estoque</label><input id="productStock" type="number" required></div>
      <div class="field"><label>Limite mínimo</label><input id="productMinStock" type="number" required></div>
      <div class="field"><label>Lote</label><input id="productLot" required></div>
      <div class="field"><label>Data de validade</label><input id="productExpiry" type="date" required></div>
    </div>

    <button class="primary-btn section-gap-small" type="submit">Salvar produto</button>
  </form>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
