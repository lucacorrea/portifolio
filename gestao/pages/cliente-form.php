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

function canClientFormAccess(string $action, string $nivel): bool
{
    $permissions = [
        'create' => ['admin', 'gerente', 'operador'],
        'edit' => ['admin', 'gerente', 'operador'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireClientFormAccess(string $action, string $nivel): void
{
    if (!canClientFormAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function redirectClientList(string $type, string $message): void
{
    $_SESSION['client_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: clientes.php');
    exit;
}

function clientFormData(array $source, array $fallback = []): array
{
    $fields = ['id', 'name', 'phone', 'cpf', 'address', 'observacao'];
    $data = [];

    foreach ($fields as $field) {
        $data[$field] = $source[$field] ?? $fallback[$field] ?? '';
    }

    if ($data['observacao'] === '') {
        $data['observacao'] = $source['observation'] ?? $fallback['observation'] ?? '';
    }

    return $data;
}

$requestedId = 0;
if (isset($_GET['id'])) {
    $validatedId = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedId === false) {
        redirectClientList('danger', 'Cliente inválido.');
    }
    $requestedId = (int)$validatedId;
}

$client = null;
$formError = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $requestedId > 0) {
    try {
        requireClientFormAccess('edit', $currentNivel);
        $client = $clientService->find($empresaId, $requestedId);

        if (!$client) {
            redirectClientList('danger', 'Cliente não encontrado.');
        }
    } catch (RuntimeException $e) {
        http_response_code(403);
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        log_app_exception($e);
        $formError = 'Não foi possível carregar o cliente.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        requireClientFormAccess('create', $currentNivel);
    } catch (RuntimeException $e) {
        http_response_code(403);
        $formError = $e->getMessage();
    }
}

$formData = clientFormData($client ?? [], ['id' => $requestedId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = clientFormData($_POST);

    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        $rawClientId = trim((string)($_POST['id'] ?? '0'));
        $postedId = filter_var($rawClientId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($rawClientId !== '' && $rawClientId !== '0' && $postedId === false) {
            throw new InvalidArgumentException('Cliente inválido.');
        }

        $clientId = $postedId === false ? 0 : (int)$postedId;
        requireClientFormAccess($clientId > 0 ? 'edit' : 'create', $currentNivel);

        if ($clientId > 0 && !$clientService->find($empresaId, $clientId)) {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }

        $clientService->save($empresaId, [
            'id' => $clientId,
            'name' => (string)($_POST['name'] ?? ''),
            'phone' => (string)($_POST['phone'] ?? ''),
            'cpf' => (string)($_POST['cpf'] ?? ''),
            'address' => (string)($_POST['address'] ?? ''),
            'observacao' => (string)($_POST['observacao'] ?? ''),
        ]);

        redirectClientList('success', $clientId > 0 ? 'Cliente atualizado com sucesso.' : 'Cliente cadastrado com sucesso.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        log_app_exception($e);
        $formError = 'Não foi possível salvar o cliente. Verifique os dados e tente novamente.';
    }
}

$isEditing = (int)($formData['id'] ?? 0) > 0;
$canRenderForm = canClientFormAccess($isEditing ? 'edit' : 'create', $currentNivel);
if (!$canRenderForm) {
    http_response_code(403);
}

$pageId = 'cliente-form-server';
$pageTitle = $isEditing ? 'Editar cliente' : 'Novo cliente';
$activeMenu = 'mais';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .client-alert { margin-bottom: 14px; padding: 13px 15px; color: var(--red); background: rgba(230,83,103,.1); border: 1px solid rgba(230,83,103,.25); border-radius: 16px; font-size: 13px; font-weight: 750; }
  @media (min-width: 720px) { .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .field.full { grid-column: 1 / -1; } }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <a class="back-btn" href="clientes.php" aria-label="Voltar">‹</a>
    <div>
      <p class="micro-label dark-text">Cadastro</p>
      <h1><?= $isEditing ? 'Editar cliente' : 'Novo cliente' ?></h1>
    </div>
    <span></span>
  </div>
</header>

<section class="content-pad">
  <?php if ($formError !== null): ?>
    <div class="client-alert" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>

  <?php if ($canRenderForm && ($formError === null || $_SERVER['REQUEST_METHOD'] === 'POST')): ?>
    <form method="post" class="form-card">
      <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($formData['id'] ?? 0) ?>">

      <div class="form-grid">
        <div class="field">
          <label for="clientName">Nome do cliente</label>
          <input id="clientName" name="name" maxlength="180" value="<?= e($formData['name']) ?>" required>
        </div>

        <div class="field">
          <label for="clientPhone">Telefone</label>
          <input id="clientPhone" name="phone" maxlength="30" value="<?= e($formData['phone']) ?>">
        </div>

        <div class="field">
          <label for="clientCpf">CPF/CNPJ</label>
          <input id="clientCpf" name="cpf" maxlength="20" value="<?= e($formData['cpf']) ?>">
        </div>

        <div class="field">
          <label for="clientAddress">Endereço</label>
          <input id="clientAddress" name="address" maxlength="255" value="<?= e($formData['address']) ?>">
        </div>

        <div class="field full">
          <label for="clientObservation">Observação</label>
          <textarea id="clientObservation" name="observacao"><?= e($formData['observacao']) ?></textarea>
        </div>
      </div>

      <button class="primary-btn section-gap-small" type="submit">Salvar cliente</button>
    </form>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
