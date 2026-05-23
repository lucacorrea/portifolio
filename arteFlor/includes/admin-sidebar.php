<aside class="admin-sidebar">
  <div class="admin-sidebar-top">
    <a class="brand" href="<?= site_url('admin/dashboard.php') ?>"><span class="brand-icon" aria-hidden="true">A&F</span><span>Arte<span>&</span>Flor</span></a>
    <div class="admin-store-card">
      <strong>Painel da Floricultura</strong>
      <small><?= e($adminUser['nome'] ?? 'Admin') ?> · <?= e($adminUser['perfil'] ?? 'operador') ?></small>
    </div>
  </div>

  <nav class="admin-menu" aria-label="Menu administrativo">
    <span class="admin-menu-label">Visão geral</span>
    <a class="<?= ($activeAdmin ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>

    <span class="admin-menu-label">Catálogo</span>
    <a class="<?= ($activeAdmin ?? '') === 'produtos' ? 'active' : '' ?>" href="<?= site_url('admin/produtos.php') ?>">Produtos</a>
    <a class="<?= ($activeAdmin ?? '') === 'produto-form' ? 'active' : '' ?>" href="<?= site_url('admin/produto-form.php') ?>">Cadastrar produto</a>
    <a class="<?= ($activeAdmin ?? '') === 'categorias' ? 'active' : '' ?>" href="<?= site_url('admin/categorias.php') ?>">Categorias</a>
    <a class="<?= ($activeAdmin ?? '') === 'categoria-form' ? 'active' : '' ?>" href="<?= site_url('admin/categoria-form.php') ?>">Cadastrar categoria</a>

    <span class="admin-menu-label">Operação</span>
    <a class="<?= ($activeAdmin ?? '') === 'caixa' ? 'active' : '' ?>" href="<?= site_url('admin/caixa.php') ?>">Frente de caixa</a>
    <a class="<?= ($activeAdmin ?? '') === 'pedidos' ? 'active' : '' ?>" href="<?= site_url('admin/pedidos.php') ?>">Pedidos</a>
    <a class="<?= ($activeAdmin ?? '') === 'estoque' ? 'active' : '' ?>" href="<?= site_url('admin/estoque.php') ?>">Estoque</a>

    <span class="admin-menu-label">Relacionamento</span>
    <a class="<?= ($activeAdmin ?? '') === 'clientes' ? 'active' : '' ?>" href="<?= site_url('admin/clientes.php') ?>">Clientes</a>
    <a class="<?= ($activeAdmin ?? '') === 'cliente-form' ? 'active' : '' ?>" href="<?= site_url('admin/cliente-form.php') ?>">Cadastrar cliente</a>
    <a class="<?= ($activeAdmin ?? '') === 'cupons' ? 'active' : '' ?>" href="<?= site_url('admin/cupons.php') ?>">Cupons</a>
    <a class="<?= ($activeAdmin ?? '') === 'cupom-form' ? 'active' : '' ?>" href="<?= site_url('admin/cupom-form.php') ?>">Cadastrar cupom</a>

    <span class="admin-menu-label">Gestão</span>
    <a class="<?= ($activeAdmin ?? '') === 'relatorios' ? 'active' : '' ?>" href="<?= site_url('admin/relatorios.php') ?>">Relatórios</a>
    <a class="<?= ($activeAdmin ?? '') === 'integracoes' ? 'active' : '' ?>" href="<?= site_url('admin/integracoes.php') ?>">Integrações</a>

    <span class="admin-menu-label">Site</span>
    <a href="<?= site_url('index.php') ?>">Ver catálogo público</a>

    <form class="admin-logout-form" method="post" action="<?= site_url('admin/logout.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
      <button class="admin-logout-button" type="submit">Sair do painel</button>
    </form>
  </nav>
</aside>
