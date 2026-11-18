<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#12364a"><title><?= h($pageTitle??'OBRAS360') ?> · OBRAS360 Coari</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/app.css">
<?php if (($_GET['r']??'')==='mapa'): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoPp6iV0YtNGtA=" crossorigin="anonymous"><?php endif; ?>
</head>
<body class="<?= $user?'app-body':'auth-body' ?> <?= (($_GET['format']??'')==='print')?'print-view':'' ?>">
<?php if ($user && (($_GET['format']??'')!=='print')): ?>
<div class="app-shell">
 <div class="sidebar-mask" data-close-sidebar></div>
 <aside class="sidebar" id="sidebar">
  <a href="<?= h(url('dashboard')) ?>" class="brand"><span class="brand-mark"><?= ic('hard-hat') ?></span><span class="brand-words"><strong>OBRAS<span>360</span></strong><small>GESTÃO MUNICIPAL</small></span></a>
  <div class="sidebar-branch"><span class="sidebar-branch-dot"></span><div><strong>Coari · Amazonas</strong><small>Obras e Planejamento</small></div></div>
  <div class="side-scroll">
   <div class="nav-caption">VISÃO EXECUTIVA</div>
   <a class="nav-link <?= route_active('dashboard')?'active':'' ?>" href="<?= h(url('dashboard')) ?>"><?= ic('grid') ?> Painel geral</a>
   <a class="nav-link <?= route_active('mapa')?'active':'' ?>" href="<?= h(url('mapa')) ?>"><?= ic('map') ?> Mapa territorial</a>
   <div class="nav-caption">PLANEJAMENTO E OBRAS</div>
   <?php foreach(['obras','ordens','planejamento','fiscalizacao'] as $key):
    $routeLabel=['obras'=>'Obras','ordens'=>'Ordens de serviço','planejamento'=>'Planejamento','fiscalizacao'=>'Fiscalização'][$key];
    $icon=$key==='planejamento'?'calendar':$modules[$key]['icon']; ?>
     <a class="nav-link <?= route_active($key)?'active':'' ?>" href="<?= h(url($key)) ?>"><?= ic($icon) ?> <?= h($routeLabel) ?></a>
   <?php endforeach; ?>
   <div class="nav-caption">DOCUMENTOS E RECURSOS</div>
   <?php foreach(['projetos','orcamentos','materiais','patrimonio'] as $key): ?>
    <a class="nav-link <?= route_active($key)?'active':'' ?>" href="<?= h(url($key)) ?>"><?= ic($modules[$key]['icon']) ?> <?= h($modules[$key]['name']) ?></a>
   <?php endforeach; ?>
   <div class="nav-caption">CONTROLE E GESTÃO</div>
   <a class="nav-link <?= route_active('protocolos')?'active':'' ?>" href="<?= h(url('protocolos')) ?>"><?= ic('files') ?> Protocolos</a>
   <a class="nav-link <?= route_active('relatorios')?'active':'' ?>" href="<?= h(url('relatorios')) ?>"><?= ic('chart') ?> Relatórios</a>
   <?php if (\App\Core\Auth::isAdmin()): ?>
    <a class="nav-link <?= route_active('usuarios')?'active':'' ?>" href="<?= h(url('usuarios')) ?>"><?= ic('users') ?> Usuários</a>
    <a class="nav-link <?= route_active('auditoria')?'active':'' ?>" href="<?= h(url('auditoria')) ?>"><?= ic('shield-check') ?> Auditoria</a>
   <?php endif; ?>
  </div>
  <div class="sidebar-footer"><span class="sidebar-version">OBRAS360 <b>v1.0</b></span><span class="sidebar-safe">Acesso autenticado</span></div>
 </aside>
 <main class="main-content">
  <header class="topbar">
   <button class="icon-button mobile-menu" type="button" aria-label="Abrir menu" data-toggle-sidebar><?= ic('menu') ?></button>
   <div class="topbar-location"><small>SECRETARIA MUNICIPAL DE OBRAS E PLANEJAMENTO</small><strong><?= h($pageTitle??'Painel geral') ?></strong></div>
   <div class="topbar-spacer"></div>
   <div class="topbar-date"><?= h((new DateTimeImmutable())->format('d/m/Y')) ?></div>
   <a class="topbar-user" href="<?= h(url('profile')) ?>"><span class="avatar"><?= h(strtoupper(substr((string)$user['name'],0,1))) ?></span><span><b><?= h($user['name']) ?></b><small><?= h(role_name($user['role'])) ?></small></span></a>
   <form method="post" action="<?= h(url('logout')) ?>" class="logout-form"><?= csrf_field() ?><button type="submit" title="Sair" class="icon-button"><?= ic('logout') ?></button></form>
  </header>
  <div class="page-content">
<?php endif; ?>
<?php if ($flash): ?><div class="notice <?= $flash['type']==='error'?'notice-error':'notice-success' ?>" role="alert"><?= ic($flash['type']==='error'?'alert':'check') ?> <?= h($flash['text']) ?></div><?php endif; ?>
<?php if ($user && is_file(BASE_PATH.'/storage/DEMO_MODE') && (($_GET['format']??'')!=='print')): ?><div class="demo-banner" role="status"><?= ic('alert') ?> MODO DEMONSTRAÇÃO — dados, contratos e localizações fictícios. Não utilizar como registros oficiais.</div><?php endif; ?>
