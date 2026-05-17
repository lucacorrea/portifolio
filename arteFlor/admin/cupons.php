<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'cupons';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cupons | Arte&Flor</title>
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
        <span class="badge">Promoções</span>
        <h1>Cupons</h1>
        <p>Controle visual de campanhas promocionais, descontos e ações comerciais.</p>
      </div>
      <div class="admin-hero-actions"><a class="btn btn-primary" href="cupom-form.php">Cadastrar cupom</a></div>
    </section>

    <section class="admin-command-bar">
      <label class="admin-field"><span>Buscar</span><input placeholder="Código ou campanha"></label>
      <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Ativo</option><option>Pausado</option><option>Expirado</option></select></label>
      <label class="admin-field"><span>Tipo</span><select><option>Todos</option><option>Percentual</option><option>Valor fixo</option><option>Frete</option></select></label>
      <label class="admin-field"><span>Canal</span><select><option>Todos</option><option>Catálogo</option><option>Atendimento</option><option>Datas especiais</option></select></label>
      <button class="btn btn-soft" type="button">Filtrar</button>
    </section>

    <section class="admin-kpi-grid">
      <article class="admin-kpi-card"><span>Cupons ativos</span><strong>5</strong><small>Prontos para campanha</small></article>
      <article class="admin-kpi-card"><span>Usos no mês</span><strong>32</strong><small>Simulação visual</small></article>
      <article class="admin-kpi-card"><span>Desconto médio</span><strong>12%</strong><small>Campanhas leves</small></article>
      <article class="admin-kpi-card"><span>Maior campanha</span><strong>MÃES10</strong><small>Data especial</small></article>
    </section>

    <div class="admin-data-table">
      <table>
        <thead><tr><th>Cupom</th><th>Campanha</th><th>Tipo</th><th>Validade</th><th>Status</th><th>Uso</th><th>Ações</th></tr></thead>
        <tbody>
          <tr><td><div class="admin-item-title"><strong>MAES10</strong><small>Dia das Mães</small></div></td><td>Data especial</td><td>10%</td><td>31/05</td><td><span class="admin-badge-ok">Ativo</span></td><td>18 usos</td><td><div class="admin-table-actions"><a href="cupom-form.php">Editar</a><button>Pausar</button></div></td></tr>
          <tr><td><div class="admin-item-title"><strong>FLOR15</strong><small>Primeira compra</small></div></td><td>Captação</td><td>15%</td><td>30/06</td><td><span class="admin-badge-ok">Ativo</span></td><td>9 usos</td><td><div class="admin-table-actions"><a href="cupom-form.php">Editar</a><button>Pausar</button></div></td></tr>
          <tr><td><div class="admin-item-title"><strong>ENTREGA</strong><small>Entrega local</small></div></td><td>Frete</td><td>R$ 10</td><td>15/06</td><td><span class="admin-badge-warn">Pausado</span></td><td>5 usos</td><td><div class="admin-table-actions"><a href="cupom-form.php">Editar</a><button>Ativar</button></div></td></tr>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
