<?php declare(strict_types=1); ?>
<aside id="app-sidebar" class="admin-sidebar">
  <a class="admin-brand" href="<?= admin_url() ?>"><i class="bi bi-buildings"></i><span>Flux Empresas<small>Administração da plataforma</small></span></a>
  <nav aria-label="Navegação administrativa">
    <p class="admin-nav-label">Visão geral</p>
    <a class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'dashboard' ? 'aria-current="page"' : '' ?> href="<?= admin_url() ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
    <p class="admin-nav-label">Empresas</p>
    <a class="<?= ($activePage ?? '') === 'companies' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'companies' ? 'aria-current="page"' : '' ?> href="<?= admin_url('empresas.php') ?>"><i class="bi bi-buildings"></i>Todas as empresas</a>
    <a class="<?= ($activePage ?? '') === 'so' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'so' ? 'aria-current="page"' : '' ?> href="<?= admin_url('empresas-so.php') ?>"><i class="bi bi-database"></i>Empresas do SO</a>
    <a class="<?= ($activePage ?? '') === 'mine' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'mine' ? 'aria-current="page"' : '' ?> href="<?= admin_url('minhas-aprovacoes.php') ?>"><i class="bi bi-person-check"></i>Cadastradas por mim</a>
    <p class="admin-nav-label">Controle</p>
    <a class="<?= ($activePage ?? '') === 'accesses' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'accesses' ? 'aria-current="page"' : '' ?> href="<?= admin_url('acessos.php') ?>"><i class="bi bi-clock-history"></i>Histórico de acessos</a>
    <a class="<?= ($activePage ?? '') === 'integrations' ? 'active' : '' ?>" <?= ($activePage ?? '') === 'integrations' ? 'aria-current="page"' : '' ?> href="<?= admin_url('integracoes.php') ?>"><i class="bi bi-plug"></i>Integrações</a>
  </nav>
  <div class="admin-sidebar-footer"><a href="<?= app_url('dashboard.php') ?>"><i class="bi bi-grid"></i>Painel operacional</a><form method="post" action="<?= app_url('actions/logout.php') ?>"><?= $csrf->field() ?><button type="submit"><i class="bi bi-box-arrow-right"></i>Sair</button></form></div>
</aside><div id="sidebar-backdrop" class="sidebar-backdrop"></div>
