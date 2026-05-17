<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cadastrar produto | Arte&Flor</title>
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-header">
      <div>
        <span class="badge">Cadastro</span>
        <h1 class="section-title">Cadastrar produto</h1>
        <p class="muted">Tela separada para montar o produto com preço, estoque, fotos e regras de exibição no catálogo.</p>
      </div>
      <a class="btn btn-soft" href="produtos.php">Voltar para listagem</a>
    </div>

    <div class="admin-tabs">
      <a href="produtos.php">Listagem</a>
      <a class="active" href="produto-form.php">Cadastro</a>
      <a href="estoque.php">Movimentações</a>
    </div>

    <form class="admin-form-layout">
      <section class="card form-grid">
        <div class="form-section-title form-group full">
          <strong>Informações principais</strong>
          <p class="muted">Dados que aparecem diretamente no catálogo.</p>
        </div>

        <label class="form-group"><span>Nome do produto</span><input placeholder="Ex: Buquê de Rosas Vermelhas" required></label>
        <label class="form-group"><span>Categoria</span><select><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Plantas</option><option>Presentes</option><option>Datas especiais</option></select></label>
        <label class="form-group"><span>Preço normal</span><input type="number" step="0.01" placeholder="149.90"></label>
        <label class="form-group"><span>Preço promocional</span><input type="number" step="0.01" placeholder="Opcional"></label>
        <label class="form-group"><span>Quantidade em estoque</span><input type="number" placeholder="8"></label>
        <label class="form-group"><span>Status</span><select><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option><option>Sem estoque</option></select></label>
        <label class="form-group full"><span>Descrição curta</span><input placeholder="Resumo para aparecer no card do produto"></label>
        <label class="form-group full"><span>Descrição completa</span><textarea placeholder="Detalhes do produto, ocasião indicada, composição e observações"></textarea></label>
      </section>

      <aside class="card admin-preview-panel">
        <strong>Configurações do catálogo</strong>
        <label class="check-row"><input type="checkbox"> Produto em destaque</label>
        <label class="check-row"><input type="checkbox"> Produto sob encomenda</label>
        <label class="check-row"><input type="checkbox"> Exibir no catálogo público</label>
        <label class="check-row"><input type="checkbox"> Permitir compra pelo WhatsApp</label>
        <label class="form-group"><span>Tags</span><input placeholder="Romântico, Mais vendido, Presente"></label>
        <label class="form-group"><span>Fotos do produto</span><input type="file" multiple accept="image/*"></label>
        <div class="upload-preview"><span>🌹</span><span>🌸</span><span>💐</span></div>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
        <p class="muted">No backend real, este formulário será ligado ao banco de dados e upload de múltiplas imagens.</p>
      </aside>
    </form>
  </main>
</div>
</body>
</html>
