<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$canConfigure = $authorization->can('nota_fiscal.configurar');
$canManageCredentials = $authorization->can('nota_fiscal.gerenciar_credenciais');
$canTestIntegration = $authorization->can('nota_fiscal.testar_integracao');
$runtime = $application->fiscalRuntimeReadiness()->inspect();
$overview = null;
try {
    $overview = $application->fiscalConfiguration()->overview('homologacao', '65');
} catch (Throwable $exception) {
    error_log('Fiscal configuration overview unavailable [' . get_class($exception) . '].');
}
$readiness = is_array($overview['readiness'] ?? null) ? $overview['readiness'] : null;
$configuration = is_array($overview['configuration'] ?? null) ? $overview['configuration'] : null;
$integrationTest = is_array($overview['integration_test'] ?? null) ? $overview['integration_test'] : null;
$certificates = is_array($overview['certificates'] ?? null) ? $overview['certificates'] : [];
$series = is_array($overview['series'] ?? null) ? $overview['series'] : [];
?>

<div class="page-body settings-page">
  <div class="alert alert-info mb-4"><i class="bi bi-shield-check me-2"></i>A configuração começa em <strong>homologação</strong>. Produção e emissão permanecem bloqueadas até a validação técnica completa.</div>

  <?php if ($overview === null): ?>
    <div class="alert alert-warning"><i class="bi bi-database-exclamation me-2"></i>A estrutura fiscal ainda não está disponível. Execute a migração 017 pelo processo controlado antes de configurar.</div>
  <?php endif; ?>

  <section class="panel mb-4">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-pc-display-horizontal"></i>Requisitos do servidor</div></div>
    <div class="p-3">
      <div class="row g-2">
        <?php foreach ($runtime['checks'] as $check): ?>
          <div class="col-12 col-md-6"><div class="d-flex justify-content-between align-items-center border rounded-3 p-3 h-100"><span><?= h($check['label']) ?></span><span class="badge <?= $check['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $check['ok'] ? 'OK' : 'Pendente' ?></span></div></div>
        <?php endforeach; ?>
      </div>
      <?php if (!$runtime['homologation_ready']): ?><p class="text-danger mt-3 mb-0">Nenhuma chamada à SEFAZ será liberada enquanto houver requisito pendente.</p><?php endif; ?>
    </div>
  </section>

  <div class="settings-grid">
    <section class="panel settings-panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-file-earmark-lock2"></i>Certificado digital A1</div></div>
      <div class="p-3">
        <p class="text-muted">O PFX/P12 é validado contra o CNPJ da empresa e armazenado fora do public_html. A senha é cifrada e nunca volta para a tela.</p>
        <?php if ($certificates !== []): ?>
          <?php foreach ($certificates as $certificate): ?>
            <div class="border rounded-3 p-3 mb-2"><strong><?= h((string) ($certificate['titular_nome'] ?? 'Certificado A1')) ?></strong><div class="small text-muted">CNPJ <?= h((string) $certificate['titular_cnpj']) ?> · válido até <?= h(date('d/m/Y', strtotime((string) $certificate['valido_ate']))) ?></div></div>
          <?php endforeach; ?>
        <?php else: ?><div class="alert alert-warning">Nenhum certificado A1 válido cadastrado.</div><?php endif; ?>

        <?php if ($canManageCredentials && $overview !== null): ?>
          <form method="post" action="actions/configuracao-fiscal-certificado-salvar.php" enctype="multipart/form-data" data-fiscal-certificate-form>
            <?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
            <div class="form-group"><label class="form-label" for="fiscal-certificate">Certificado PFX/P12</label><input class="form-control-os" id="fiscal-certificate" type="file" name="certificado" accept=".pfx,.p12,application/x-pkcs12" required></div>
            <div class="form-group"><label class="form-label" for="fiscal-certificate-password">Senha do certificado</label><input class="form-control-os" id="fiscal-certificate-password" type="password" name="senha_certificado" maxlength="200" autocomplete="new-password" required></div>
            <div class="alert alert-danger py-2 d-none" role="alert" data-fiscal-certificate-feedback></div>
            <button class="btn-modal-save" type="submit" data-fiscal-certificate-submit><i class="bi bi-shield-lock"></i> Validar e armazenar</button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel settings-panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-receipt"></i>NFC-e de homologação</div></div>
      <div class="p-3">
        <?php if ($configuration !== null): ?>
          <div class="alert alert-info">Versão <?= (int) $configuration['versao'] ?> · série/modelo 65 · status <strong><?= h((string) $configuration['status']) ?></strong></div>
        <?php endif; ?>
        <?php if ($canConfigure && $certificates !== [] && $overview !== null): ?>
          <form method="post" action="actions/configuracao-fiscal-salvar.php">
            <?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="ambiente" value="homologacao"><input type="hidden" name="modelo" value="65">
            <div class="form-row"><div class="form-group"><label class="form-label" for="fiscal-state">UF</label><input class="form-control-os" id="fiscal-state" name="uf" value="AM" maxlength="2" required></div><div class="form-group"><label class="form-label" for="fiscal-schema">Schema NF-e</label><input class="form-control-os" id="fiscal-schema" name="schema_versao" value="4.00" maxlength="5" required></div></div>
            <div class="form-group"><label class="form-label" for="fiscal-certificate-id">Certificado</label><select class="form-select form-control-os" id="fiscal-certificate-id" name="certificado_id" required><?php foreach ($certificates as $certificate): ?><option value="<?= (int) $certificate['id'] ?>"><?= h((string) ($certificate['titular_nome'] ?? $certificate['titular_cnpj'])) ?> — <?= h(date('d/m/Y', strtotime((string) $certificate['valido_ate']))) ?></option><?php endforeach; ?></select></div>
            <div class="form-row"><div class="form-group"><label class="form-label" for="fiscal-csc-id">ID do CSC</label><input class="form-control-os" id="fiscal-csc-id" name="csc_id" maxlength="40" autocomplete="off" required></div><div class="form-group"><label class="form-label" for="fiscal-csc">CSC de homologação</label><input class="form-control-os" id="fiscal-csc" type="password" name="csc" maxlength="120" autocomplete="new-password" required></div></div>
            <input type="hidden" name="qr_code_versao" value="3">
            <button class="btn-modal-save" type="submit"><i class="bi bi-save"></i> Criar nova versão</button>
          </form>
        <?php elseif ($certificates === []): ?><p class="text-muted">Cadastre primeiro o certificado A1.</p><?php endif; ?>
      </div>
    </section>

    <section class="panel settings-panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-123"></i>Série e numeração</div></div>
      <div class="p-3">
        <?php foreach ($series as $serie): ?><div class="border rounded-3 p-3 mb-2"><strong>Série <?= (int) $serie['serie'] ?></strong><div class="small text-muted">Próximo número: <?= (int) $serie['proximo_numero'] ?></div></div><?php endforeach; ?>
        <?php if ($canConfigure && $overview !== null): ?><form method="post" action="actions/configuracao-fiscal-serie-salvar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="ambiente" value="homologacao"><input type="hidden" name="modelo" value="65"><div class="form-row"><div class="form-group"><label class="form-label" for="fiscal-series">Série</label><input class="form-control-os" id="fiscal-series" type="number" name="serie" min="0" max="999" value="1" required></div><div class="form-group"><label class="form-label" for="fiscal-next-number">Próximo número</label><input class="form-control-os" id="fiscal-next-number" type="number" name="proximo_numero" min="1" max="999999999" value="1" required></div></div><button class="btn-modal-save" type="submit"><i class="bi bi-save"></i> Salvar série</button></form><?php endif; ?>
      </div>
    </section>

    <section class="panel settings-panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-clipboard2-check"></i>Checklist fiscal</div></div>
      <div class="p-3">
        <?php if ($readiness === null): ?><p class="text-muted">Checklist indisponível até a migração fiscal.</p><?php else: ?>
          <?php foreach ($readiness['errors'] as $error): ?><div class="alert alert-danger py-2"><?= h($error) ?></div><?php endforeach; ?>
          <?php foreach ($readiness['warnings'] as $warning): ?><div class="alert alert-warning py-2"><?= h($warning) ?></div><?php endforeach; ?>
          <?php if ($readiness['errors'] === []): ?><div class="alert alert-success">Cadastros fiscais completos para o teste em homologação.</div><?php endif; ?>

          <?php if ($integrationTest !== null): ?>
            <div class="alert <?= $integrationTest['success'] ? 'alert-success' : 'alert-danger' ?> py-2">
              Último teste SEFAZ em <?= h(date('d/m/Y H:i', strtotime((string) $integrationTest['tested_at']))) ?>:
              <strong><?= h((string) ($integrationTest['code'] ?: 'falha')) ?></strong> — <?= h((string) $integrationTest['message']) ?>
            </div>
          <?php endif; ?>

          <?php if ($canTestIntegration && $configuration !== null && $runtime['homologation_ready']): ?>
            <form class="mb-2" method="post" action="actions/configuracao-fiscal-testar-sefaz.php">
              <?= $csrf->field() ?><?php return_to_field(); ?>
              <input type="hidden" name="configuracao_id" value="<?= (int) $configuration['id'] ?>">
              <button class="btn-modal-save" type="submit"><i class="bi bi-cloud-check"></i> Testar comunicação com a SEFAZ</button>
            </form>
          <?php endif; ?>

          <?php if ($canConfigure && $configuration !== null && $readiness['ready'] && $runtime['homologation_ready'] && ($integrationTest['success'] ?? false) && $configuration['status'] !== 'ativa'): ?><form method="post" action="actions/configuracao-fiscal-ativar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="configuracao_id" value="<?= (int) $configuration['id'] ?>"><button class="btn-modal-save" type="submit"><i class="bi bi-check2-circle"></i> Ativar configuração de homologação</button></form><?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
