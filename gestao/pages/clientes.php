<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ClientService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');
$clientService = new ClientService();

function canClientAccess(string $action, string $nivel): bool
{
    $permissions = [
        'view' => ['admin', 'gerente', 'operador', 'leitor'],
        'create' => ['admin', 'gerente', 'operador'],
        'edit' => ['admin', 'gerente', 'operador'],
        'account' => ['admin', 'gerente', 'operador', 'leitor'],
        'delete' => ['admin', 'gerente'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireClientAccess(string $action, string $nivel): void
{
    if (!canClientAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function redirectClients(string $type, string $message, string $query = '', string $filter = 'todos'): void
{
    $_SESSION['client_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($filter !== 'todos') {
        $params['filtro'] = $filter;
    }

    header('Location: clientes.php' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

function clientMatchesFilter(array $client, string $filter): bool
{
    $status = (string)($client['status'] ?? '');

    return match ($filter) {
        'em_dia' => $status === 'Em dia',
        'devendo' => $status === 'Devendo',
        'atrasados' => $status === 'Atrasado',
        default => true,
    };
}

function clientStatusClass(string $status): string
{
    return match ($status) {
        'Em dia' => 'green',
        'Atrasado' => 'red',
        default => 'orange',
    };
}

function formatClientMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatClientDue(mixed $value): string
{
    $due = trim((string)$value);
    if ($due === '') {
        return 'Sem vencimento';
    }

    $timestamp = strtotime($due);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Sem vencimento';
}

try {
    requireClientAccess('view', $currentNivel);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit('Acesso negado.');
}

$allowedFilters = ['todos', 'em_dia', 'devendo', 'atrasados'];
$query = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filtro'] ?? 'todos');
$filter = in_array($filter, $allowedFilters, true) ? $filter : 'todos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        requireClientAccess('delete', $currentNivel);

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            throw new InvalidArgumentException('Cliente inválido.');
        }

        if (!$clientService->find($empresaId, (int)$id)) {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }

        $clientService->inactivate($empresaId, (int)$id);
        redirectClients('success', 'Cliente inativado com sucesso.', $query, $filter);
    } catch (InvalidArgumentException | RuntimeException $e) {
        redirectClients('danger', $e->getMessage(), $query, $filter);
    } catch (Throwable $e) {
        log_app_exception($e);
        redirectClients('danger', 'Não foi possível inativar o cliente.', $query, $filter);
    }
}

$clients = [];
$loadError = null;
try {
    $clients = $clientService->list($empresaId, $query);
} catch (Throwable $e) {
    log_app_exception($e);
    $loadError = 'Não foi possível carregar os clientes agora.';
}

$clients = array_values(array_filter(
    $clients,
    static fn (array $client): bool => clientMatchesFilter($client, $filter)
));

$flash = $_SESSION['client_flash'] ?? null;
unset($_SESSION['client_flash']);

$pageId = 'clientes-server';
$pageTitle = 'Clientes';
$activeMenu = 'mais';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .client-alert { margin-bottom: 14px; padding: 13px 15px; border: 1px solid var(--line); border-radius: 16px; font-size: 13px; font-weight: 750; }
  .client-alert.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .client-alert.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .client-search-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 9px; }
  .client-search-form .search-box { min-width: 0; }
  .client-search-form .secondary-btn { width: auto; min-height: 54px; padding: 0 16px; }
  .filter-pills a { height: 31px; flex: 0 0 auto; display: inline-flex; align-items: center; padding: 0 14px; border-radius: 999px; color: var(--muted); background: #fff; border: 1px solid var(--line); font-size: 12px; font-weight: 800; }
  .filter-pills a.active { color: var(--blue); background: var(--blue-soft); border-color: var(--blue-line); }
  .client-actions form { margin: 0; }
  .client-actions button { width: 100%; border: 0; }
  .client-actions .danger-mini { color: var(--red); background: rgba(230,83,103,.1); }
  .client-actions.one { grid-template-columns: 1fr; }
  @media (max-width: 430px) { .client-search-form { grid-template-columns: 1fr; } .client-search-form .secondary-btn { width: 100%; } }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Clientes e contas</p>
      <h1>Clientes</h1>
    </div>
    <?php if (canClientAccess('create', $currentNivel)): ?>
      <a class="round-btn" href="cliente-form.php" aria-label="Cadastrar cliente">+</a>
    <?php endif; ?>
  </div>
</header>

<section class="content-pad">
  <?php if (is_array($flash)): ?>
    <div class="client-alert <?= e((string)($flash['type'] ?? 'danger')) ?>" role="status">
      <?= e((string)($flash['message'] ?? '')) ?>
    </div>
  <?php endif; ?>

  <?php if ($loadError !== null): ?>
    <div class="client-alert danger" role="alert"><?= e($loadError) ?></div>
  <?php endif; ?>

  <form class="client-search-form" method="get" action="clientes.php">
    <label class="search-box">
      <span data-icon="search"></span>
      <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar por nome, telefone ou CPF/CNPJ">
    </label>
    <input type="hidden" name="filtro" value="<?= e($filter) ?>">
    <button class="secondary-btn" type="submit">Buscar</button>
  </form>

  <?php
  $filterLabels = [
      'todos' => 'Todos',
      'em_dia' => 'Em dia',
      'devendo' => 'Devendo',
      'atrasados' => 'Atrasados',
  ];
  ?>
  <nav class="filter-pills" aria-label="Filtros de clientes">
    <?php foreach ($filterLabels as $filterKey => $filterLabel): ?>
      <?php $filterQuery = array_filter(['q' => $query, 'filtro' => $filterKey], static fn ($value): bool => $value !== ''); ?>
      <a class="<?= $filter === $filterKey ? 'active' : '' ?>" href="clientes.php?<?= e(http_build_query($filterQuery)) ?>">
        <?= e($filterLabel) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div id="clientsList">
    <?php if (!$clients && $loadError === null): ?>
      <article class="summary-card">Nenhum cliente encontrado.</article>
    <?php endif; ?>

    <?php foreach ($clients as $client): ?>
      <?php
      $status = (string)($client['status'] ?? 'Em dia');
      $actions = (canClientAccess('edit', $currentNivel) ? 1 : 0) + (canClientAccess('delete', $currentNivel) ? 1 : 0);
      ?>
      <article class="client-card">
        <div class="client-top">
          <div>
            <h3><?= e((string)$client['name']) ?></h3>
            <p><?= e((string)($client['phone'] ?: 'Sem telefone')) ?> · <?= e((string)($client['cpf'] ?: 'Sem CPF/CNPJ')) ?></p>
            <p><?= e((string)($client['address'] ?: 'Sem endereço')) ?></p>
          </div>
          <span class="badge <?= e(clientStatusClass($status)) ?>"><?= e($status) ?></span>
        </div>

        <?php if (canClientAccess('account', $currentNivel)): ?>
          <div class="client-summary">
            <div><span>Valor em aberto</span><strong><?= e(formatClientMoney($client['debt'] ?? 0)) ?></strong></div>
            <div><span>Valor pago</span><strong><?= e(formatClientMoney($client['paid'] ?? 0)) ?></strong></div>
            <div><span>Vencimento mais próximo</span><strong><?= e(formatClientDue($client['due'] ?? '')) ?></strong></div>
          </div>
        <?php endif; ?>

        <?php if ($actions > 0): ?>
          <div class="client-actions <?= $actions === 1 ? 'one' : '' ?>">
            <?php if (canClientAccess('edit', $currentNivel)): ?>
              <a href="cliente-form.php?id=<?= (int)$client['id'] ?>">Editar</a>
            <?php endif; ?>
            <?php if (canClientAccess('delete', $currentNivel)): ?>
              <form method="post" action="clientes.php?<?= e(http_build_query(array_filter(['q' => $query, 'filtro' => $filter]))) ?>" onsubmit="return confirm('Inativar este cliente?');">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                <button class="danger-mini" type="submit">Inativar</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
