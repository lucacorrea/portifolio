<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Security\Auth;
use App\Security\Csrf;
use App\Services\CompanyBrandService;
use App\Services\CompanyContextService;

Auth::requireLogin();

$user = Auth::user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$context = new CompanyContextService();
$companies = $context->availableCompanies((int)$user['id']);

if (!$companies) {
    Auth::logout();
    header('Location: login.php');
    exit;
}

$brandService = new CompanyBrandService();
$token = Csrf::token();
$flash = $_SESSION['company_selection_flash'] ?? null;
unset($_SESSION['company_selection_flash']);

function selectionDisplayName(array $company): string
{
    $fantasyName = trim((string)($company['nome_fantasia'] ?? ''));
    return $fantasyName !== '' ? $fantasyName : trim((string)($company['nome'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1657A7">
  <title>Selecionar empresa | Sistema de Gestão</title>
  <style>
    :root {
      --bg: #f5f7fb;
      --card: #fff;
      --text: #102033;
      --muted: #64748b;
      --line: rgba(15, 23, 42, .09);
      --primary: #1657a7;
      --success: #16a34a;
      --danger: #dc2626;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
      background: var(--bg);
    }

    .selection-page {
      width: min(960px, 100%);
      margin: 0 auto;
      padding: 24px 16px 44px;
      display: grid;
      gap: 18px;
    }

    .selection-hero {
      padding: 22px;
      color: #fff;
      background: linear-gradient(135deg, #111827, #1657a7);
      border-radius: 24px;
      box-shadow: 0 18px 38px rgba(22, 87, 167, .18);
    }

    .selection-hero p,
    .selection-hero h1 { margin: 0; }
    .selection-hero p {
      color: rgba(255,255,255,.78);
      font-size: .84rem;
      font-weight: 800;
      line-height: 1.45;
    }
    .selection-hero h1 {
      margin-top: 6px;
      font-size: clamp(1.6rem, 5vw, 2.4rem);
      line-height: 1.08;
      letter-spacing: 0;
    }

    .selection-alert {
      padding: 13px 15px;
      border: 1px solid #fecaca;
      color: #991b1b;
      background: #fee2e2;
      border-radius: 16px;
      font-weight: 800;
    }

    .company-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .company-card {
      display: grid;
      grid-template-columns: 64px minmax(0, 1fr);
      gap: 14px;
      align-items: center;
      padding: 16px;
      text-align: left;
      border: 1px solid var(--line);
      border-radius: 22px;
      background: var(--card);
      box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
    }

    .company-logo {
      width: 64px;
      height: 64px;
      display: grid;
      place-items: center;
      overflow: hidden;
      border-radius: 18px;
      background: #eef6ff;
      color: var(--primary);
      font-weight: 950;
      font-size: 1.2rem;
    }

    .company-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      padding: 7px;
      background: #fff;
    }

    .company-copy {
      min-width: 0;
      display: grid;
      gap: 5px;
    }

    .company-copy h2 {
      margin: 0;
      font-size: 1rem;
      line-height: 1.2;
      overflow-wrap: anywhere;
    }

    .company-copy p {
      margin: 0;
      color: var(--muted);
      font-size: .82rem;
      font-weight: 750;
      line-height: 1.35;
      overflow-wrap: anywhere;
    }

    .company-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 24px;
      padding: 0 9px;
      border-radius: 999px;
      background: #eff6ff;
      color: var(--primary);
      font-size: .72rem;
      font-weight: 900;
    }

    .badge.success {
      background: #dcfce7;
      color: var(--success);
    }

    .company-actions {
      grid-column: 1 / -1;
      display: flex;
      justify-content: flex-end;
    }

    .select-btn {
      min-height: 42px;
      padding: 0 16px;
      border: 0;
      border-radius: 14px;
      background: var(--primary);
      color: #fff;
      font-weight: 950;
      cursor: pointer;
    }

    .select-btn:focus-visible {
      outline: 4px solid rgba(22, 87, 167, .18);
      outline-offset: 3px;
    }

    @media (max-width: 760px) {
      .company-grid { grid-template-columns: 1fr; }
      .company-card { grid-template-columns: 54px minmax(0, 1fr); border-radius: 20px; }
      .company-logo { width: 54px; height: 54px; border-radius: 16px; }
    }
  </style>
</head>
<body>
  <main class="selection-page" aria-labelledby="selectionTitle">
    <header class="selection-hero">
      <p>Olá, <?= e((string)($user['nome'] ?? '')) ?></p>
      <h1 id="selectionTitle">Escolha a empresa para continuar</h1>
      <p>Somente empresas ativas vinculadas ao seu usuário aparecem aqui.</p>
    </header>

    <?php if ($flash): ?>
      <div class="selection-alert" role="alert"><?= e((string)$flash) ?></div>
    <?php endif; ?>

    <section class="company-grid" aria-label="Empresas disponíveis">
      <?php foreach ($companies as $company): ?>
        <?php
          $brand = $brandService->getForCompany((int)$company['empresa_id']);
          $displayName = selectionDisplayName($company);
          $type = (string)($company['tipo'] ?? 'matriz');
        ?>
        <form class="company-card" method="post" action="api/lojas/selecionar.php">
          <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
          <input type="hidden" name="empresa_id" value="<?= (int)$company['empresa_id'] ?>">

          <div class="company-logo" aria-hidden="true">
            <?php if (!empty($brand['logo_path'])): ?>
              <img src="<?= e((string)$brand['logo_path']) ?>" alt="">
            <?php else: ?>
              <?= e((string)$brand['initials']) ?>
            <?php endif; ?>
          </div>

          <div class="company-copy">
            <h2><?= e($displayName) ?></h2>
            <p><?= e((string)$company['nome']) ?></p>
            <?php if (!empty($company['codigo'])): ?>
              <p>Código: <?= e((string)$company['codigo']) ?></p>
            <?php endif; ?>
            <div class="company-meta">
              <span class="badge"><?= e($type === 'loja' ? 'Loja' : 'Matriz') ?></span>
              <span class="badge success">Ativa</span>
              <span class="badge"><?= e((string)$company['nivel']) ?></span>
            </div>
          </div>

          <div class="company-actions">
            <button class="select-btn" type="submit">Entrar</button>
          </div>
        </form>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
