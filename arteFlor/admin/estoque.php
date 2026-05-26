<?php
$adminTitle = 'Estoque';
$activeAdmin = 'estoque';
require_once __DIR__ . '/../includes/inventory.php';
$adminUser = require_admin();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            inventory_save_movement_from_request($adminUser);
            header('Location: ' . site_url('admin/estoque.php?saved=1'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][inventory-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível registrar a movimentação. Verifique os dados e tente novamente.';
        }
    }
}

$products = inventory_available_products();
$stats = inventory_stats();
$criticalProducts = inventory_low_stock_products(6);
$movements = inventory_recent_movements(25);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $default;
$defaultMovedAt = date('Y-m-d\TH:i');

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Estoque</span>
    <h1>Controle de estoque</h1>
    <p>Entradas, saídas, ajustes, perdas, reservas e alertas de estoque baixo com dados do banco.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Ver produtos</a>
    <a class="btn btn-primary" href="#inventoryMovementForm">Registrar movimentação</a>
  </div>
</section>

<?php if (isset($_GET['saved'])): ?>
  <div class="admin-alert-card admin-alert-success" role="status">
    <strong>Movimentação registrada</strong>
    O estoque do produto foi atualizado e o histórico foi gravado no banco.
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro ao movimentar</strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Entradas</span><strong><?= $stats['entradas_mes'] ?></strong><small>Unidades no mês</small></article>
  <article class="admin-kpi-card"><span>Saídas</span><strong><?= $stats['saidas_mes'] ?></strong><small>Vendas e reservas no mês</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong><?= $stats['estoque_baixo'] ?></strong><small>Produtos críticos</small></article>
  <article class="admin-kpi-card"><span>Perdas</span><strong><?= $stats['perdas_mes'] ?></strong><small>Unidades baixadas no mês</small></article>
</section>

<section class="admin-grid-2">
  <form id="inventoryMovementForm" class="admin-form-card" method="post" action="<?= site_url('admin/estoque.php') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
    <div class="admin-panel-header"><div><span class="badge">Movimentação</span><h2>Entrada, saída ou ajuste</h2></div></div>
    <div class="admin-form-grid">
      <label class="admin-field full">
        <span>Produto</span>
        <select name="produto_id" required>
          <option value="">Selecione um produto</option>
          <?php foreach ($products as $product): ?>
            <option value="<?= (int) $product['id'] ?>" <?= (int) $field('produto_id', 0) === (int) $product['id'] ? 'selected' : '' ?>>
              <?= e($product['nome']) ?> · <?= e($product['sku']) ?> · estoque <?= (int) $product['estoque'] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="admin-field">
        <span>Tipo</span>
        <select name="tipo" required>
          <?php foreach (inventory_movement_type_options() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) $field('tipo', 'entrada') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="admin-field">
        <span>Quantidade</span>
        <input name="quantidade" type="number" min="0" value="<?= e((string) $field('quantidade', 1)) ?>" required>
      </label>
      <label class="admin-field">
        <span>Origem</span>
        <select name="origem" required>
          <?php foreach (inventory_origin_options() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) $field('origem', 'compra') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="admin-field">
        <span>Custo unitário</span>
        <input name="custo_unitario" type="number" min="0" step="0.01" value="<?= e((string) $field('custo_unitario', '')) ?>" placeholder="Opcional">
      </label>
      <label class="admin-field">
        <span>Data</span>
        <input name="movimentado_em" type="datetime-local" value="<?= e((string) $field('movimentado_em', $defaultMovedAt)) ?>" required>
      </label>
      <label class="admin-field full">
        <span>Motivo</span>
        <textarea name="motivo" placeholder="Descreva o motivo da movimentação"><?= e((string) $field('motivo')) ?></textarea>
      </label>
    </div>
    <div class="admin-alert-card admin-alert-info">
      <strong>Regra de ajuste</strong>
      Entrada soma estoque. Saída, perda e reserva subtraem. Ajuste define o novo saldo final informado em quantidade.
    </div>
    <div class="admin-action-row">
      <button class="btn btn-primary" type="submit">Salvar movimentação</button>
      <button class="btn btn-soft" type="reset">Limpar</button>
    </div>
  </form>

  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge badge-rose">Críticos</span><h2>Produtos em atenção</h2></div></div>
    <div class="admin-metric-list">
      <?php foreach ($criticalProducts as $product): ?>
        <?php $badge = (int) $product['estoque'] <= 0 ? 'admin-badge-danger' : 'admin-badge-warn'; ?>
        <div class="admin-metric-row">
          <span><?= e($product['nome']) ?><small><?= e($product['sku']) ?> · mínimo <?= (int) $product['estoque_minimo'] ?></small></span>
          <strong class="<?= $badge ?>"><?= (int) $product['estoque'] ?> un.</strong>
        </div>
      <?php endforeach; ?>
      <?php if (empty($criticalProducts)): ?>
        <div class="admin-empty-row">
          <strong>Nenhum produto crítico</strong>
          <span>Todos os produtos estão acima do estoque mínimo.</span>
        </div>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="admin-panel-card">
  <div class="admin-panel-header">
    <div><span class="badge">Histórico</span><h2>Movimentações recentes</h2></div>
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php?estoque=baixo') ?>">Ver estoque baixo</a>
  </div>
  <div class="admin-data-table">
    <table>
      <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Saldo</th><th>Responsável</th><th>Motivo</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($movements as $movement): ?>
          <?php
            $type = (string) $movement['tipo'];
            $status = (string) $movement['status'];
            $movedAt = strtotime((string) $movement['movimentado_em']);
          ?>
          <tr>
            <td><?= $movedAt ? e(date('d/m/Y H:i', $movedAt)) : '-' ?></td>
            <td>
              <strong><?= e($movement['produto_nome']) ?></strong>
              <?php if (!empty($movement['produto_cor_nome'])): ?>
                <small class="admin-color-line"><i class="admin-color-dot" style="--color: <?= e((string) ($movement['produto_cor_hex'] ?: '#FFFFFF')) ?>"></i><?= e((string) $movement['produto_cor_nome']) ?></small>
              <?php endif; ?>
              <small><?= e($movement['produto_sku']) ?></small>
            </td>
            <td><span class="<?= inventory_type_badge_class($type) ?>"><?= e(inventory_movement_type_options()[$type] ?? $type) ?></span></td>
            <td><?= (int) $movement['quantidade'] ?></td>
            <td><strong><?= (int) $movement['estoque_novo'] ?> un.</strong><small>Antes: <?= (int) $movement['estoque_anterior'] ?></small></td>
            <td><?= e($movement['responsavel_nome'] ?: ($movement['usuario_nome'] ?? 'Admin')) ?></td>
            <td><?= e((string) ($movement['motivo'] ?? 'Sem motivo informado')) ?></td>
            <td><span class="<?= $status === 'concluido' ? 'admin-badge-ok' : ($status === 'cancelado' ? 'admin-badge-danger' : 'admin-badge-warn') ?>"><?= e(inventory_status_label($status)) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($movements)): ?>
          <tr>
            <td colspan="8">
              <div class="admin-empty-row">
                <strong>Nenhuma movimentação registrada</strong>
                <span>Use o formulário acima para criar o primeiro lançamento de estoque.</span>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
