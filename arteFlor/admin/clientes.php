<?php
$adminTitle = 'Clientes';
$activeAdmin = 'clientes';
require_once __DIR__ . '/../includes/admin-head.php';
$clientes = [
    ['nome' => 'Maria Clara', 'contato' => '(97) 90000-1234', 'bairro' => 'Centro', 'compras' => 6, 'ticket' => 142.5, 'ultima' => '17/05/2026', 'perfil' => 'recorrente'],
    ['nome' => 'Ana Beatriz', 'contato' => '(97) 90000-4455', 'bairro' => 'União', 'compras' => 2, 'ticket' => 98.9, 'ultima' => '14/05/2026', 'perfil' => 'novo'],
    ['nome' => 'Rafael Lima', 'contato' => '(97) 90000-7788', 'bairro' => 'Tauá Mirim', 'compras' => 9, 'ticket' => 181.3, 'ultima' => '12/05/2026', 'perfil' => 'especial'],
    ['nome' => 'Beatriz Souza', 'contato' => '(97) 90000-9911', 'bairro' => 'Centro', 'compras' => 4, 'ticket' => 119.9, 'ultima' => '10/05/2026', 'perfil' => 'recorrente'],
];
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Relacionamento</span>
    <h1>Clientes</h1>
    <p>Lista fictícia com perfil, bairro, compras, ticket médio e ações comerciais.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/cliente-form.php') ?>">Cadastrar cliente</a></div>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Buscar</span><input placeholder="Nome, contato ou bairro"></label>
  <label class="admin-field"><span>Perfil</span><select><option>Todos</option><option>Novo</option><option>Recorrente</option><option>Especial</option></select></label>
  <label class="admin-field"><span>Bairro</span><select><option>Todos</option><option>Centro</option><option>União</option><option>Tauá Mirim</option></select></label>
  <button class="btn btn-soft" type="button">Filtrar</button>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Clientes</span><strong>84</strong><small>Base visual</small></article>
  <article class="admin-kpi-card"><span>Recorrentes</span><strong>42</strong><small>Compraram mais de 2x</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong>R$ 128</strong><small>Por cliente</small></article>
  <article class="admin-kpi-card"><span>Especiais</span><strong>9</strong><small>Alto relacionamento</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Cliente</th><th>Contato</th><th>Bairro</th><th>Compras</th><th>Ticket médio</th><th>Última compra</th><th>Perfil</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($clientes as $cliente): ?>
        <tr>
          <td><strong><?= e($cliente['nome']) ?></strong></td>
          <td><?= e($cliente['contato']) ?></td>
          <td><?= e($cliente['bairro']) ?></td>
          <td><?= (int) $cliente['compras'] ?></td>
          <td><?= money_br($cliente['ticket']) ?></td>
          <td><?= e($cliente['ultima']) ?></td>
          <td><span class="<?= $cliente['perfil'] === 'especial' ? 'admin-badge-soft' : ($cliente['perfil'] === 'recorrente' ? 'admin-badge-ok' : 'admin-badge-info') ?>"><?= e(ucfirst($cliente['perfil'])) ?></span></td>
          <td><div class="admin-table-actions"><a href="<?= site_url('admin/cliente-form.php') ?>">Editar</a><button type="button">Histórico</button><button type="button">Criar pedido</button></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
