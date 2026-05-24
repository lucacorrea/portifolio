<?php
$adminTitle = 'Clientes';
$activeAdmin = 'clientes';
require_once __DIR__ . '/../includes/customers.php';
$adminUser = require_admin();

$filters = [
    'search' => customer_clean_text($_GET['search'] ?? '', 120),
    'perfil' => customer_clean_text($_GET['perfil'] ?? '', 40),
    'bairro' => customer_clean_text($_GET['bairro'] ?? '', 120),
];
$clientes = customer_list($filters);
$stats = customer_stats();
$districts = customer_districts();

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Relacionamento</span>
    <h1>Clientes</h1>
    <p>Base real de clientes com perfil, bairro, compras, ticket médio e histórico de pedidos.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/cliente-form.php') ?>">Cadastrar cliente</a></div>
</section>

<?php if (isset($_GET['saved'])): ?>
  <div class="admin-alert-card admin-alert-success" role="status">
    <strong>Cliente salvo</strong>
    Os dados do cliente foram gravados no banco.
  </div>
<?php endif; ?>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/clientes.php') ?>">
  <label class="admin-field">
    <span>Buscar</span>
    <input name="search" type="search" value="<?= e($filters['search']) ?>" placeholder="Nome, contato, e-mail ou bairro">
  </label>
  <label class="admin-field">
    <span>Perfil</span>
    <select name="perfil">
      <option value="">Todos</option>
      <?php foreach (customer_profile_options() as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['perfil'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Bairro</span>
    <select name="bairro">
      <option value="">Todos</option>
      <?php foreach ($districts as $district): ?>
        <option value="<?= e($district) ?>" <?= $filters['bairro'] === $district ? 'selected' : '' ?>><?= e($district) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-soft" type="submit">Filtrar</button>
  <a class="btn btn-outline" href="<?= site_url('admin/clientes.php') ?>">Limpar</a>
</form>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Clientes</span><strong><?= (int) $stats['total'] ?></strong><small>Base cadastrada</small></article>
  <article class="admin-kpi-card"><span>Recorrentes</span><strong><?= (int) $stats['recorrentes'] ?></strong><small>Perfil recorrente</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong><?= money_br((float) $stats['ticket_medio']) ?></strong><small>Pedidos vinculados</small></article>
  <article class="admin-kpi-card"><span>Especiais</span><strong><?= (int) $stats['especiais'] ?></strong><small>Alto relacionamento</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Cliente</th><th>Contato</th><th>Bairro</th><th>Compras</th><th>Ticket médio</th><th>Última compra</th><th>Perfil</th><th>Ações</th></tr></thead>
    <tbody>
      <?php if (empty($clientes)): ?>
        <tr>
          <td colspan="8">
            <div class="admin-empty-row">
              <strong>Nenhum cliente encontrado</strong>
              <span>Cadastre um cliente ou ajuste os filtros.</span>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($clientes as $cliente): ?>
        <tr>
          <td>
            <strong><?= e((string) $cliente['nome']) ?></strong>
            <small><?= e((string) ($cliente['email'] ?? '')) ?></small>
          </td>
          <td>
            <?= e((string) (($cliente['whatsapp'] ?: $cliente['telefone']) ?? 'Sem contato')) ?>
            <small><?= e(customer_channel_options()[(string) ($cliente['canal_preferido'] ?? '')] ?? 'Canal não definido') ?></small>
          </td>
          <td><?= e((string) ($cliente['bairro'] ?? 'Não informado')) ?></td>
          <td><?= (int) $cliente['compras'] ?></td>
          <td><?= money_br((float) $cliente['ticket_medio']) ?></td>
          <td><?= !empty($cliente['ultima_compra']) ? e(date('d/m/Y', strtotime((string) $cliente['ultima_compra']))) : 'Sem compras' ?></td>
          <td><span class="<?= e(customer_badge_class((string) $cliente['perfil'])) ?>"><?= e(customer_profile_options()[(string) $cliente['perfil']] ?? (string) $cliente['perfil']) ?></span></td>
          <td>
            <div class="admin-table-actions">
              <a href="<?= site_url('admin/cliente-form.php?id=' . (int) $cliente['id']) ?>">Editar</a>
              <a href="<?= site_url('admin/pedidos.php?busca=' . rawurlencode((string) $cliente['nome'])) ?>">Histórico</a>
              <a href="<?= site_url('admin/pedidos.php?busca=' . rawurlencode((string) ($cliente['whatsapp'] ?: $cliente['telefone']))) ?>">Pedidos</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
