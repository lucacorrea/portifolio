<?php
$adminTitle = 'Cadastro de categoria';
$activeAdmin = 'categoria-form';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Cadastro</span>
    <h1>Cadastrar categoria</h1>
    <p>Defina nome, slug, ordem, cor de apoio e onde a categoria aparece no catálogo.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-soft" href="<?= site_url('admin/categorias.php') ?>">Voltar para categorias</a></div>
</section>

<form class="admin-form-shell">
  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados principais</strong><p>Campos visuais para organizar o catálogo.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Nome</span><input placeholder="Ex: Buquês" required></label>
        <label class="admin-field"><span>Slug</span><input placeholder="buques"></label>
        <label class="admin-field"><span>Ícone textual</span><input placeholder="Buquê"></label>
        <label class="admin-field"><span>Status</span><select><option>Ativa</option><option>Inativa</option></select></label>
        <label class="admin-field full"><span>Descrição</span><input placeholder="Resumo para aparecer no catálogo"></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Configuração visual</strong><p>Controle de ordem e destaque.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Ordem</span><input type="number" value="1"></label>
        <label class="admin-field"><span>Cor de apoio</span><input type="text" value="#4F8F6B"></label>
        <label class="admin-field"><span>Exibir na home</span><select><option>Sim</option><option>Não</option></select></label>
        <label class="admin-field"><span>Exibir no catálogo</span><select><option>Sim</option><option>Não</option></select></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="category-preview">
      <span></span>
      <strong>Buquês</strong>
      <p>Buquês naturais e personalizados.</p>
    </div>
    <div class="admin-check-list">
      <label><input type="checkbox" checked> Exibir na home</label>
      <label><input type="checkbox" checked> Exibir no catálogo</label>
      <label><input type="checkbox"> Priorizar na listagem</label>
    </div>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
