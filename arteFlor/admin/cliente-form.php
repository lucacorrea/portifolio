<?php
$adminTitle = 'Cadastro de cliente';
$activeAdmin = 'cliente-form';
require_once __DIR__ . '/../includes/customers.php';
$adminUser = require_admin();

$customerId = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$customer = $customerId > 0 ? customer_find($customerId) : null;
$address = $customerId > 0 ? customer_main_address($customerId) : null;
$error = '';

if ($customerId > 0 && !$customer && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    $error = 'Cliente não encontrado.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $savedId = customer_save_from_request($_POST);
            header('Location: ' . site_url('admin/clientes.php?saved=1&id=' . $savedId));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][customer-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível salvar o cliente. Verifique os dados e tente novamente.';
            $customer = array_merge($customer ?? [], $_POST);
            $address = [
                'rua' => $_POST['endereco'] ?? '',
                'referencia' => $_POST['referencia'] ?? '',
            ];
        }
    }
}

$isEditing = !empty($customer['id']);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $customer[$key] ?? $default;
$addressField = fn(string $key, mixed $default = ''): mixed => $_POST[$key === 'rua' ? 'endereco' : $key] ?? $address[$key] ?? $default;
$initials = strtoupper(substr((string) $field('nome', 'AF'), 0, 2));

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Relacionamento</span>
    <h1><?= $isEditing ? 'Editar cliente' : 'Cadastrar cliente' ?></h1>
    <p>Ficha real com dados de contato, preferências e endereço principal.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/clientes.php') ?>">Voltar para clientes</a>
    <button class="btn btn-primary" type="submit" form="customerForm">Salvar cliente</button>
  </div>
</section>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro ao salvar</strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<form id="customerForm" class="admin-form-shell" method="post" action="<?= site_url('admin/cliente-form.php' . ($isEditing ? '?id=' . (int) $customer['id'] : '')) ?>">
  <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) ($customer['id'] ?? 0) ?>">

  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados do cliente</strong><p>Informações usadas no atendimento e nos pedidos.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Nome</span><input name="nome" value="<?= e((string) $field('nome')) ?>" placeholder="Maria Clara" required></label>
        <label class="admin-field"><span>E-mail</span><input name="email" type="email" value="<?= e((string) $field('email')) ?>" placeholder="cliente@email.com"></label>
        <label class="admin-field"><span>Telefone</span><input name="telefone" value="<?= e((string) $field('telefone')) ?>" placeholder="(97) 90000-0000"></label>
        <label class="admin-field"><span>WhatsApp</span><input name="whatsapp" value="<?= e((string) $field('whatsapp')) ?>" placeholder="(97) 90000-0000"></label>
        <label class="admin-field"><span>Bairro</span><input name="bairro" value="<?= e((string) $field('bairro')) ?>" placeholder="Centro"></label>
        <label class="admin-field"><span>Perfil</span><select name="perfil"><?php foreach (customer_profile_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) $field('perfil', 'novo') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field full"><span>Endereço principal</span><input name="endereco" value="<?= e((string) $addressField('rua')) ?>" placeholder="Rua, número e complemento"></label>
        <label class="admin-field full"><span>Referência</span><input name="referencia" value="<?= e((string) $addressField('referencia')) ?>" placeholder="Próximo a..."></label>
        <label class="admin-field full"><span>Observações</span><textarea name="observacoes" placeholder="Preferências de entrega, atendimento e restrições."><?= e((string) $field('observacoes')) ?></textarea></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Preferências e datas</strong><p>Campos úteis para relacionamento futuro.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Flores preferidas</span><input name="flores_preferidas" value="<?= e((string) $field('flores_preferidas')) ?>" placeholder="Rosas, orquídeas, tons pastel"></label>
        <label class="admin-field"><span>Canal preferido</span><select name="canal_preferido"><option value="">Não definido</option><?php foreach (customer_channel_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) $field('canal_preferido') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Aniversário</span><input name="aniversario" type="date" value="<?= e((string) $field('aniversario')) ?>"></label>
        <label class="admin-field"><span>Data importante</span><input name="data_importante" type="date" value="<?= e((string) $field('data_importante')) ?>"></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="client-profile-preview">
      <span><?= e($initials) ?></span>
      <h3><?= e((string) $field('nome', 'Novo cliente')) ?></h3>
      <p><?= e(customer_profile_options()[(string) $field('perfil', 'novo')] ?? 'Novo') ?> · <?= e((string) $field('bairro', 'Sem bairro')) ?></p>
    </div>
    <?php if ($isEditing): ?>
      <?php
        $statement = db()->prepare(
            'SELECT COUNT(*) AS compras, COALESCE(AVG(total), 0) AS ticket, MAX(criado_em) AS ultima
             FROM pedidos
             WHERE cliente_id = :cliente_id AND status <> "cancelado"'
        );
        $statement->execute(['cliente_id' => (int) $customer['id']]);
        $summary = $statement->fetch() ?: [];
      ?>
      <div class="admin-metric-list">
        <div class="admin-metric-row"><span>Compras</span><strong><?= (int) ($summary['compras'] ?? 0) ?></strong></div>
        <div class="admin-metric-row"><span>Ticket médio</span><strong><?= money_br((float) ($summary['ticket'] ?? 0)) ?></strong></div>
        <div class="admin-metric-row"><span>Última compra</span><strong><?= !empty($summary['ultima']) ? e(date('d/m', strtotime((string) $summary['ultima']))) : '-' ?></strong></div>
      </div>
      <a class="btn btn-soft" href="<?= site_url('admin/pedidos.php?busca=' . rawurlencode((string) $field('nome'))) ?>">Ver pedidos</a>
    <?php else: ?>
      <div class="admin-alert-card admin-alert-info"><strong>Novo cliente</strong>Após salvar, os pedidos vinculados aparecem neste painel.</div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Salvar cliente</button>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
