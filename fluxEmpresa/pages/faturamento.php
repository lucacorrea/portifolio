<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$canViewFiscal = $authorization->can('nota_fiscal.visualizar');
$canViewReceipts = $authorization->can('recibo.visualizar');
$canIssueReceipt = $authorization->can('recibo.emitir');
$canReprintReceipt = $authorization->can('recibo.reimprimir');
$runtime = $application->fiscalRuntimeReadiness()->inspect();
$fiscalOverview = null;
if ($canViewFiscal) {
    try {
        $fiscalOverview = $application->fiscalConfiguration()->overview('homologacao', '65');
    } catch (Throwable $exception) {
        error_log('Fiscal billing overview unavailable [' . get_class($exception) . '].');
    }
}
$readiness = is_array($fiscalOverview['readiness'] ?? null) ? $fiscalOverview['readiness'] : null;
$configuration = is_array($fiscalOverview['configuration'] ?? null) ? $fiscalOverview['configuration'] : null;
$receiptFilters = [
  'search' => trim((string) ($_GET['search'] ?? '')),
  'status' => trim((string) ($_GET['receipt_status'] ?? '')),
  'type' => trim((string) ($_GET['receipt_type'] ?? '')),
];
if (!in_array($receiptFilters['status'], ['', 'emitido', 'cancelado'], true)) $receiptFilters['status'] = '';
if (!in_array($receiptFilters['type'], ['', 'os', 'avulso'], true)) $receiptFilters['type'] = '';
$receipts = [];
if ($canViewReceipts && method_exists($application->receiptService(), 'listReceipts')) {
  try {
    $receipts = $application->receiptService()->listReceipts($receiptFilters);
  } catch (Throwable $exception) {
    error_log('Receipt listing unavailable [' . get_class($exception) . '].');
  }
}
$receiptClients = $canIssueReceipt ? array_values(array_filter(
  $application->clientManagement()->listClients(),
  static fn($client): bool => $client->status() === 'ativo'
)) : [];

function billing_receipt_money(mixed $value): string { return money((string) $value); }
function billing_receipt_date(mixed $value): string
{
  try { return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i'); }
  catch (Throwable) { return '-'; }
}
function billing_receipt_type(array $receipt): string
{
  return !empty($receipt['pagamento_id']) || !empty($receipt['ordem_servico_id']) ? 'OS' : 'Avulso';
}
?>

<div class="page-body">
  <?php if ($canViewFiscal): ?>
    <section class="panel mb-4">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-receipt-cutoff"></i>Emissão fiscal</div><?php if ($authorization->can('nota_fiscal.configurar')): ?><a class="btn-filter btn-filter-primary" href="configuracoes-fiscais.php"><i class="bi bi-gear"></i> Configurar</a><?php endif; ?></div>
      <div class="p-3">
        <div class="row g-3">
          <div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100"><span class="text-muted d-block">Ambiente</span><strong>Homologação</strong></div></div>
          <div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100"><span class="text-muted d-block">Configuração NFC-e</span><strong><?= $configuration === null ? 'Não configurada' : h((string) $configuration['status']) ?></strong></div></div>
          <div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100"><span class="text-muted d-block">Comunicação SEFAZ</span><strong><?= $runtime['homologation_ready'] && $readiness !== null && $readiness['ready'] ? 'Pronta para teste técnico' : 'Bloqueada por pendências' ?></strong></div></div>
        </div>
        <?php if ($readiness !== null && $readiness['errors'] !== []): ?><div class="alert alert-warning mt-3 mb-0">Existem <?= count($readiness['errors']) ?> pendência(s) cadastral(is) ou técnica(s). Consulte a Configuração Fiscal.</div><?php endif; ?>
        <?php if ($fiscalOverview === null): ?><div class="alert alert-warning mt-3 mb-0">A fundação fiscal ainda não está disponível no banco.</div><?php endif; ?>
      </div>
    </section>

    <section class="panel mb-4">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-file-earmark-code"></i>Documentos NF-e / NFC-e</div></div>
      <div class="empty-state py-5"><i class="bi bi-shield-exclamation"></i><h3>Emissão ainda não liberada</h3><p>A tela não simula notas fiscais. Os documentos aparecerão aqui somente após o adaptador oficial, o teste em homologação e a autorização real da SEFAZ.</p></div>
    </section>
  <?php endif; ?>

  <?php if ($authorization->canAny(['recibo.visualizar', 'recibo.emitir', 'boleto.visualizar'])): ?>
    <section class="panel" id="recibos">
      <div class="panel-header"><div><div class="panel-title"><i class="bi bi-journal-check"></i>Recibos</div><p class="text-muted small mb-0 mt-1">Documentos não fiscais de pagamentos de OS ou recebimentos avulsos.</p></div><?php if ($canIssueReceipt): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-standalone-receipt"><i class="bi bi-plus-lg"></i> Novo recibo</button><?php endif; ?></div>
      <?php if ($canViewReceipts): ?>
        <form class="filter-bar" method="get" action="faturamento.php" data-live-filter="receipts" data-live-regions="receipt-results">
          <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($receiptFilters['search']) ?>" placeholder="Número, cliente ou descrição"></div>
          <select class="filter-select" name="receipt_type" aria-label="Tipo do recibo"><option value="">Todos os tipos</option><option value="os" <?= $receiptFilters['type'] === 'os' ? 'selected' : '' ?>>Pagamento de OS</option><option value="avulso" <?= $receiptFilters['type'] === 'avulso' ? 'selected' : '' ?>>Avulso</option></select>
          <select class="filter-select" name="receipt_status" aria-label="Status do recibo"><option value="">Todos os status</option><option value="emitido" <?= $receiptFilters['status'] === 'emitido' ? 'selected' : '' ?>>Emitidos</option><option value="cancelado" <?= $receiptFilters['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelados</option></select>
          <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
          <a class="btn-filter btn-filter-ghost" href="faturamento.php#recibos" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
        </form>
        <div data-live-region="receipt-results">
          <?php if ($receipts === []): ?>
            <?php empty_state('Nenhum recibo encontrado', 'Emita um recibo avulso ou registre o pagamento de uma OS.'); ?>
          <?php else: ?>
            <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Recibo</th><th>Tipo</th><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Emissão</th><th>Status</th><th>Ações</th></tr></thead><tbody>
            <?php foreach ($receipts as $receipt): ?>
              <tr>
                <td><strong><?= h((string) ($receipt['numero'] ?? '')) ?></strong><?php if (!empty($receipt['os_numero'])): ?><br><small class="text-muted"><?= h((string) $receipt['os_numero']) ?></small><?php endif; ?></td>
                <td><?= h(billing_receipt_type($receipt)) ?></td>
                <td><?= h((string) ($receipt['cliente_nome'] ?? 'Cliente avulso')) ?></td>
                <td class="receipt-description-cell"><?= h((string) ($receipt['descricao'] ?? '')) ?></td>
                <td><strong><?= h(billing_receipt_money($receipt['valor'] ?? '0')) ?></strong></td>
                <td><?= h(billing_receipt_date($receipt['emitido_em'] ?? null)) ?></td>
                <td><span class="badge-soft badge-<?= ($receipt['status'] ?? '') === 'emitido' ? 'green' : 'gray' ?>"><?= ($receipt['status'] ?? '') === 'emitido' ? 'Emitido' : 'Cancelado' ?></span></td>
                <td class="table-actions-cell"><?php if ($canReprintReceipt): ?><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do recibo <?= h((string) ($receipt['numero'] ?? '')) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="recibo-imprimir.php?id=<?= h((string) $receipt['id']) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir recibo</a></li></ul></div><?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="p-3"><div class="alert alert-info mb-0">Você pode emitir recibos, mas não possui permissão para consultar o histórico.</div></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php if ($canIssueReceipt): ?>
<div class="modal fade" id="modal-standalone-receipt" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/recibo-avulso-emitir.php" target="_blank" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo recibo</h2><p class="text-muted small mb-0">Use um cliente cadastrado ou informe os dados de um recebimento avulso.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-info"><i class="bi bi-image"></i> A logo e os dados atuais da empresa serão incluídos automaticamente no recibo.</div><fieldset class="form-section"><legend class="form-section-title">Cliente</legend><div class="form-row"><div class="form-group"><label class="form-label" for="standalone-receipt-mode">Origem do cliente</label><select class="form-control-os" id="standalone-receipt-mode"><option value="registered" <?= $receiptClients !== [] ? 'selected' : '' ?>>Cliente cadastrado</option><option value="standalone" <?= $receiptClients === [] ? 'selected' : '' ?>>Cliente avulso</option></select></div><div class="form-group" data-receipt-registered-client><label class="form-label" for="standalone-receipt-client">Cliente cadastrado</label><select class="form-control-os" id="standalone-receipt-client" name="cliente_id"><option value="">Selecione</option><?php foreach ($receiptClients as $client): ?><option value="<?= h((string) $client->id()) ?>"><?= h($client->name()) ?></option><?php endforeach; ?></select></div></div><div class="form-row" data-receipt-standalone-client><div class="form-group"><label class="form-label" for="standalone-receipt-name">Nome do cliente</label><input class="form-control-os" id="standalone-receipt-name" name="cliente_nome" maxlength="150"></div><div class="form-group"><label class="form-label" for="standalone-receipt-document">CPF/CNPJ <span class="text-muted">(opcional)</span></label><input class="form-control-os" id="standalone-receipt-document" name="cliente_documento" maxlength="20"></div></div></fieldset><fieldset class="form-section"><legend class="form-section-title">Recebimento</legend><div class="form-group"><label class="form-label" for="standalone-receipt-description">Referente a</label><textarea class="form-control-os" id="standalone-receipt-description" name="descricao" maxlength="1000" rows="4" required></textarea></div><div class="form-row"><div class="form-group"><label class="form-label" for="standalone-receipt-value">Valor</label><input class="form-control-os" id="standalone-receipt-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="standalone-receipt-payment">Forma de pagamento</label><select class="form-control-os" id="standalone-receipt-payment" name="forma_pagamento" required><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartão de débito</option><option value="cartao_credito">Cartão de crédito</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></div></div></fieldset></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-receipt-cutoff"></i> Emitir e abrir recibo</button></div></form></div></div></div>
<?php endif; ?>
