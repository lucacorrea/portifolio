<?php
$adminTitle = 'Cadastro de cupom';
$activeAdmin = 'cupom-form';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Promoções</span>
    <h1>Cadastrar cupom</h1>
    <p>Crie campanhas visuais com tipo de desconto, validade, regras de uso e aplicação por canal.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-soft" href="<?= site_url('admin/cupons.php') ?>">Voltar para cupons</a></div>
</section>

<form class="admin-form-shell">
  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Campanha</strong><p>Dados principais do cupom.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Código</span><input placeholder="FLOR15"></label>
        <label class="admin-field"><span>Nome da campanha</span><input placeholder="Primeira compra"></label>
        <label class="admin-field"><span>Tipo de desconto</span><select><option>Percentual</option><option>Valor fixo</option><option>Frete</option></select></label>
        <label class="admin-field"><span>Valor</span><input type="number" placeholder="15"></label>
        <label class="admin-field"><span>Início</span><input type="date"></label>
        <label class="admin-field"><span>Validade</span><input type="date"></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Regras de uso</strong><p>Limites e aplicação comercial.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Uso máximo</span><input type="number" placeholder="100"></label>
        <label class="admin-field"><span>Valor mínimo</span><input type="number" step="0.01" placeholder="120.00"></label>
        <label class="admin-field"><span>Status</span><select><option>Ativo</option><option>Pausado</option><option>Encerrado</option></select></label>
        <label class="admin-field"><span>Canal</span><select><option>Catálogo</option><option>PDV</option><option>Todos</option></select></label>
        <label class="admin-field full"><span>Produtos/categorias aplicáveis</span><input placeholder="Buquês, Presentes, Produtos selecionados"></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="coupon-preview">
      <span>FLOR15</span>
      <strong>15% OFF</strong>
      <p>Válido no catálogo demonstrativo.</p>
    </div>
    <div class="admin-check-list">
      <label><input type="checkbox" checked> Exibir no checkout</label>
      <label><input type="checkbox" checked> Aplicar no catálogo</label>
      <label><input type="checkbox"> Limitar por categoria</label>
    </div>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
