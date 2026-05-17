<?php $activeAdmin = $activeAdmin ?? ''; ?>
<aside class="admin-sidebar">
  <a class="brand admin-brand" href="<?= site_url('admin/dashboard.php') ?>" aria-label="Painel Arte&Flor">
    <span class="brand-mark" aria-hidden="true">A&F</span>
    <span class="brand-text">Arte<span>&</span>Flor</span>
  </a>
  <nav class="admin-nav" aria-label="Navegacao administrativa">
    <a class="<?= $activeAdmin === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>
    <a class="<?= $activeAdmin === 'produtos' ? 'active' : '' ?>" href="<?= site_url('admin/produtos.php') ?>">Produtos</a>
    <a class="<?= $activeAdmin === 'produto-form' ? 'active' : '' ?>" href="<?= site_url('admin/produto-form.php') ?>">Novo produto</a>
    <a class="<?= $activeAdmin === 'estoque' ? 'active' : '' ?>" href="<?= site_url('admin/estoque.php') ?>">Estoque</a>
    <a class="<?= $activeAdmin === 'caixa' ? 'active' : '' ?>" href="<?= site_url('admin/caixa.php') ?>">Caixa</a>
    <a class="<?= $activeAdmin === 'pedidos' ? 'active' : '' ?>" href="<?= site_url('admin/pedidos.php') ?>">Pedidos</a>
    <a class="<?= $activeAdmin === 'relatorios' ? 'active' : '' ?>" href="<?= site_url('admin/relatorios.php') ?>">Relatórios</a>
    <a href="<?= site_url('index.php') ?>">Ver site</a>
  </nav>
</aside>
