<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;
use App\Security\Csrf;
use App\Services\CompanyBrandService;
use App\Services\CompanyContextService;
use App\Services\MatrixService;
use App\Services\PlatformAuthorizationService;

Auth::requireLogin();

$user = Auth::user();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

$usuarioId = (int)$user['id'];
$platform = new PlatformAuthorizationService();

try {
    $platform->assertPlatformOwner($usuarioId);
} catch (Throwable $e) {
    log_app_exception($e);
    http_response_code(403);
    exit('Acesso negado.');
}

$matrixService = new MatrixService();
$brandService = new CompanyBrandService();

function redirectMatrices(string $type, string $message): void
{
    $_SESSION['matrizes_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: matrizes.php');
    exit;
}

function matrixDisplayName(array $matrix): string
{
    $fantasy = trim((string)($matrix['nome_fantasia'] ?? ''));
    return $fantasy !== '' ? $fantasy : trim((string)($matrix['nome'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Token de segurança inválido. Recarregue a página e tente novamente.');
        }

        $action = (string)($_POST['acao'] ?? '');
        $matrixId = (int)($_POST['matriz_id'] ?? 0);
        $logoFile = $_FILES['logo'] ?? null;
        $logo = is_array($logoFile) ? $logoFile : null;

        match ($action) {
            'criar_matriz' => $matrixService->createMatrix($usuarioId, $_POST, $logo),
            'editar_matriz' => $matrixService->updateMatrix($usuarioId, $matrixId, $_POST, $logo),
            'ativar_matriz' => $matrixService->setMatrixActive($usuarioId, $matrixId, true),
            'inativar_matriz' => $matrixService->setMatrixActive($usuarioId, $matrixId, false),
            'entrar_matriz' => (function () use ($matrixService, $usuarioId, $matrixId): void {
                $matrixService->enterMatrix($usuarioId, $matrixId);
                (new CompanyContextService())->activate($usuarioId, $matrixId, 'acesso_plataforma');
                header('Location: ../index.php');
                exit;
            })(),
            default => throw new RuntimeException('Ação inválida.'),
        };

        redirectMatrices('success', 'Operação realizada com sucesso.');
    } catch (Throwable $e) {
        log_app_exception($e);
        redirectMatrices('danger', $e->getMessage());
    }
}

try {
    $matrices = $matrixService->listMatrices($usuarioId);
    $missingOwners = $matrixService->missingOwnerEmails();
} catch (Throwable $e) {
    log_app_exception($e);
    $matrices = [];
    $missingOwners = [];
    $_SESSION['matrizes_flash'] = [
        'type' => 'danger',
        'message' => $e->getMessage(),
    ];
}

$flash = $_SESSION['matrizes_flash'] ?? null;
unset($_SESSION['matrizes_flash']);

$csrfToken = Csrf::token();
$pageId = 'matrizes';
$pageTitle = 'Matrizes';
$activeMenu = 'mais';

require_once __DIR__ . '/layout/header.php';
?>

<style>
  .matrix-page { width: min(1180px, 100%); margin: 0 auto; padding: 18px 16px 118px; display: grid; gap: 16px; }
  .matrix-hero, .matrix-panel, .matrix-card { border: 1px solid rgba(15,23,42,.08); border-radius: 22px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.07); }
  .matrix-hero { padding: 18px; display: grid; gap: 6px; }
  .matrix-hero h1, .matrix-hero p, .matrix-card h2, .matrix-card p { margin: 0; }
  .matrix-hero h1 { font-size: 1.65rem; line-height: 1.1; }
  .matrix-hero p, .matrix-card p { color: #64748b; font-size: .86rem; font-weight: 750; line-height: 1.42; overflow-wrap: anywhere; }
  .matrix-alert { padding: 13px 15px; border-radius: 16px; font-weight: 850; }
  .matrix-alert.success { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
  .matrix-alert.danger { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
  .matrix-alert.warning { color: #92400e; background: #fef3c7; border: 1px solid #fde68a; }
  .matrix-panel { padding: 16px; display: grid; gap: 13px; }
  .matrix-panel h2 { margin: 0; font-size: 1.1rem; }
  .matrix-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .matrix-field { display: grid; gap: 6px; min-width: 0; }
  .matrix-field.full { grid-column: 1 / -1; }
  .matrix-field label { color: #334155; font-size: .82rem; font-weight: 850; }
  .matrix-field input { width: 100%; min-height: 45px; border: 1px solid #dbe3ef; border-radius: 14px; padding: 9px 11px; font-size: 16px; }
  .matrix-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
  .matrix-btn { min-height: 42px; border: 0; border-radius: 14px; padding: 0 14px; color: #fff; background: #1657a7; font-weight: 950; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
  .matrix-btn.secondary { color: #102033; background: #e5e7eb; }
  .matrix-btn.danger { background: #dc2626; }
  .matrix-btn.success { background: #16a34a; }
  .matrix-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .matrix-card { padding: 15px; display: grid; grid-template-columns: 60px minmax(0, 1fr); gap: 13px; }
  .matrix-logo { width: 58px; height: 58px; display: grid; place-items: center; overflow: hidden; border-radius: 18px; color: #1657a7; background: #eef6ff; font-weight: 950; }
  .matrix-logo img { width: 100%; height: 100%; object-fit: contain; padding: 7px; background: #fff; }
  .matrix-card details, .matrix-card .matrix-actions { grid-column: 1 / -1; }
  .matrix-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
  .matrix-badge { min-height: 24px; display: inline-flex; align-items: center; padding: 0 9px; border-radius: 999px; background: #eff6ff; color: #1657a7; font-size: .72rem; font-weight: 900; }
  .matrix-badge.success { background: #dcfce7; color: #16a34a; }
  .matrix-badge.danger { background: #fee2e2; color: #dc2626; }
  .matrix-badge.warning { background: #fef3c7; color: #92400e; }
  @media (max-width: 760px) { .matrix-grid, .matrix-list { grid-template-columns: 1fr; } .matrix-actions, .matrix-actions form, .matrix-btn { width: 100%; } }
</style>

<section class="matrix-page">
  <header class="matrix-hero">
    <p class="micro-label">Administração da plataforma</p>
    <h1>Matrizes</h1>
    <p>Cadastre empresas principais, defina o administrador principal e acesse matrizes ativas para prestar suporte.</p>
  </header>

  <?php if ($flash): ?>
    <div class="matrix-alert <?= e((string)$flash['type']) ?>" role="alert"><?= e((string)$flash['message']) ?></div>
  <?php endif; ?>

  <?php if ($missingOwners): ?>
    <div class="matrix-alert warning" role="status">
      Contas especiais ainda não existem em usuários: <?= e(implode(', ', $missingOwners)) ?>.
    </div>
  <?php endif; ?>

  <section class="matrix-panel" aria-labelledby="createMatrixTitle">
    <h2 id="createMatrixTitle">Criar matriz</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="acao" value="criar_matriz">
      <div class="matrix-grid">
        <div class="matrix-field"><label for="nome">Razão social *</label><input id="nome" name="nome" maxlength="180" required></div>
        <div class="matrix-field"><label for="nome_fantasia">Nome fantasia</label><input id="nome_fantasia" name="nome_fantasia" maxlength="180"></div>
        <div class="matrix-field"><label for="codigo">Código</label><input id="codigo" name="codigo" maxlength="50" pattern="[A-Za-z0-9_-]+"></div>
        <div class="matrix-field"><label for="cpf_cnpj">CPF/CNPJ</label><input id="cpf_cnpj" name="cpf_cnpj" maxlength="20"></div>
        <div class="matrix-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" maxlength="30"></div>
        <div class="matrix-field"><label for="logo">Logo</label><input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"></div>
        <div class="matrix-field full"><label for="endereco">Endereço</label><input id="endereco" name="endereco" maxlength="255"></div>
        <div class="matrix-field"><label for="admin_nome">Nome do administrador principal</label><input id="admin_nome" name="admin_nome" maxlength="140"></div>
        <div class="matrix-field"><label for="admin_email">E-mail do administrador principal *</label><input id="admin_email" name="admin_email" type="email" maxlength="180" required></div>
        <div class="matrix-field"><label for="admin_telefone">Telefone do administrador</label><input id="admin_telefone" name="admin_telefone" maxlength="30"></div>
        <div class="matrix-field"><label for="admin_senha">Senha inicial</label><input id="admin_senha" name="admin_senha" type="password" minlength="6" maxlength="72"></div>
        <div class="matrix-field"><label for="admin_senha_confirmacao">Confirmação da senha inicial</label><input id="admin_senha_confirmacao" name="admin_senha_confirmacao" type="password" minlength="6" maxlength="72"></div>
      </div>
      <div class="matrix-actions"><button class="matrix-btn" type="submit">Criar matriz</button></div>
    </form>
  </section>

  <section class="matrix-list" aria-label="Matrizes cadastradas">
    <?php foreach ($matrices as $matrix): ?>
      <?php
        $brand = $brandService->getForCompany((int)$matrix['id'], '../');
        $isActive = (int)$matrix['ativo'] === 1;
        $hasAdmin = !empty($matrix['admin_principal_usuario_id']);
      ?>
      <article class="matrix-card">
        <div class="matrix-logo" aria-hidden="true">
          <?php if ($brand['logo_url'] !== ''): ?><img src="<?= e((string)$brand['logo_url']) ?>" alt=""><?php else: ?><?= e((string)$brand['initials']) ?><?php endif; ?>
        </div>
        <div>
          <h2><?= e(matrixDisplayName($matrix)) ?></h2>
          <p><?= e((string)$matrix['nome']) ?></p>
          <?php if (!empty($matrix['codigo'])): ?><p>Código: <?= e((string)$matrix['codigo']) ?></p><?php endif; ?>
          <?php if (!empty($matrix['cpf_cnpj'])): ?><p>CPF/CNPJ: <?= e((string)$matrix['cpf_cnpj']) ?></p><?php endif; ?>
          <p>Administrador: <?= $hasAdmin ? e((string)$matrix['admin_nome']) . ' · ' . e((string)$matrix['admin_email']) : 'Pendente' ?></p>
          <p>Filiais: <?= (int)($matrix['filiais_total'] ?? 0) ?></p>
          <div class="matrix-badges">
            <span class="matrix-badge">Matriz</span>
            <span class="matrix-badge <?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Ativa' : 'Inativa' ?></span>
            <?php if (!$hasAdmin): ?><span class="matrix-badge warning">Admin pendente</span><?php endif; ?>
          </div>
        </div>

        <details class="matrix-panel">
          <summary>Editar matriz</summary>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="acao" value="editar_matriz">
            <input type="hidden" name="matriz_id" value="<?= (int)$matrix['id'] ?>">
            <div class="matrix-grid">
              <div class="matrix-field"><label>Razão social *</label><input name="nome" maxlength="180" required value="<?= e((string)$matrix['nome']) ?>"></div>
              <div class="matrix-field"><label>Nome fantasia</label><input name="nome_fantasia" maxlength="180" value="<?= e((string)($matrix['nome_fantasia'] ?? '')) ?>"></div>
              <div class="matrix-field"><label>Código</label><input name="codigo" maxlength="50" pattern="[A-Za-z0-9_-]+" value="<?= e((string)($matrix['codigo'] ?? '')) ?>"></div>
              <div class="matrix-field"><label>CPF/CNPJ</label><input name="cpf_cnpj" maxlength="20" value="<?= e((string)($matrix['cpf_cnpj'] ?? '')) ?>"></div>
              <div class="matrix-field"><label>Telefone</label><input name="telefone" maxlength="30" value="<?= e((string)($matrix['telefone'] ?? '')) ?>"></div>
              <div class="matrix-field"><label>Logo</label><input name="logo" type="file" accept="image/jpeg,image/png,image/webp"></div>
              <div class="matrix-field full"><label>Endereço</label><input name="endereco" maxlength="255" value="<?= e((string)($matrix['endereco'] ?? '')) ?>"></div>
              <div class="matrix-field"><label>Novo administrador principal</label><input name="admin_email" type="email" maxlength="180" placeholder="Preencha apenas se for trocar"></div>
              <div class="matrix-field"><label>Nome se for novo usuário</label><input name="admin_nome" maxlength="140"></div>
              <div class="matrix-field"><label>Senha inicial se for novo usuário</label><input name="admin_senha" type="password" minlength="6" maxlength="72"></div>
              <div class="matrix-field"><label>Confirmar senha</label><input name="admin_senha_confirmacao" type="password" minlength="6" maxlength="72"></div>
            </div>
            <div class="matrix-actions"><button class="matrix-btn secondary" type="submit">Salvar matriz</button></div>
          </form>
        </details>

        <div class="matrix-actions">
          <?php if ($isActive): ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="acao" value="entrar_matriz">
              <input type="hidden" name="matriz_id" value="<?= (int)$matrix['id'] ?>">
              <button class="matrix-btn" type="submit">Entrar</button>
            </form>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="acao" value="<?= $isActive ? 'inativar_matriz' : 'ativar_matriz' ?>">
            <input type="hidden" name="matriz_id" value="<?= (int)$matrix['id'] ?>">
            <button class="matrix-btn <?= $isActive ? 'danger' : 'success' ?>" type="submit" <?= $isActive ? "onclick=\"return confirm('Inativar esta matriz?')\"" : '' ?>><?= $isActive ? 'Inativar' : 'Ativar' ?></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
