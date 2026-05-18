<?php
$adminTitle = 'Cupons';
$activeAdmin = 'cupons';
require_once __DIR__ . '/../includes/admin-head.php';
$cupons = [
    ['codigo' => 'MAES10', 'campanha' => 'Dia das Mães', 'tipo' => 'Percentual', 'valor' => '10%', 'validade' => '31/05/2026', 'status' => 'Ativo', 'usos' => 18],
    ['codigo' => 'FLOR15', 'campanha' => 'Primeira compra', 'tipo' => 'Percentual', 'valor' => '15%', 'validade' => '30/06/2026', 'status' => 'Ativo', 'usos' => 9],
    ['codigo' => 'ENTREGA', 'campanha' => 'Entrega local', 'tipo' => 'Valor fixo', 'valor' => 'R$ 10', 'validade' => '15/06/2026', 'status' => 'Pausado', 'usos' => 5],
];
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Promoções</span>
    <h1>Cupons</h1>
    <p>Lista visual de campanhas, tipos de desconto, validade, usos e ações.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/cupom-form.php') ?>">Cadastrar cupom</a></div>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Buscar</span><input placeholder="Código ou campanha"></label>
  <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Ativo</option><option>Pausado</option><option>Expirado</option></select></label>
  <label class="admin-field"><span>Tipo</span><select><option>Todos</option><option>Percentual</option><option>Valor fixo</option><option>Frete</option></select></label>
  <label class="admin-field"><span>Canal</span><select><option>Todos</option><option>Catálogo</option><option>PDV</option><option>Atendimento</option></select></label>
  <button class="btn btn-soft" type="button">Filtrar</button>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Ativos</span><strong>2</strong><small>Campanhas em vigor</small></article>
  <article class="admin-kpi-card"><span>Usos</span><strong>32</strong><small>No mês visual</small></article>
  <article class="admin-kpi-card"><span>Desconto médio</span><strong>12%</strong><small>Campanhas leves</small></article>
  <article class="admin-kpi-card"><span>Maior campanha</span><strong>MAES10</strong><small>Data especial</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Cupom</th><th>Campanha</th><th>Tipo</th><th>Valor</th><th>Validade</th><th>Status</th><th>Usos</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($cupons as $cupom): ?>
        <tr>
          <td><strong><?= e($cupom['codigo']) ?></strong></td>
          <td><?= e($cupom['campanha']) ?></td>
          <td><?= e($cupom['tipo']) ?></td>
          <td><?= e($cupom['valor']) ?></td>
          <td><?= e($cupom['validade']) ?></td>
          <td><span class="<?= $cupom['status'] === 'Ativo' ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e($cupom['status']) ?></span></td>
          <td><?= (int) $cupom['usos'] ?></td>
          <td><div class="admin-table-actions"><a href="<?= site_url('admin/cupom-form.php') ?>">Editar</a><button type="button"><?= $cupom['status'] === 'Ativo' ? 'Pausar' : 'Ativar' ?></button></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
