<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\SettingsRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\SaleService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentUserId = (int)($user['id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');

function canSaleDetailAccess(string $action, string $nivel): bool
{
    $permissions = [
        'view' => ['admin', 'gerente', 'operador', 'leitor'],
        'print' => ['admin', 'gerente', 'operador', 'leitor'],
        'cancel' => ['admin', 'gerente'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireSaleDetailAccess(string $action, string $nivel): void
{
    if (!canSaleDetailAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para acessar esta venda.');
    }
}

function saleDetailMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function saleDetailNumber(mixed $value): string
{
    $formatted = number_format((float)$value, 3, ',', '.');

    return rtrim(rtrim($formatted, '0'), ',');
}

function saleDetailPaymentLabel(string $method): string
{
    return [
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'credito' => 'Cartão de crédito',
        'debito' => 'Cartão de débito',
        'conta_cliente' => 'Conta do cliente',
        'misto' => 'Pagamento misto',
    ][$method] ?? 'Não informado';
}

function saleDetailStatusLabel(string $status): string
{
    return [
        'finalizada' => 'Finalizada',
        'pendente' => 'Pendente',
        'cancelada' => 'Cancelada',
        'em_aberto' => 'Em aberto',
    ][$status] ?? ucfirst($status);
}

function saleDetailDateTime(mixed $value): string
{
    if ($value === null || trim((string)$value) === '') {
        return 'Não informado';
    }

    return date('d/m/Y H:i', strtotime((string)$value));
}

$vendaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($vendaId === false || $vendaId === null) {
    header('Location: relatorios.php');
    exit;
}

$saleService = new SaleService();
$settingsRepository = new SettingsRepository();
$formError = null;
$sale = null;
$items = [];
$company = [];
$flash = $_SESSION['sale_detail_flash'] ?? null;
unset($_SESSION['sale_detail_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireSaleDetailAccess('cancel', $currentNivel);

        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action !== 'cancel_sale') {
            throw new InvalidArgumentException('Ação inválida.');
        }

        $reason = trim((string)($_POST['reason'] ?? ''));
        $saleService->cancel($empresaId, $currentUserId, [
            'id' => $vendaId,
            'reason' => $reason,
        ]);

        $_SESSION['sale_detail_flash'] = [
            'type' => 'success',
            'message' => 'Venda cancelada com sucesso.',
        ];
        header('Location: venda-detalhes.php?id=' . $vendaId);
        exit;
    } catch (InvalidArgumentException | RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        log_app_exception($e);
        $formError = 'Não foi possível cancelar a venda agora.';
    }
}

try {
    requireSaleDetailAccess('view', $currentNivel);
    $details = $saleService->fullDetails($empresaId, (int)$vendaId);
    $sale = $details['sale'];
    $items = $details['items'];
    $company = $settingsRepository->getEmpresa($empresaId) ?? [];
} catch (InvalidArgumentException | RuntimeException $e) {
    $formError = $formError ?? $e->getMessage();
} catch (Throwable $e) {
    log_app_exception($e);
    $formError = $formError ?? 'Não foi possível carregar os detalhes da venda agora.';
}

$status = (string)($sale['status'] ?? '');
$isCanceled = $status === 'cancelada';
$canCancel = $sale !== null && !$isCanceled && canSaleDetailAccess('cancel', $currentNivel);
$canPrint = $sale !== null && canSaleDetailAccess('print', $currentNivel);
$companyName = (string)($company['nome_fantasia'] ?? $company['nome'] ?? 'Empresa');
$companyDocument = (string)($company['cpf_cnpj'] ?? '');
$companyAddress = (string)($company['endereco'] ?? '');

$pageId = 'venda-detalhes-server';
$pageTitle = $sale ? 'Venda #' . (string)$sale['numero_venda'] : 'Venda';
$activeMenu = 'vendas';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .sale-detail-alert { margin-bottom: 14px; padding: 13px 15px; border: 1px solid var(--line); border-radius: 14px; font-size: 13px; font-weight: 750; }
  .sale-detail-alert.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .sale-detail-alert.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .sale-detail-layout { display: grid; gap: 14px; }
  .sale-detail-main, .sale-detail-side { display: grid; gap: 14px; min-width: 0; }
  .sale-detail-card { padding: 15px; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 22px rgba(29,55,95,.055); }
  .sale-detail-card h2, .sale-detail-card h3 { margin: 0 0 12px; font-size: 18px; letter-spacing: 0; }
  .sale-detail-lines { display: grid; gap: 9px; }
  .sale-detail-lines div { display: flex; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 13px; font-weight: 750; }
  .sale-detail-lines strong { color: var(--ink); text-align: right; }
  .sale-status { display: inline-flex; align-items: center; min-height: 32px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 900; }
  .sale-status.finalizada { color: var(--green); background: rgba(37,196,132,.12); }
  .sale-status.cancelada { color: var(--red); background: rgba(230,83,103,.12); }
  .sale-status.pendente, .sale-status.em_aberto { color: #8A4A00; background: rgba(255,181,71,.16); }
  .sale-item-card { display: grid; gap: 8px; padding: 12px 0; border-bottom: 1px solid var(--line); }
  .sale-item-card:last-child { border-bottom: 0; }
  .sale-item-top { display: flex; justify-content: space-between; gap: 12px; }
  .sale-item-top strong { font-size: 14px; }
  .sale-item-card p { margin: 0; color: var(--muted); font-size: 12px; font-weight: 700; }
  .sale-total-box { padding: 14px; color: #fff; background: var(--blue); border-radius: 14px; }
  .sale-total-box span { display: block; font-size: 12px; font-weight: 800; opacity: .88; }
  .sale-total-box strong { display: block; margin-top: 4px; font-size: 26px; letter-spacing: 0; }
  .sale-detail-actions { display: grid; gap: 9px; }
  .sale-detail-actions .primary-btn, .sale-detail-actions .secondary-btn, .sale-detail-actions .danger-btn { width: 100%; min-height: 44px; }
  .sale-cancel-form textarea { min-height: 88px; resize: vertical; }
  .receipt-print-area { padding: 14px; background: #fff; border: 1px dashed var(--line); border-radius: 14px; color: var(--ink); }
  .receipt-print-area h2 { margin: 0; text-align: center; font-size: 17px; }
  .receipt-print-area p { margin: 4px 0; color: #334155; font-size: 12px; text-align: center; }
  .receipt-line { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; border-bottom: 1px dotted #CBD5E1; font-size: 12px; }
  .receipt-line strong { text-align: right; }
  .receipt-item { padding: 7px 0; border-bottom: 1px dotted #CBD5E1; font-size: 12px; }
  .receipt-item strong { display: block; }
  .sale-empty { padding: 16px; background: #fff; border: 1px dashed var(--blue-line); border-radius: 14px; color: var(--muted); font-size: 13px; font-weight: 800; }
  @media (min-width: 860px) {
    .sale-detail-layout { grid-template-columns: minmax(0, 1fr) minmax(320px, 390px); align-items: start; }
    .sale-detail-side { position: sticky; top: 90px; }
  }
  @media (max-width: 560px) {
    .sale-detail-card { padding: 13px; border-radius: 14px; }
    .sale-item-top { display: grid; }
    .sale-detail-lines div { display: grid; gap: 3px; }
    .sale-detail-lines strong { text-align: left; }
  }
  @media print {
    body * { visibility: hidden; }
    .receipt-print-area, .receipt-print-area * { visibility: visible; }
    .receipt-print-area { position: absolute; left: 0; top: 0; width: 100%; max-width: 360px; background: #fff; color: #000; padding: 0; margin: 0; border: 0; border-radius: 0; }
    .no-print { display: none !important; }
  }
</style>

<header class="plain-header no-print">
  <div class="page-title-row">
    <a class="back-btn" href="relatorios.php" aria-label="Voltar">‹</a>
    <div>
      <p class="micro-label dark-text">Detalhes da venda</p>
      <h1><?= $sale ? 'Venda #' . e((string)$sale['numero_venda']) : 'Venda' ?></h1>
    </div>
    <?php if ($sale): ?>
      <span class="sale-status <?= e($status) ?>"><?= e(saleDetailStatusLabel($status)) ?></span>
    <?php else: ?>
      <span></span>
    <?php endif; ?>
  </div>
</header>

<section class="content-pad">
  <?php if (is_array($flash)): ?>
    <div class="sale-detail-alert <?= e((string)($flash['type'] ?? 'success')) ?> no-print" role="status"><?= e((string)($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <?php if ($formError !== null): ?>
    <div class="sale-detail-alert danger no-print" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>

  <?php if (!$sale): ?>
    <article class="sale-empty no-print">
      <?= e($formError !== null ? 'Não foi possível exibir esta venda.' : 'Venda não encontrada.') ?>
      <div class="section-gap-small"><a class="secondary-btn" href="relatorios.php">Voltar para relatórios</a></div>
    </article>
  <?php else: ?>
    <div class="sale-detail-layout">
      <main class="sale-detail-main">
        <section class="sale-detail-card">
          <h2>Dados da venda</h2>
          <div class="sale-detail-lines">
            <div><span>Número</span><strong><?= e((string)$sale['numero_venda']) ?></strong></div>
            <div><span>Data/hora</span><strong><?= e(saleDetailDateTime($sale['criado_em'])) ?></strong></div>
            <div><span>Operador</span><strong><?= e((string)$sale['operador_nome']) ?></strong></div>
            <div><span>Status</span><strong><?= e(saleDetailStatusLabel($status)) ?></strong></div>
            <?php if ($isCanceled && (string)($sale['motivo_cancelamento'] ?? '') !== ''): ?>
              <div><span>Motivo do cancelamento</span><strong><?= e((string)$sale['motivo_cancelamento']) ?></strong></div>
            <?php endif; ?>
          </div>
        </section>

        <section class="sale-detail-card">
          <h2>Cliente</h2>
          <div class="sale-detail-lines">
            <div><span>Nome</span><strong><?= e((string)$sale['cliente_nome']) ?></strong></div>
            <div><span>Telefone</span><strong><?= e((string)($sale['cliente_telefone'] ?: 'Não informado')) ?></strong></div>
            <div><span>CPF/CNPJ</span><strong><?= e((string)($sale['cliente_cpf_cnpj'] ?: 'Não informado')) ?></strong></div>
            <div><span>Endereço</span><strong><?= e((string)($sale['cliente_endereco'] ?: 'Não informado')) ?></strong></div>
          </div>
        </section>

        <section class="sale-detail-card">
          <h2>Itens vendidos</h2>
          <?php if (!$items): ?>
            <p class="sale-empty">Nenhum item encontrado para esta venda.</p>
          <?php endif; ?>
          <?php foreach ($items as $item): ?>
            <article class="sale-item-card">
              <div class="sale-item-top">
                <strong><?= e((string)$item['produto_nome']) ?></strong>
                <strong><?= e(saleDetailMoney($item['subtotal'])) ?></strong>
              </div>
              <p><?= e(saleDetailNumber($item['quantidade'])) ?> x <?= e(saleDetailMoney($item['preco_unitario'])) ?></p>
              <?php if (($item['lote'] ?? '') !== '' || ($item['validade'] ?? '') !== ''): ?>
                <p>Lote <?= e((string)($item['lote'] ?: 'não informado')) ?> · Validade <?= e((string)($item['validade'] ?: 'não informada')) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </section>
      </main>

      <aside class="sale-detail-side">
        <section class="sale-detail-card">
          <h2>Pagamento</h2>
          <div class="sale-detail-lines">
            <div><span>Forma</span><strong><?= e(saleDetailPaymentLabel((string)$sale['forma_pagamento'])) ?></strong></div>
            <div><span>Subtotal</span><strong><?= e(saleDetailMoney($sale['subtotal'])) ?></strong></div>
            <div><span>Desconto</span><strong><?= e(saleDetailMoney($sale['desconto'])) ?></strong></div>
            <div><span>Valor recebido</span><strong><?= e(saleDetailMoney($sale['valor_recebido'])) ?></strong></div>
            <div><span>Troco</span><strong><?= e(saleDetailMoney($sale['troco'])) ?></strong></div>
          </div>
          <div class="sale-total-box section-gap-small">
            <span>Total</span>
            <strong><?= e(saleDetailMoney($sale['total'])) ?></strong>
          </div>
        </section>

        <section class="sale-detail-card no-print">
          <h2>Ações</h2>
          <div class="sale-detail-actions">
            <?php if ($canPrint): ?>
              <button class="primary-btn" type="button" onclick="window.print()">Imprimir comprovante</button>
            <?php endif; ?>
            <a class="secondary-btn" href="nova-venda.php">Nova venda</a>
            <a class="secondary-btn" href="relatorios.php">Voltar para relatórios</a>
          </div>
        </section>

        <?php if ($canCancel): ?>
          <section class="sale-detail-card no-print">
            <h2>Cancelar venda</h2>
            <form class="sale-cancel-form" method="post" onsubmit="return confirm('Cancelar esta venda e devolver o estoque?');">
              <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="cancel_sale">
              <div class="field">
                <label for="cancelReason">Motivo do cancelamento</label>
                <textarea id="cancelReason" name="reason" required></textarea>
              </div>
              <button class="danger-btn section-gap-small" type="submit">Cancelar venda</button>
            </form>
          </section>
        <?php endif; ?>

        <section class="sale-detail-card">
          <h2 class="no-print">Comprovante</h2>
          <div class="receipt-print-area">
            <h2><?= e($companyName) ?></h2>
            <?php if ($companyDocument !== ''): ?><p><?= e($companyDocument) ?></p><?php endif; ?>
            <?php if ($companyAddress !== ''): ?><p><?= e($companyAddress) ?></p><?php endif; ?>
            <div class="receipt-line"><span>Venda</span><strong>#<?= e((string)$sale['numero_venda']) ?></strong></div>
            <div class="receipt-line"><span>Data</span><strong><?= e(saleDetailDateTime($sale['criado_em'])) ?></strong></div>
            <div class="receipt-line"><span>Operador</span><strong><?= e((string)$sale['operador_nome']) ?></strong></div>
            <div class="receipt-line"><span>Cliente</span><strong><?= e((string)$sale['cliente_nome']) ?></strong></div>

            <?php foreach ($items as $item): ?>
              <div class="receipt-item">
                <strong><?= e((string)$item['produto_nome']) ?></strong>
                <span><?= e(saleDetailNumber($item['quantidade'])) ?> x <?= e(saleDetailMoney($item['preco_unitario'])) ?> = <?= e(saleDetailMoney($item['subtotal'])) ?></span>
              </div>
            <?php endforeach; ?>

            <div class="receipt-line"><span>Subtotal</span><strong><?= e(saleDetailMoney($sale['subtotal'])) ?></strong></div>
            <div class="receipt-line"><span>Desconto</span><strong><?= e(saleDetailMoney($sale['desconto'])) ?></strong></div>
            <div class="receipt-line"><span>Total</span><strong><?= e(saleDetailMoney($sale['total'])) ?></strong></div>
            <div class="receipt-line"><span>Pagamento</span><strong><?= e(saleDetailPaymentLabel((string)$sale['forma_pagamento'])) ?></strong></div>
            <div class="receipt-line"><span>Recebido</span><strong><?= e(saleDetailMoney($sale['valor_recebido'])) ?></strong></div>
            <div class="receipt-line"><span>Troco</span><strong><?= e(saleDetailMoney($sale['troco'])) ?></strong></div>
            <?php if ((string)($sale['observacao'] ?? '') !== ''): ?>
              <p><?= e((string)$sale['observacao']) ?></p>
            <?php endif; ?>
            <p>Obrigado pela preferência.</p>
          </div>
        </section>
      </aside>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
