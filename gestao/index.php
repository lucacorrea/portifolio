<?php

declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';

use App\Repositories\CompanyRepository;
use App\Security\Auth;
use App\Services\DashboardService;

Auth::requireLogin();
$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$dashboardService = new DashboardService();
$companyRepository = new CompanyRepository();
function dashboardMoney(mixed $value): string
{
  return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
function dashboardNumber(mixed $value): string
{
  return number_format((float)$value, 0, ',', '.');
}
function dashboardDate(mixed $value): string
{
  $value = trim((string)$value);
  if ($value === '') {
    return 'Sem data';
  }
  $timestamp = strtotime($value);
  return $timestamp ? date('d/m/Y', $timestamp) : 'Sem data';
}
function dashboardDateTime(mixed $value): string
{
  $value = trim((string)$value);
  if ($value === '') {
    return 'Sem data';
  }
  $timestamp = strtotime($value);
  return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sem data';
}
function dashboardSaleStatusLabel(string $status): string
{
  return ['finalizada' => 'Finalizada', 'cancelada' => 'Cancelada', 'pendente' => 'Pendente',][$status] ?? ucfirst(str_replace('_', ' ', $status));
}
function dashboardSaleStatusClass(string $status): string
{
  return match ($status) {
    'finalizada' => 'success',
    'cancelada' => 'danger',
    default => 'warning',
  };
}
function dashboardPaymentMethodLabel(string $method): string
{
  $method = trim($method);
  return ['pix' => 'PIX', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito', 'transferencia' => 'Transferência', 'conta_cliente' => 'Conta cliente / Fiado', 'misto' => 'Misto', 'outro' => 'Outro',][$method] ?? ucfirst(str_replace('_', ' ', $method));
}
function dashboardSafePercent(float $value, float $base): float
{
  if ($base <= 0) {
    return 0.0;
  }
  return round(($value / $base) * 100, 1);
}
/** * Retorna as iniciais calculadas a partir do nome * cadastrado no banco de dados. */ function dashboardCompanyInitials(string $name): string
{
  $name = trim($name);
  if ($name === '') {
    return '';
  }
  $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
  if (!$parts) {
    return '';
  }
  $firstPart = (string)$parts[0];
  $firstInitial = function_exists('mb_substr') ? mb_substr($firstPart, 0, 1, 'UTF-8') : substr($firstPart, 0, 1);
  $lastInitial = '';
  if (count($parts) > 1) {
    $lastPart = (string)$parts[count($parts) - 1];
    $lastInitial = function_exists('mb_substr') ? mb_substr($lastPart, 0, 1, 'UTF-8') : substr($lastPart, 0, 1);
  }
  $initials = $firstInitial . $lastInitial;
  return function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);
}
/** * Valida o caminho salvo no banco e retorna * a URL relativa da imagem. * * Não utiliza imagem fixa como fallback. */ function dashboardCompanyLogoUrl(int $empresaId, mixed $storedLogo): string
{
  if ($empresaId <= 0) {
    return '';
  }
  $storedLogo = trim((string)$storedLogo);
  if ($storedLogo === '') {
    return '';
  }
  $relativePath = ltrim(str_replace('\\', '/', $storedLogo), '/');
  $companyDirectory = sprintf('uploads/empresas/%d/', $empresaId);
  if (!str_starts_with($relativePath, $companyDirectory) || str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
    return '';
  }
  $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
  if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    return '';
  }
  $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
  if (!is_file($absolutePath)) {
    return '';
  }
  return $relativePath;
} /* |-------------------------------------------------------------------------- | Identidade visual da empresa |-------------------------------------------------------------------------- | | A logo e o nome são buscados diretamente da tabela empresas. | */
$company = [];
$companyDisplayName = '';
$companyLogoUrl = '';
$companyInitials = '';
$companyLoadError = null;
if ($empresaId > 0) {
  try {
    $company = $companyRepository->findById($empresaId) ?? [];
    $companyFantasyName = trim((string)($company['nome_fantasia'] ?? ''));
    $companyLegalName = trim((string)($company['nome'] ?? ''));
    $companyDisplayName = $companyFantasyName !== '' ? $companyFantasyName : $companyLegalName;
    $companyLogoUrl = dashboardCompanyLogoUrl($empresaId, $company['logo'] ?? null);
    $companyInitials = dashboardCompanyInitials($companyDisplayName);
  } catch (Throwable $e) {
    log_app_exception($e);
    $companyLoadError = 'Não foi possível carregar a identidade visual da empresa.';
  }
}
$dashboard = ['today' => ['sales_count' => 0, 'total_sales' => 0, 'estimated_profit' => 0,], 'month' => ['sales_count' => 0, 'total_sales' => 0, 'estimated_profit' => 0,], 'clientAccounts' => ['total_open' => 0, 'total_overdue' => 0, 'open_count' => 0, 'overdue_count' => 0, 'clients_with_debt' => 0,], 'paymentMethods' => [], 'salesEvolution' => [], 'latestSales' => [], 'topProducts' => [], 'lowStock' => [], 'expiringProducts' => [], 'expiredProducts' => [], 'settings' => ['alertDays' => 7,],];
$loadError = null;
try {
  $dashboard = array_replace_recursive($dashboard, $dashboardService->summary($empresaId));
} catch (Throwable $e) {
  log_app_exception($e);
  $loadError = 'Não foi possível carregar todos os indicadores do dashboard agora.';
}
$today = $dashboard['today'];
$month = $dashboard['month'];
$clientAccounts = $dashboard['clientAccounts'];
$paymentMethods = $dashboard['paymentMethods'];
$salesEvolution = $dashboard['salesEvolution'];
$latestSales = $dashboard['latestSales'];
$topProducts = $dashboard['topProducts'];
$lowStock = $dashboard['lowStock'];
$expiringProducts = $dashboard['expiringProducts'];
$expiredProducts = $dashboard['expiredProducts'];
$totalVendidoHoje = (float)($today['total_sales'] ?? 0);
$qtdVendasHoje = (int)($today['sales_count'] ?? 0);
$lucroHoje = (float)($today['estimated_profit'] ?? 0);
$ticketMedioHoje = $qtdVendasHoje > 0 ? $totalVendidoHoje / $qtdVendasHoje : 0;
$totalVendidoMes = (float)($month['total_sales'] ?? 0);
$qtdVendasMes = (int)($month['sales_count'] ?? 0);
$lucroMes = (float)($month['estimated_profit'] ?? 0);
$totalContasAbertas = (float)($clientAccounts['total_open'] ?? 0);
$totalContasVencidas = (float)($clientAccounts['total_overdue'] ?? 0);
$qtdContasAbertas = (int)($clientAccounts['open_count'] ?? 0);
$qtdContasVencidas = (int)($clientAccounts['overdue_count'] ?? 0);
$qtdClientesComDivida = (int)($clientAccounts['clients_with_debt'] ?? 0);
$evolutionMap = [];
foreach ($salesEvolution as $row) {
  $date = (string)($row['sale_date'] ?? '');
  if ($date !== '') {
    $evolutionMap[$date] = ['sale_date' => $date, 'sales_count' => (int)($row['sales_count'] ?? 0), 'total_sales' => (float)($row['total_sales'] ?? 0),];
  }
}
$chartDays = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-{$i} days"));
  $chartDays[] = $evolutionMap[$date] ?? ['sale_date' => $date, 'sales_count' => 0, 'total_sales' => 0,];
}
$maxEvolutionValue = 0.0;
foreach ($chartDays as $row) {
  $maxEvolutionValue = max($maxEvolutionValue, (float)($row['total_sales'] ?? 0));
}
$totalPaymentMethods = 0.0;
foreach ($paymentMethods as $payment) {
  $totalPaymentMethods += (float)($payment['total_value'] ?? 0);
}
$pageId = 'dashboard';
$pageTitle = 'Dashboard';
$activeMenu = 'home';
$prefix = '';
require_once __DIR__ . '/pages/layout/header.php'; ?> <style>
  .dashboard-page {
    display: grid;
    gap: 18px;
  }

  .dashboard-alert {
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(230, 83, 103, .25);
    color: var(--red);
    background: rgba(230, 83, 103, .10);
    font-size: 13px;
    font-weight: 800;
  }

  .dashboard-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .dashboard-hero-meta span {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 11px;
    border-radius: 999px;
    color: rgba(255, 255, 255, .95);
    background: rgba(255, 255, 255, .16);
    font-size: 11px;
    font-weight: 850;
  }

  /* |-------------------------------------------------------------------------- | Logo dinâmica da empresa |-------------------------------------------------------------------------- */
  .avatar-btn {
    overflow: hidden;
    text-decoration: none;
  }

  .avatar-btn img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
  }

  .company-avatar-initials {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: var(--blue);
    background: #ffffff;
    font-size: 15px;
    line-height: 1;
    font-weight: 950;
    letter-spacing: .02em;
    text-transform: uppercase;
  }

  .dashboard-section {
    display: grid;
    gap: 12px;
  }

  .dashboard-section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
  }

  .dashboard-section-header h2 {
    margin: 0;
    color: var(--ink);
    font-size: 22px;
    line-height: 1.1;
  }

  .dashboard-section-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .dashboard-section-header .small-link {
    flex: 0 0 auto;
  }

  .dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .dashboard-kpi-card {
    position: relative;
    min-height: 128px;
    padding: 17px;
    border-radius: 22px;
    border: 1px solid var(--line);
    background: #fff;
    box-shadow: 0 10px 28px rgba(29, 55, 95, .06);
    overflow: hidden;
  }

  .dashboard-kpi-card::after {
    content: "";
    position: absolute;
    right: -28px;
    top: -28px;
    width: 86px;
    height: 86px;
    border-radius: 999px;
    background: rgba(22, 87, 167, .08);
  }

  .dashboard-kpi-card.success::after {
    background: rgba(37, 196, 132, .11);
  }

  .dashboard-kpi-card.warning::after {
    background: rgba(245, 158, 11, .12);
  }

  .dashboard-kpi-card.danger::after {
    background: rgba(230, 83, 103, .12);
  }

  .dashboard-kpi-label {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .dashboard-kpi-value {
    display: block;
    margin-top: 8px;
    color: var(--ink);
    font-size: 25px;
    line-height: 1.08;
    font-weight: 950;
  }

  .dashboard-kpi-meta {
    display: block;
    margin-top: 8px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.35;
    font-weight: 750;
  }

  .dashboard-panel-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
    gap: 14px;
    align-items: start;
  }

  .dashboard-card {
    border-radius: 24px;
    border: 1px solid var(--line);
    background: #fff;
    box-shadow: 0 10px 28px rgba(29, 55, 95, .055);
    overflow: hidden;
  }

  .dashboard-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 17px 18px;
    border-bottom: 1px solid var(--line);
  }

  .dashboard-card-header h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
  }

  .dashboard-card-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .dashboard-card-body {
    padding: 16px 18px 18px;
  }

  .dashboard-shortcuts {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .dashboard-shortcut {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 72px;
    padding: 14px;
    border-radius: 21px;
    border: 1px solid var(--line);
    color: var(--ink);
    background: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 8px 24px rgba(29, 55, 95, .045);
  }

  .dashboard-shortcut.primary {
    color: #fff;
    background: var(--blue);
    border-color: var(--blue);
  }

  .dashboard-shortcut .shortcut-icon {
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: #eff6ff;
    color: var(--blue);
  }

  .dashboard-shortcut.primary .shortcut-icon {
    background: rgba(255, 255, 255, .18);
    color: #fff;
  }

  .dashboard-shortcut svg {
    width: 21px;
    height: 21px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.9;
  }

  .dashboard-mini-chart {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 9px;
    align-items: end;
    min-height: 170px;
  }

  .dashboard-bar {
    display: grid;
    align-items: end;
    gap: 8px;
    min-width: 0;
  }

  .dashboard-bar-track {
    height: 112px;
    display: flex;
    align-items: end;
    justify-content: center;
    border-radius: 999px;
    background: #f1f6fd;
    overflow: hidden;
  }

  .dashboard-bar-fill {
    width: 100%;
    min-height: 5px;
    border-radius: 999px 999px 0 0;
    background: linear-gradient(180deg, var(--blue), #0f4a8a);
  }

  .dashboard-bar small {
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-align: center;
    white-space: nowrap;
  }

  .dashboard-data-list {
    display: grid;
  }

  .dashboard-data-row {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr) auto;
    gap: 13px;
    align-items: center;
    padding: 15px 18px;
    border-bottom: 1px solid var(--line);
  }

  .dashboard-data-row:last-child {
    border-bottom: 0;
  }

  .dashboard-data-row.clickable {
    cursor: pointer;
  }

  .dashboard-data-row.clickable:hover {
    background: #f8fbff;
  }

  .dashboard-row-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    color: var(--blue);
    background: #eff6ff;
  }

  .dashboard-row-icon.warning {
    color: #b45309;
    background: rgba(245, 158, 11, .12);
  }

  .dashboard-row-icon.danger {
    color: var(--red);
    background: rgba(230, 83, 103, .10);
  }

  .dashboard-row-icon.success {
    color: var(--green);
    background: rgba(37, 196, 132, .10);
  }

  .dashboard-row-icon svg {
    width: 21px;
    height: 21px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.9;
  }

  .dashboard-row-main {
    min-width: 0;
  }

  .dashboard-row-main h4 {
    margin: 0;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.25;
    font-weight: 950;
  }

  .dashboard-row-main p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.35;
    font-weight: 750;
  }

  .dashboard-row-value {
    text-align: right;
    white-space: nowrap;
  }

  .dashboard-row-value strong {
    display: block;
    color: var(--ink);
    font-size: 14px;
    font-weight: 950;
  }

  .dashboard-status {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    margin-top: 6px;
    padding: 0 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
  }

  .dashboard-status.success {
    color: var(--green);
    background: rgba(37, 196, 132, .10);
  }

  .dashboard-status.warning {
    color: #b45309;
    background: rgba(245, 158, 11, .12);
  }

  .dashboard-status.danger {
    color: var(--red);
    background: rgba(230, 83, 103, .10);
  }

  .dashboard-empty {
    padding: 20px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 800;
    text-align: center;
  }

  .dashboard-finance-list {
    display: grid;
    gap: 10px;
  }

  .dashboard-finance-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 14px;
    border-radius: 18px;
    border: 1px solid var(--line);
    background: #f8fbff;
  }

  .dashboard-finance-item span {
    display: block;
    color: var(--muted);
    font-size: 12px;
    font-weight: 850;
  }

  .dashboard-finance-item strong {
    display: block;
    margin-top: 4px;
    color: var(--ink);
    font-size: 17px;
    font-weight: 950;
  }

  .dashboard-finance-item .danger {
    color: var(--red);
  }

  .dashboard-finance-item .success {
    color: var(--green);
  }

  .dashboard-payment-list {
    display: grid;
    gap: 10px;
  }

  .dashboard-payment-item {
    display: grid;
    gap: 8px;
    padding: 14px;
    border-radius: 18px;
    border: 1px solid var(--line);
    background: #fff;
  }

  .dashboard-payment-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .dashboard-payment-top strong {
    color: var(--ink);
    font-size: 14px;
    font-weight: 950;
  }

  .dashboard-payment-top span {
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
  }

  .dashboard-progress {
    height: 9px;
    border-radius: 999px;
    background: #eff4fb;
    overflow: hidden;
  }

  .dashboard-progress div {
    height: 100%;
    min-width: 4px;
    border-radius: 999px;
    background: var(--blue);
  }

  .dashboard-payment-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
  }

  .dashboard-payment-bottom b {
    color: var(--ink);
  }

  .dashboard-grid-bottom {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  @media (max-width: 1120px) {
    .dashboard-kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-panel-grid,
    .dashboard-grid-bottom {
      grid-template-columns: 1fr;
    }

    .dashboard-shortcuts {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 620px) {
    .dashboard-page {
      gap: 14px;
    }

    .dashboard-section-header {
      align-items: flex-start;
      flex-direction: column;
    }

    .dashboard-section-header h2 {
      font-size: 20px;
    }

    .dashboard-kpi-grid,
    .dashboard-shortcuts {
      grid-template-columns: 1fr;
    }

    .dashboard-kpi-card {
      min-height: auto;
      padding: 16px;
      border-radius: 20px;
    }

    .dashboard-kpi-value {
      font-size: 23px;
    }

    .dashboard-card {
      border-radius: 22px;
    }

    .dashboard-card-header {
      padding: 15px;
    }

    .dashboard-card-body {
      padding: 15px;
    }

    .dashboard-data-row {
      grid-template-columns: 44px minmax(0, 1fr);
      padding: 14px 15px;
      align-items: flex-start;
    }

    .dashboard-row-icon {
      width: 40px;
      height: 40px;
      border-radius: 14px;
    }

    .dashboard-row-value {
      grid-column: 2;
      text-align: left;
      white-space: normal;
    }

    .dashboard-mini-chart {
      gap: 6px;
      min-height: 140px;
    }

    .dashboard-bar-track {
      height: 88px;
    }

    .dashboard-finance-item,
    .dashboard-payment-top,
    .dashboard-payment-bottom {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>
<header class="blue-area">
  <div class="balance-row">
    <div>
      <p class="micro-label">Vendido hoje</p>
      <h1> <?= e(dashboardMoney($totalVendidoHoje)) ?> </h1> <span class="subtle-light"> <?= e((string)($user['nome'] ?? $user['usuario'] ?? 'Usuário')) ?> • <?= (int)$qtdVendasHoje ?> venda<?= $qtdVendasHoje === 1 ? '' : 's' ?> </span>
      <div class="dashboard-hero-meta"> <span> Ticket <?= e(dashboardMoney($ticketMedioHoje)) ?> </span> <span> Lucro <?= e(dashboardMoney($lucroHoje)) ?> </span> <span> Mês <?= e(dashboardMoney($totalVendidoMes)) ?> </span> </div>
    </div> <a class="avatar-btn" href="pages/configuracoes.php" aria-label="<?= e($companyDisplayName !== '' ? 'Configurações de ' . $companyDisplayName : 'Configurações da empresa') ?>" title="<?= e($companyDisplayName) ?>"> <?php if ($companyLogoUrl !== ''): ?> <img src="<?= e($companyLogoUrl) ?>" alt="<?= e($companyDisplayName !== '' ? 'Logo de ' . $companyDisplayName : 'Logo da empresa') ?>" loading="eager" decoding="async"> <?php elseif ($companyInitials !== ''): ?> <span class="company-avatar-initials" aria-hidden="true"> <?= e($companyInitials) ?> </span> <?php endif; ?> </a>
  </div>
  <nav class="quick-menu" aria-label="Ações rápidas"> <a href="pages/nova-venda.php"> <i data-icon="receipt"></i> <span>Venda</span> </a> <a href="pages/produtos.php"> <i data-icon="product"></i> <span>Produtos</span> </a> <a href="pages/clientes.php"> <i data-icon="user"></i> <span>Clientes</span> </a> <a href="pages/contas-clientes.php"> <i data-icon="box"></i> <span>Fiado</span> </a> <a href="pages/relatorios.php"> <i data-icon="report"></i> <span>Relatórios</span> </a> </nav>
</header>
<section class="white-sheet">
  <div class="dashboard-page"> <?php if ($companyLoadError !== null): ?> <div class="dashboard-alert" role="alert"> <?= e($companyLoadError) ?> </div> <?php endif; ?> <?php if ($loadError !== null): ?> <div class="dashboard-alert" role="alert"> <?= e($loadError) ?> </div> <?php endif; ?> <section class="dashboard-section">
      <div class="dashboard-section-header">
        <div>
          <h2>Resumo geral</h2>
          <p> Indicadores reais do caixa, estoque e fiado </p>
        </div>
      </div>
      <div class="dashboard-kpi-grid">
        <article class="dashboard-kpi-card success"> <span class="dashboard-kpi-label"> Vendas hoje </span> <strong class="dashboard-kpi-value"> <?= e(dashboardMoney($totalVendidoHoje)) ?> </strong> <small class="dashboard-kpi-meta"> <?= (int)$qtdVendasHoje ?> venda<?= $qtdVendasHoje === 1 ? '' : 's' ?> · Ticket <?= e(dashboardMoney($ticketMedioHoje)) ?> </small> </article>
        <article class="dashboard-kpi-card"> <span class="dashboard-kpi-label"> Lucro estimado hoje </span> <strong class="dashboard-kpi-value"> <?= e(dashboardMoney($lucroHoje)) ?> </strong> <small class="dashboard-kpi-meta"> <?= e((string)dashboardSafePercent($lucroHoje, $totalVendidoHoje)) ?>% sobre o total vendido </small> </article>
        <article class="dashboard-kpi-card"> <span class="dashboard-kpi-label"> Vendas do mês </span> <strong class="dashboard-kpi-value"> <?= e(dashboardMoney($totalVendidoMes)) ?> </strong> <small class="dashboard-kpi-meta"> <?= (int)$qtdVendasMes ?> venda<?= $qtdVendasMes === 1 ? '' : 's' ?> · Lucro <?= e(dashboardMoney($lucroMes)) ?> </small> </article>
        <article class="dashboard-kpi-card <?= $totalContasVencidas > 0 ? 'danger' : 'warning' ?>"> <span class="dashboard-kpi-label"> Fiado em aberto </span> <strong class="dashboard-kpi-value"> <?= e(dashboardMoney($totalContasAbertas)) ?> </strong> <small class="dashboard-kpi-meta"> <?= (int)$qtdContasAbertas ?> conta<?= $qtdContasAbertas === 1 ? '' : 's' ?> · <?= (int)$qtdClientesComDivida ?> cliente<?= $qtdClientesComDivida === 1 ? '' : 's' ?> </small> </article>
      </div>
    </section>
    <section class="dashboard-section">
      <div class="dashboard-shortcuts"> <a class="dashboard-shortcut primary" href="pages/nova-venda.php"> <span class="shortcut-icon"> <svg viewBox="0 0 24 24">
              <path d="M6 5h12v14H6z" />
              <path d="M9 9h6" />
              <path d="M9 13h4" />
            </svg> </span> Nova venda </a> <a class="dashboard-shortcut" href="pages/produtos.php"> <span class="shortcut-icon"> <svg viewBox="0 0 24 24">
              <path d="M5 7h14v12H5z" />
              <path d="M8 7a4 4 0 0 1 8 0" />
            </svg> </span> Produtos </a> <a class="dashboard-shortcut" href="pages/clientes.php"> <span class="shortcut-icon"> <svg viewBox="0 0 24 24">
              <path d="M16 11a4 4 0 1 0-8 0" />
              <path d="M4 20a8 8 0 0 1 16 0" />
            </svg> </span> Clientes </a> <a class="dashboard-shortcut" href="pages/contas-clientes.php"> <span class="shortcut-icon"> <svg viewBox="0 0 24 24">
              <path d="M6 7h12v10H6z" />
              <path d="M8 11h8" />
              <path d="M8 14h5" />
            </svg> </span> Contas / Fiado </a> </div>
    </section>
    <section class="dashboard-panel-grid">
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Últimos 7 dias</h3>
            <p> Evolução diária das vendas finalizadas </p>
          </div>
        </div>
        <div class="dashboard-card-body">
          <div class="dashboard-mini-chart" aria-label="Evolução de vendas dos últimos dias"> <?php foreach ($chartDays as $row): ?> <?php $date = (string)($row['sale_date'] ?? '');
                                                                                                                                      $value = (float)($row['total_sales'] ?? 0);
                                                                                                                                      $height = $maxEvolutionValue > 0 ? max(5, (int)round(($value / $maxEvolutionValue) * 112)) : 5; ?> <div class="dashboard-bar" title="<?= e(dashboardDate($date) . ' · ' . dashboardMoney($value)) ?>">
                <div class="dashboard-bar-track">
                  <div class="dashboard-bar-fill" style="height: <?= (int)$height ?>px"></div>
                </div> <small> <?= e($date !== '' ? date('d/m', strtotime($date)) : '--') ?> </small>
              </div> <?php endforeach; ?> </div>
        </div>
      </div>
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Alertas financeiros</h3>
            <p>Contas a receber e vencimentos</p>
          </div> <a class="small-link" href="pages/contas-clientes.php"> Ver </a>
        </div>
        <div class="dashboard-card-body">
          <div class="dashboard-finance-list">
            <div class="dashboard-finance-item"> <span>Total em aberto</span> <strong> <?= e(dashboardMoney($totalContasAbertas)) ?> </strong> </div>
            <div class="dashboard-finance-item"> <span>Contas vencidas</span> <strong class="<?= $totalContasVencidas > 0 ? 'danger' : 'success' ?>"> <?= e(dashboardMoney($totalContasVencidas)) ?> </strong> </div>
            <div class="dashboard-finance-item"> <span>Clientes com dívida</span> <strong> <?= (int)$qtdClientesComDivida ?> </strong> </div>
          </div>
        </div>
      </div>
    </section>
    <section class="dashboard-panel-grid">
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Últimas vendas</h3>
            <p>Movimentações recentes do caixa</p>
          </div> <a class="small-link" href="pages/relatorios.php"> Ver tudo </a>
        </div>
        <div class="dashboard-data-list"> <?php if (empty($latestSales)): ?> <p class="dashboard-empty"> Nenhuma venda registrada. </p> <?php else: ?> <?php foreach ($latestSales as $sale): ?> <?php $saleStatus = (string)($sale['status'] ?? ''); ?> <div class="dashboard-data-row clickable" onclick="location.href='pages/venda-detalhes.php?id=<?= (int)$sale['id'] ?>'">
                <div class="dashboard-row-icon"> <svg viewBox="0 0 24 24">
                    <path d="M7 4h10v16l-2-1-2 1-2-1-2 1-2-1z" />
                    <path d="M9 8h6" />
                    <path d="M9 12h5" />
                  </svg> </div>
                <div class="dashboard-row-main">
                  <h4> Venda # <?= e(str_pad((string)($sale['numero_venda'] ?? $sale['id']), 4, '0', STR_PAD_LEFT)) ?> </h4>
                  <p> <?= e((string)($sale['vendedor'] ?? 'Operador')) ?> · <?= e(dashboardDateTime($sale['criado_em'] ?? '')) ?> </p> <span class="dashboard-status <?= e(dashboardSaleStatusClass($saleStatus)) ?>"> <?= e(dashboardSaleStatusLabel($saleStatus)) ?> </span>
                </div>
                <div class="dashboard-row-value"> <strong> <?= e(dashboardMoney($sale['total'] ?? 0)) ?> </strong> </div>
              </div> <?php endforeach; ?> <?php endif; ?> </div>
      </div>
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Formas de pagamento hoje</h3>
            <p>Composição dos recebimentos</p>
          </div>
        </div>
        <div class="dashboard-card-body"> <?php if (empty($paymentMethods)): ?> <p class="dashboard-empty"> Nenhum pagamento registrado hoje. </p> <?php else: ?> <div class="dashboard-payment-list"> <?php foreach ($paymentMethods as $payment): ?> <?php $methodValue = (float)($payment['total_value'] ?? 0);
                                                                                                                                                                                                                                                        $percent = dashboardSafePercent($methodValue, $totalPaymentMethods); ?> <article class="dashboard-payment-item">
                  <div class="dashboard-payment-top"> <strong> <?= e(dashboardPaymentMethodLabel((string)($payment['metodo'] ?? ''))) ?> </strong> <span> <?= (int)($payment['total_count'] ?? 0) ?> lançamento<?= (int)($payment['total_count'] ?? 0) === 1 ? '' : 's' ?> </span> </div>
                  <div class="dashboard-progress" aria-hidden="true">
                    <div style="width: <?= max(4, min(100, (float)$percent)) ?>%"></div>
                  </div>
                  <div class="dashboard-payment-bottom"> <span> <?= e((string)$percent) ?>% do total </span> <b> <?= e(dashboardMoney($methodValue)) ?> </b> </div>
                </article> <?php endforeach; ?> </div> <?php endif; ?> </div>
      </div>
    </section>
    <section class="dashboard-grid-bottom">
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Estoque baixo</h3>
            <p>Produtos no mínimo ou abaixo</p>
          </div> <a class="small-link" href="pages/produtos.php?filtro=estoque_baixo"> Ver </a>
        </div>
        <div class="dashboard-data-list"> <?php if (empty($lowStock)): ?> <p class="dashboard-empty"> Nenhum produto com estoque baixo. </p> <?php else: ?> <?php foreach ($lowStock as $product): ?> <div class="dashboard-data-row clickable" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
                <div class="dashboard-row-icon warning"> <svg viewBox="0 0 24 24">
                    <path d="M5 7h14v12H5z" />
                    <path d="M8 7a4 4 0 0 1 8 0" />
                  </svg> </div>
                <div class="dashboard-row-main">
                  <h4> <?= e((string)$product['nome']) ?> </h4>
                  <p> Mínimo <?= e(dashboardNumber($product['estoque_minimo'] ?? 0)) ?> · Atual <?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> </p>
                </div>
                <div class="dashboard-row-value"> <strong> <?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> un. </strong> </div>
              </div> <?php endforeach; ?> <?php endif; ?> </div>
      </div>
      <div class="dashboard-card">
        <div class="dashboard-card-header">
          <div>
            <h3>Validade</h3>
            <p> Produtos vencidos e próximos do vencimento </p>
          </div> <a class="small-link" href="pages/produtos.php?filtro=perto_validade"> Ver </a>
        </div>
        <div class="dashboard-data-list"> <?php if (empty($expiredProducts) && empty($expiringProducts)): ?> <p class="dashboard-empty"> Nenhum alerta de validade. </p> <?php else: ?> <?php foreach ($expiredProducts as $product): ?> <div class="dashboard-data-row clickable" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
                <div class="dashboard-row-icon danger"> <svg viewBox="0 0 24 24">
                    <path d="M12 3l9 18H3z" />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                  </svg> </div>
                <div class="dashboard-row-main">
                  <h4> <?= e((string)$product['nome']) ?> </h4>
                  <p> Lote <?= e((string)($product['lote'] ?? '-')) ?> · Venceu em <?= e(dashboardDate($product['validade'] ?? '')) ?> </p> <span class="dashboard-status danger"> Vencido </span>
                </div>
                <div class="dashboard-row-value"> <strong> <?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> un. </strong> </div>
              </div> <?php endforeach; ?> <?php foreach ($expiringProducts as $product): ?> <div class="dashboard-data-row clickable" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
                <div class="dashboard-row-icon warning"> <svg viewBox="0 0 24 24">
                    <path d="M5 7h14v12H5z" />
                    <path d="M8 7a4 4 0 0 1 8 0" />
                  </svg> </div>
                <div class="dashboard-row-main">
                  <h4> <?= e((string)$product['nome']) ?> </h4>
                  <p> Lote <?= e((string)($product['lote'] ?? '-')) ?> · Validade <?= e(dashboardDate($product['validade'] ?? '')) ?> </p> <span class="dashboard-status warning"> Próximo vencimento </span>
                </div>
                <div class="dashboard-row-value"> <strong> <?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> un. </strong> </div>
              </div> <?php endforeach; ?> <?php endif; ?> </div>
      </div>
    </section>
    <section class="dashboard-section">
      <div class="dashboard-section-header">
        <div>
          <h2>Mais vendidos hoje</h2>
          <p> Produtos com maior saída no caixa </p>
        </div>
      </div>
      <div class="dashboard-card">
        <div class="dashboard-data-list"> <?php if (empty($topProducts)): ?> <p class="dashboard-empty"> Nenhum produto vendido hoje. </p> <?php else: ?> <?php foreach ($topProducts as $product): ?> <div class="dashboard-data-row clickable" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
                <div class="dashboard-row-icon success"> <svg viewBox="0 0 24 24">
                    <path d="M5 7h14v12H5z" />
                    <path d="M8 7a4 4 0 0 1 8 0" />
                  </svg> </div>
                <div class="dashboard-row-main">
                  <h4> <?= e((string)$product['nome']) ?> </h4>
                  <p> Faturamento <?= e(dashboardMoney($product['total_faturado'] ?? 0)) ?> </p>
                </div>
                <div class="dashboard-row-value"> <strong> <?= e(dashboardNumber($product['total_vendido'] ?? 0)) ?> un. </strong> </div>
              </div> <?php endforeach; ?> <?php endif; ?> </div>
      </div>
    </section>
  </div>
</section> <?php require_once __DIR__ . '/pages/layout/footer.php'; ?>