<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$canViewFiscal = $authorization->can('nota_fiscal.visualizar');
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

  <?php if ($authorization->canAny(['recibo.visualizar', 'boleto.visualizar'])): ?>
    <section class="panel" id="recibos">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-journal-check"></i>Recibos e boletos</div></div>
      <div class="p-3"><div class="alert alert-info mb-0">Recibos não fiscais continuam vinculados aos pagamentos das Ordens de Serviço. Eles não substituem NF-e, NFC-e ou NFS-e.</div></div>
    </section>
  <?php endif; ?>
</div>
