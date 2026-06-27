<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\StoreRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\CompanyBrandService;
use App\Services\MatrixAdminService;
use App\Services\StoreService;

Auth::requireLogin();

$user = Auth::user();

if (!$user) {
    header('Location: ../login.php');
    exit;
}

$usuarioId = (int)$user['id'];
$empresaId = (int)$user['empresa_id'];
$storeService = new StoreService();
$storeRepository = new StoreRepository();
$brandService = new CompanyBrandService();
$matrixAdminService = new MatrixAdminService();

function redirectStores(string $type, string $message): void
{
    $_SESSION['lojas_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: lojas.php');
    exit;
}

function storeDisplayName(array $company): string
{
    $fantasyName = trim((string)($company['nome_fantasia'] ?? ''));
    return $fantasyName !== '' ? $fantasyName : trim((string)($company['nome'] ?? ''));
}

function storeDate(mixed $value): string
{
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Token de segurança inválido. Recarregue a página e tente novamente.');
        }

        $action = (string)($_POST['acao'] ?? '');
        $storeId = (int)($_POST['loja_id'] ?? 0);
        $logoFile = $_FILES['logo'] ?? null;

        match ($action) {
            'criar_filial', 'criar_loja' => $storeService->createStore($usuarioId, $empresaId, $_POST, is_array($logoFile) ? $logoFile : null),
            'editar_filial', 'editar_loja' => $storeService->updateStore($usuarioId, $empresaId, $storeId, $_POST, is_array($logoFile) ? $logoFile : null),
            'ativar_filial', 'ativar_loja' => $storeService->activateStore($usuarioId, $empresaId, $storeId),
            'inativar_filial', 'inativar_loja' => $storeService->deactivateStore($usuarioId, $empresaId, $storeId),
            default => throw new RuntimeException('Ação inválida.'),
        };

        redirectStores('success', 'Operação realizada com sucesso.');
    } catch (Throwable $e) {
        log_app_exception($e);
        redirectStores('danger', $e->getMessage());
    }
}

try {
    $activeCompany = $storeRepository->findById($empresaId) ?? [];
    $stores = $storeService->listChildren($usuarioId, $empresaId);
    $primaryAdmin = $matrixAdminService->findPrimaryAdmin($empresaId);
} catch (Throwable $e) {
    log_app_exception($e);
    http_response_code(403);
    exit(e($e->getMessage()));
}

$flash = $_SESSION['lojas_flash'] ?? null;
unset($_SESSION['lojas_flash']);

$csrfToken = Csrf::token();
$activeBrand = $brandService->getForCompany($empresaId, '../');

$pageId = 'lojas';
$pageTitle = 'Filiais';
$activeMenu = 'lojas';

require_once __DIR__ . '/layout/header.php';
?>

<style>
  .stores-page {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding: 18px 16px 118px;
    display: grid;
    gap: 16px;
  }

  .stores-hero,
  .store-card,
  .store-form-panel {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
  }

  .stores-hero {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 18px;
  }

  .store-logo {
    width: 64px;
    height: 64px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 18px;
    color: #1657a7;
    background: #eef6ff;
    font-weight: 950;
  }

  .store-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 7px;
    background: #fff;
  }

  .stores-copy {
    min-width: 0;
  }

  .stores-copy h1,
  .stores-copy h2,
  .stores-copy p { margin: 0; }

  .stores-copy h1 {
    font-size: 1.55rem;
    line-height: 1.12;
    overflow-wrap: anywhere;
  }

  .stores-copy h2 {
    font-size: 1rem;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }

  .stores-copy p {
    margin-top: 5px;
    color: #64748b;
    font-size: .84rem;
    font-weight: 750;
    line-height: 1.4;
    overflow-wrap: anywhere;
  }

  .stores-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 9px;
  }

  .store-badge {
    min-height: 24px;
    display: inline-flex;
    align-items: center;
    padding: 0 9px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1657a7;
    font-size: .72rem;
    font-weight: 900;
  }

  .store-badge.success { background: #dcfce7; color: #16a34a; }
  .store-badge.danger { background: #fee2e2; color: #dc2626; }

  .stores-alert {
    padding: 13px 15px;
    border-radius: 16px;
    font-weight: 850;
  }

  .stores-alert.success { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
  .stores-alert.danger { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }

  .store-form-panel {
    padding: 16px;
    display: grid;
    gap: 13px;
  }

  .store-form-panel h2 {
    margin: 0;
    font-size: 1.1rem;
  }

  .store-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .store-field {
    display: grid;
    gap: 6px;
    min-width: 0;
  }

  .store-field.full { grid-column: 1 / -1; }
  .store-field label {
    color: #334155;
    font-size: .82rem;
    font-weight: 850;
  }

  .store-field input {
    width: 100%;
    min-height: 45px;
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    padding: 9px 11px;
    font-size: 16px;
  }

  .store-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
  }

  .store-btn {
    min-height: 42px;
    border: 0;
    border-radius: 14px;
    padding: 0 14px;
    color: #fff;
    background: #1657a7;
    font-weight: 950;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .store-btn.secondary { color: #102033; background: #e5e7eb; }
  .store-btn.danger { background: #dc2626; }
  .store-btn.success { background: #16a34a; }

  .stores-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .store-card {
    padding: 15px;
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr);
    gap: 13px;
  }

  .store-card .store-actions {
    grid-column: 1 / -1;
  }

  .store-empty {
    padding: 18px;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    background: #f8fafc;
    border-radius: 18px;
    font-weight: 800;
    text-align: center;
  }

  @media (max-width: 760px) {
    .stores-hero {
      grid-template-columns: 58px minmax(0, 1fr);
    }
    .stores-hero .store-actions {
      grid-column: 1 / -1;
      justify-content: stretch;
    }
    .stores-hero .store-btn,
    .store-card .store-btn,
    .store-card form {
      width: 100%;
    }
    .store-form-grid,
    .stores-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<section class="stores-page">
  <header class="stores-hero">
    <div class="store-logo" aria-hidden="true">
      <?php if ($activeBrand['logo_url'] !== ''): ?>
        <img src="<?= e((string)$activeBrand['logo_url']) ?>" alt="">
      <?php else: ?>
        <?= e((string)$activeBrand['initials']) ?>
      <?php endif; ?>
    </div>
    <div class="stores-copy">
      <h1>Filiais</h1>
      <p>Matriz ativa: <?= e(storeDisplayName($activeCompany)) ?></p>
      <p>Administrador principal: <?= $primaryAdmin ? e((string)$primaryAdmin['nome']) . ' · ' . e((string)$primaryAdmin['email']) : 'Pendente de definição' ?></p>
      <div class="stores-badges">
        <span class="store-badge">Matriz</span>
        <span class="store-badge success">Ativa</span>
      </div>
    </div>
    <div class="store-actions">
      <a class="store-btn secondary" href="configuracoes.php">Configurações</a>
    </div>
  </header>

  <?php if ($flash): ?>
    <div class="stores-alert <?= e((string)$flash['type']) ?>" role="alert">
      <?= e((string)$flash['message']) ?>
    </div>
  <?php endif; ?>

  <section class="store-form-panel" aria-labelledby="createStoreTitle">
    <h2 id="createStoreTitle">Cadastrar nova filial</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="acao" value="criar_filial">
      <div class="store-form-grid">
        <div class="store-field">
          <label for="nome">Nome *</label>
          <input id="nome" name="nome" maxlength="180" required>
        </div>
        <div class="store-field">
          <label for="nome_fantasia">Nome fantasia</label>
          <input id="nome_fantasia" name="nome_fantasia" maxlength="180">
        </div>
        <div class="store-field">
          <label for="codigo">Código</label>
          <input id="codigo" name="codigo" maxlength="50" pattern="[A-Za-z0-9_-]+">
        </div>
        <div class="store-field">
          <label for="cpf_cnpj">CPF/CNPJ</label>
          <input id="cpf_cnpj" name="cpf_cnpj" maxlength="20">
        </div>
        <div class="store-field">
          <label for="telefone">Telefone</label>
          <input id="telefone" name="telefone" maxlength="30">
        </div>
        <div class="store-field">
          <label for="logo">Logo</label>
          <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="store-field full">
          <label for="endereco">Endereço</label>
          <input id="endereco" name="endereco" maxlength="255">
        </div>
      </div>
      <div class="store-actions">
        <button class="store-btn" type="submit">Cadastrar filial</button>
      </div>
    </form>
  </section>

  <section class="stores-grid" aria-label="Filiais diretas">
    <?php if (!$stores): ?>
      <div class="store-empty">Nenhuma filial cadastrada para a matriz atual.</div>
    <?php endif; ?>

    <?php foreach ($stores as $store): ?>
      <?php
        $storeBrand = $brandService->getForCompany((int)$store['id'], '../');
        $isActive = (int)$store['ativo'] === 1;
      ?>
      <article class="store-card">
        <div class="store-logo" aria-hidden="true">
          <?php if ($storeBrand['logo_url'] !== ''): ?>
            <img src="<?= e((string)$storeBrand['logo_url']) ?>" alt="">
          <?php else: ?>
            <?= e((string)$storeBrand['initials']) ?>
          <?php endif; ?>
        </div>
        <div class="stores-copy">
          <h2><?= e(storeDisplayName($store)) ?></h2>
          <p><?= e((string)$store['nome']) ?></p>
          <?php if (!empty($store['codigo'])): ?><p>Código: <?= e((string)$store['codigo']) ?></p><?php endif; ?>
          <?php if (!empty($store['cpf_cnpj'])): ?><p>CPF/CNPJ: <?= e((string)$store['cpf_cnpj']) ?></p><?php endif; ?>
          <?php if (!empty($store['telefone'])): ?><p>Telefone: <?= e((string)$store['telefone']) ?></p><?php endif; ?>
          <?php if (!empty($store['endereco'])): ?><p>Endereço: <?= e((string)$store['endereco']) ?></p><?php endif; ?>
          <p>Criada em <?= e(storeDate($store['criado_em'] ?? null)) ?></p>
          <div class="stores-badges">
            <span class="store-badge">Filial</span>
            <span class="store-badge <?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Ativa' : 'Inativa' ?></span>
          </div>
        </div>

        <details class="store-form-panel">
          <summary>Editar filial</summary>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="acao" value="editar_filial">
            <input type="hidden" name="loja_id" value="<?= (int)$store['id'] ?>">
            <div class="store-form-grid">
              <div class="store-field">
                <label for="nome_<?= (int)$store['id'] ?>">Nome *</label>
                <input id="nome_<?= (int)$store['id'] ?>" name="nome" maxlength="180" required value="<?= e((string)$store['nome']) ?>">
              </div>
              <div class="store-field">
                <label for="fantasia_<?= (int)$store['id'] ?>">Nome fantasia</label>
                <input id="fantasia_<?= (int)$store['id'] ?>" name="nome_fantasia" maxlength="180" value="<?= e((string)($store['nome_fantasia'] ?? '')) ?>">
              </div>
              <div class="store-field">
                <label for="codigo_<?= (int)$store['id'] ?>">Código</label>
                <input id="codigo_<?= (int)$store['id'] ?>" name="codigo" maxlength="50" pattern="[A-Za-z0-9_-]+" value="<?= e((string)($store['codigo'] ?? '')) ?>">
              </div>
              <div class="store-field">
                <label for="telefone_<?= (int)$store['id'] ?>">Telefone</label>
                <input id="telefone_<?= (int)$store['id'] ?>" name="telefone" maxlength="30" value="<?= e((string)($store['telefone'] ?? '')) ?>">
              </div>
              <div class="store-field">
                <label for="cpf_<?= (int)$store['id'] ?>">CPF/CNPJ</label>
                <input id="cpf_<?= (int)$store['id'] ?>" name="cpf_cnpj" maxlength="20" value="<?= e((string)($store['cpf_cnpj'] ?? '')) ?>">
              </div>
              <div class="store-field">
                <label for="logo_<?= (int)$store['id'] ?>">Logo</label>
                <input id="logo_<?= (int)$store['id'] ?>" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
              </div>
              <div class="store-field full">
                <label for="endereco_<?= (int)$store['id'] ?>">Endereço</label>
                <input id="endereco_<?= (int)$store['id'] ?>" name="endereco" maxlength="255" value="<?= e((string)($store['endereco'] ?? '')) ?>">
              </div>
            </div>
            <div class="store-actions">
              <button class="store-btn secondary" type="submit">Salvar edição</button>
            </div>
          </form>
        </details>

        <div class="store-actions">
          <?php if ($isActive): ?>
            <form method="post" action="../api/lojas/selecionar.php">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="empresa_id" value="<?= (int)$store['id'] ?>">
              <button class="store-btn" type="submit">Entrar</button>
            </form>
          <?php endif; ?>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="loja_id" value="<?= (int)$store['id'] ?>">
            <input type="hidden" name="acao" value="<?= $isActive ? 'inativar_filial' : 'ativar_filial' ?>">
            <button
              class="store-btn <?= $isActive ? 'danger' : 'success' ?>"
              type="submit"
              <?php if ($isActive): ?>onclick="return confirm('Inativar esta loja? Usuários não poderão selecioná-la até reativação.')"<?php endif; ?>>
              <?= $isActive ? 'Inativar' : 'Ativar' ?>
            </button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
