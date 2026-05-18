<?php
$adminTitle = 'Cadastro de produto';
$activeAdmin = 'produto-form';
require_once __DIR__ . '/../includes/admin-head.php';
$previewImage = 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=700&q=80';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Cadastro</span>
    <h1>Cadastrar produto</h1>
    <p>Template visual para cadastrar produto com informações comerciais, fotos, SEO e opções de venda.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Voltar para produtos</a>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
  </div>
</section>

<form class="admin-form-shell">
  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados principais</strong><p>Informações exibidas no catálogo e no detalhe do produto.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Nome</span><input placeholder="Buquê de Rosas Vermelhas" required></label>
        <label class="admin-field"><span>Categoria</span><select><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Plantas</option><option>Presentes</option><option>Datas especiais</option></select></label>
        <label class="admin-field"><span>SKU</span><input placeholder="AF-BUQ-001"></label>
        <label class="admin-field"><span>Status</span><select><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option><option>Sem estoque</option></select></label>
        <label class="admin-field full"><span>Descrição curta</span><input placeholder="Resumo que aparece no card"></label>
        <label class="admin-field full"><span>Descrição completa</span><textarea placeholder="Composição, ocasião indicada, cuidados e observações"></textarea></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Preço e estoque</strong><p>Campos comerciais para venda online e PDV.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Preço</span><input type="number" step="0.01" placeholder="149.90"></label>
        <label class="admin-field"><span>Preço promocional</span><input type="number" step="0.01" placeholder="129.90"></label>
        <label class="admin-field"><span>Estoque</span><input type="number" placeholder="8"></label>
        <label class="admin-field"><span>Estoque mínimo</span><input type="number" placeholder="3"></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Fotos, tags e SEO visual</strong><p>Simulação dos campos que serão persistidos no backend futuro.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field full"><span>URLs das fotos</span><textarea placeholder="https://images.unsplash.com/..."></textarea></label>
        <label class="admin-field"><span>Tags</span><input placeholder="Romântico, Mais vendido"></label>
        <label class="admin-field"><span>Slug/SEO</span><input placeholder="buque-rosas-vermelhas"></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="admin-preview-product">
      <img src="<?= e($previewImage) ?>" alt="Preview do produto">
      <div>
        <span class="badge">Preview</span>
        <h3>Buquê de Rosas Vermelhas</h3>
        <p>Clássico, romântico e elegante.</p>
        <strong>R$ 129,90</strong>
      </div>
    </div>
    <div class="admin-check-list">
      <label><input type="checkbox" checked> Exibir no catálogo</label>
      <label><input type="checkbox" checked> Permitir venda online</label>
      <label><input type="checkbox" checked> Disponível no PDV</label>
      <label><input type="checkbox"> Produto em destaque</label>
      <label><input type="checkbox"> Produto sob encomenda</label>
    </div>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Ver listagem</a>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
