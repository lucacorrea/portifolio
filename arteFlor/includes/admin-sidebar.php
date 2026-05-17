<aside class="admin-sidebar">
  <div class="admin-sidebar-top">
    <a class="brand" href="dashboard.php"><span class="brand-icon">🌿</span><span>Arte<span>&</span>Flor</span></a>
    <div class="admin-store-card">
      <strong>Painel da Floricultura</strong>
      <small>Catálogo, PDV, pedidos e integrações</small>
    </div>
  </div>

  <nav class="admin-menu" aria-label="Menu administrativo">
    <span class="admin-menu-label">Visão geral</span>
    <a class="<?= ($activeAdmin ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">📊 Dashboard</a>

    <span class="admin-menu-label">Catálogo</span>
    <a class="<?= ($activeAdmin ?? '') === 'produtos' ? 'active' : '' ?>" href="produtos.php">🌸 Produtos</a>
    <a class="<?= ($activeAdmin ?? '') === 'produto-form' ? 'active' : '' ?>" href="produto-form.php">➕ Cadastrar produto</a>
    <a class="<?= ($activeAdmin ?? '') === 'categorias' ? 'active' : '' ?>" href="categorias.php">🏷️ Categorias</a>
    <a class="<?= ($activeAdmin ?? '') === 'categoria-form' ? 'active' : '' ?>" href="categoria-form.php">➕ Cadastrar categoria</a>

    <span class="admin-menu-label">Operação</span>
    <a class="<?= ($activeAdmin ?? '') === 'caixa' ? 'active' : '' ?>" href="caixa.php">🧾 Frente de caixa</a>
    <a class="<?= ($activeAdmin ?? '') === 'pedidos' ? 'active' : '' ?>" href="pedidos.php">📦 Pedidos</a>
    <a class="<?= ($activeAdmin ?? '') === 'estoque' ? 'active' : '' ?>" href="estoque.php">📊 Estoque</a>

    <span class="admin-menu-label">Relacionamento</span>
    <a class="<?= ($activeAdmin ?? '') === 'clientes' ? 'active' : '' ?>" href="clientes.php">👥 Clientes</a>
    <a class="<?= ($activeAdmin ?? '') === 'cliente-form' ? 'active' : '' ?>" href="cliente-form.php">➕ Cadastrar cliente</a>
    <a class="<?= ($activeAdmin ?? '') === 'cupons' ? 'active' : '' ?>" href="cupons.php">🎟️ Cupons</a>
    <a class="<?= ($activeAdmin ?? '') === 'cupom-form' ? 'active' : '' ?>" href="cupom-form.php">➕ Cadastrar cupom</a>

    <span class="admin-menu-label">Gestão</span>
    <a class="<?= ($activeAdmin ?? '') === 'relatorios' ? 'active' : '' ?>" href="relatorios.php">📈 Relatórios</a>
    <a class="<?= ($activeAdmin ?? '') === 'integracoes' ? 'active' : '' ?>" href="integracoes.php">🔌 Pix e WhatsApp API</a>

    <span class="admin-menu-label">Site</span>
    <a href="../index.php">↗ Ver catálogo público</a>
  </nav>
</aside>
