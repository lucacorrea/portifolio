<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'produto-form';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cadastrar produto | Arte&Flor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/admin-premium.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="admin-premium-body">
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <section class="admin-page-hero">
      <div class="admin-page-title">
        <span class="badge">Cadastro</span>
        <h1>Cadastrar produto</h1>
        <p>Monte um produto completo para o catálogo com preço, estoque, imagens, tags e regras comerciais.</p>
      </div>
      <div class="admin-hero-actions">
        <a class="btn btn-soft" href="produtos.php">Voltar para produtos</a>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
      </div>
    </section>

    <form class="admin-form-shell">
      <section class="admin-form-card">
        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Informações principais</strong><p>Dados que aparecem no catálogo e na página de detalhes.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Nome do produto</span><input placeholder="Ex: Buquê de Rosas Vermelhas" required></label>
            <label class="admin-field"><span>Categoria</span><select><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Plantas</option><option>Presentes</option><option>Datas especiais</option></select></label>
            <label class="admin-field"><span>SKU / código</span><input placeholder="AF-1001"></label>
            <label class="admin-field"><span>Status</span><select><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option><option>Sem estoque</option></select></label>
            <label class="admin-field full"><span>Descrição curta</span><input placeholder="Resumo para card do catálogo"></label>
            <label class="admin-field full"><span>Descrição completa</span><textarea placeholder="Composição, ocasião indicada, cuidados e observações"></textarea></label>
          </div>
        </div>

        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Preço e estoque</strong><p>Controle visual de valores e disponibilidade.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Preço normal</span><input type="number" step="0.01" placeholder="149.90"></label>
            <label class="admin-field"><span>Preço promocional</span><input type="number" step="0.01" placeholder="129.90"></label>
            <label class="admin-field"><span>Quantidade em estoque</span><input type="number" placeholder="8"></label>
            <label class="admin-field"><span>Estoque mínimo</span><input type="number" placeholder="3"></label>
          </div>
        </div>

        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Mídia e SEO visual</strong><p>Campos preparados para a próxima etapa com backend.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field full"><span>Fotos do produto</span><input type="file" multiple accept="image/*"></label>
            <label class="admin-field"><span>Tags</span><input placeholder="Romântico, Mais vendido"></label>
            <label class="admin-field"><span>Palavras-chave</span><input placeholder="flores, buquê, presente"></label>
          </div>
        </div>
      </section>

      <aside class="admin-form-card admin-side-card">
        <div class="admin-preview-image">🌹</div>
        <div class="admin-check-list">
          <label><input type="checkbox" checked> Exibir no catálogo público</label>
          <label><input type="checkbox" checked> Permitir compra pelo WhatsApp</label>
          <label><input type="checkbox"> Produto em destaque</label>
          <label><input type="checkbox"> Produto sob encomenda</label>
          <label><input type="checkbox"> Aplicar promoção</label>
        </div>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
        <a class="btn btn-soft" href="produtos.php">Ver listagem</a>
        <p class="muted">Na versão real, esse formulário terá validação, upload de imagens e persistência em banco de dados.</p>
      </aside>
    </form>
  </main>
</div>
</body>
</html>
