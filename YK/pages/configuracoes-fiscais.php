<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$canConfigure = $authorization->can('nota_fiscal.configurar');
$canManageCredentials = $authorization->can('nota_fiscal.gerenciar_credenciais');
$canTestIntegration = $authorization->can('nota_fiscal.testar_integracao');
$canActivateProduction = $authorization->can('nota_fiscal.ativar_producao');
$canInutilize = $authorization->can('nota_fiscal.inutilizar');
$runtime = $application->fiscalRuntimeReadiness()->inspect();
$selectedEnvironment = (string) ($_GET['ambiente'] ?? 'homologacao');
if (!in_array($selectedEnvironment, ['homologacao', 'producao'], true)) {
    $selectedEnvironment = 'homologacao';
}
if ($selectedEnvironment === 'producao' && !$canActivateProduction) {
    $selectedEnvironment = 'homologacao';
}
$canEditSelectedEnvironment = $selectedEnvironment === 'producao'
    ? $canActivateProduction
    : $canConfigure;
$selectedModel = (string) ($_GET['modelo'] ?? '65');
if (!in_array($selectedModel, ['55', '65'], true)) {
    $selectedModel = '65';
}
$selectedDocumentLabel = $selectedModel === '55' ? 'NF-e' : 'NFC-e';
$overview = null;
try {
    $overview = $application->fiscalConfiguration()->overview($selectedEnvironment, $selectedModel);
} catch (Throwable $exception) {
    error_log('Fiscal configuration overview unavailable [' . get_class($exception) . '].');
}
$readiness = is_array($overview['readiness'] ?? null) ? $overview['readiness'] : null;
$configuration = is_array($overview['configuration'] ?? null) ? $overview['configuration'] : null;
$integrationTest = is_array($overview['integration_test'] ?? null) ? $overview['integration_test'] : null;
$certificates = is_array($overview['certificates'] ?? null) ? $overview['certificates'] : [];
$series = is_array($overview['series'] ?? null) ? $overview['series'] : [];
$inutilizations = [];
if ($canInutilize && ($selectedEnvironment !== 'producao' || $canActivateProduction)) {
    try {
        $inutilizations = $application->fiscalInutilization()->recent($selectedEnvironment, $selectedModel);
    } catch (Throwable $exception) {
        error_log('Fiscal inutilization overview unavailable [' . get_class($exception) . '].');
    }
}
?>

<div class="page-body settings-page">
  <div class="alert <?= $selectedEnvironment === 'producao' ? 'alert-danger' : 'alert-info' ?> mb-3"><i class="bi bi-shield-check me-2"></i><?= $selectedEnvironment === 'producao' ? 'Produção usa série, numeração e configuração próprias. A ativação só é liberada pelo gate técnico e nunca emite nota durante o teste de status.' : 'Homologação não possui valor fiscal. Conclua uma autorização real de cada modelo antes de solicitar produção.' ?></div>
  <nav class="d-flex flex-wrap gap-2 mb-2" aria-label="Ambiente fiscal">
    <a class="btn-filter <?= $selectedEnvironment === 'homologacao' ? 'btn-filter-primary' : 'btn-filter-ghost' ?>" href="configuracoes-fiscais.php?ambiente=homologacao&amp;modelo=<?= h($selectedModel) ?>">Homologação</a>
    <?php if ($canActivateProduction): ?><a class="btn-filter <?= $selectedEnvironment === 'producao' ? 'btn-filter-primary' : 'btn-filter-ghost' ?>" href="configuracoes-fiscais.php?ambiente=producao&amp;modelo=<?= h($selectedModel) ?>">Produção</a><?php endif; ?>
  </nav>
  <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Modelo de documento fiscal">
    <a class="btn-filter <?= $selectedModel === '55' ? 'btn-filter-primary' : 'btn-filter-ghost' ?>" href="configuracoes-fiscais.php?ambiente=<?= h($selectedEnvironment) ?>&amp;modelo=55"><i class="bi bi-file-earmark-text"></i> NF-e (modelo 55)</a>
    <a class="btn-filter <?= $selectedModel === '65' ? 'btn-filter-primary' : 'btn-filter-ghost' ?>" href="configuracoes-fiscais.php?ambiente=<?= h($selectedEnvironment) ?>&amp;modelo=65"><i class="bi bi-receipt-cutoff"></i> NFC-e (modelo 65)</a>
  </nav>

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
      <div class="panel-header"><div class="panel-title"><i class="bi bi-receipt"></i><?= h($selectedDocumentLabel) ?> de <?= h($selectedEnvironment) ?></div></div>
      <div class="p-3">
        <?php if ($configuration !== null): ?>
          <div class="alert alert-info">Versão <?= (int) $configuration['versao'] ?> · modelo <?= h($selectedModel) ?> · status <strong><?= h((string) $configuration['status']) ?></strong></div>
        <?php endif; ?>
        <?php if ($canEditSelectedEnvironment && $certificates !== [] && $overview !== null): ?>
          <form method="post" action="actions/configuracao-fiscal-salvar.php">
            <?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="ambiente" value="<?= h($selectedEnvironment) ?>"><input type="hidden" name="modelo" value="<?= h($selectedModel) ?>">
            <div class="form-row"><div class="form-group"><label class="form-label" for="fiscal-state">UF</label><input class="form-control-os" id="fiscal-state" name="uf" value="AM" maxlength="2" required></div><div class="form-group"><label class="form-label" for="fiscal-schema">Schema NF-e</label><input class="form-control-os" id="fiscal-schema" name="schema_versao" value="4.00" maxlength="5" required></div></div>
            <div class="form-group"><label class="form-label" for="fiscal-certificate-id">Certificado</label><select class="form-select form-control-os" id="fiscal-certificate-id" name="certificado_id" required><?php foreach ($certificates as $certificate): ?><option value="<?= (int) $certificate['id'] ?>"><?= h((string) ($certificate['titular_nome'] ?? $certificate['titular_cnpj'])) ?> — <?= h(date('d/m/Y', strtotime((string) $certificate['valido_ate']))) ?></option><?php endforeach; ?></select></div>
            <?php if ($selectedModel === '65'): ?>
              <div class="alert alert-info py-2">QR Code v3 é obrigatório e não utiliza CSC na geração do QR. Os campos legados ficam vazios.</div>
              <input type="hidden" name="qr_code_versao" value="3">
            <?php endif; ?>
            <button class="btn-modal-save" type="submit"><i class="bi bi-save"></i> Criar nova versão</button>
          </form>
        <?php elseif ($certificates === []): ?><p class="text-muted">Cadastre primeiro o certificado A1.</p><?php endif; ?>
      </div>
    </section>

    <section class="panel settings-panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-123"></i>Série e numeração</div></div>
      <div class="p-3">
        <?php foreach ($series as $serie): ?><div class="border rounded-3 p-3 mb-2"><strong>Série <?= (int) $serie['serie'] ?></strong><div class="small text-muted">Próximo número: <?= (int) $serie['proximo_numero'] ?></div></div><?php endforeach; ?>
        <?php if ($canEditSelectedEnvironment && $overview !== null): ?><form method="post" action="actions/configuracao-fiscal-serie-salvar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="ambiente" value="<?= h($selectedEnvironment) ?>"><input type="hidden" name="modelo" value="<?= h($selectedModel) ?>"><div class="form-row"><div class="form-group"><label class="form-label" for="fiscal-series">Série</label><input class="form-control-os" id="fiscal-series" type="number" name="serie" min="0" max="999" required></div><div class="form-group"><label class="form-label" for="fiscal-next-number">Próximo número confirmado</label><input class="form-control-os" id="fiscal-next-number" type="number" name="proximo_numero" min="1" max="999999999" required></div></div><p class="small text-muted">Confirme série e próximo número com a contabilidade/SEFAZ. Ao salvar, esta passa a ser a única série ativa do ambiente/modelo; o sistema não presume série 1.</p><button class="btn-modal-save" type="submit"><i class="bi bi-save"></i> Salvar e ativar série</button></form><?php endif; ?>
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

          <?php if (($selectedEnvironment === 'producao' ? $canActivateProduction : $canTestIntegration) && $configuration !== null && $runtime['homologation_ready']): ?>
            <form class="mb-2" method="post" action="actions/configuracao-fiscal-testar-sefaz.php">
              <?= $csrf->field() ?><?php return_to_field(); ?>
              <input type="hidden" name="configuracao_id" value="<?= (int) $configuration['id'] ?>">
              <input type="hidden" name="ambiente" value="<?= h($selectedEnvironment) ?>">
              <button class="btn-modal-save" type="submit"><i class="bi bi-cloud-check"></i> Testar comunicação com a SEFAZ</button>
            </form>
          <?php endif; ?>

          <?php if (($selectedEnvironment === 'producao' ? $canActivateProduction : $canConfigure) && $configuration !== null && $readiness['ready'] && $runtime['homologation_ready'] && ($integrationTest['success'] ?? false) && $configuration['status'] !== 'ativa'): ?><form method="post" action="actions/configuracao-fiscal-ativar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="configuracao_id" value="<?= (int) $configuration['id'] ?>"><input type="hidden" name="ambiente" value="<?= h($selectedEnvironment) ?>"><button class="btn-modal-save" type="submit"><i class="bi bi-check2-circle"></i> Ativar configuração de <?= h($selectedEnvironment) ?></button></form><?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <?php if ($canInutilize && ($selectedEnvironment !== 'producao' || $canActivateProduction) && $configuration !== null): ?>
    <section class="panel mt-4">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-slash-circle"></i> Inutilização de faixa</div></div>
      <div class="p-3">
        <div class="alert alert-warning">Use somente para lacunas reais, abaixo do próximo número da série. A faixa é bloqueada se houver documento ou pedido anterior e não é repetida automaticamente após timeout.</div>
        <form method="post" action="actions/nota-fiscal-inutilizar.php">
          <?= $csrf->field() ?><?php return_to_field(); ?>
          <input type="hidden" name="ambiente" value="<?= h($selectedEnvironment) ?>">
          <input type="hidden" name="modelo" value="<?= h($selectedModel) ?>">
          <input type="hidden" name="configuracao_id" value="<?= (int) $configuration['id'] ?>">
          <input type="hidden" name="idempotency_key" value="<?= h(bin2hex(random_bytes(32))) ?>">
          <div class="form-row">
            <div class="form-group"><label class="form-label" for="inut-serie">Série</label><select class="form-select form-control-os" id="inut-serie" name="serie" required><option value="">Selecione</option><?php foreach ($series as $item): ?><option value="<?= (int) $item['serie'] ?>"><?= (int) $item['serie'] ?> · próximo <?= (int) $item['proximo_numero'] ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label" for="inut-ano">Ano</label><input class="form-control-os" id="inut-ano" type="number" name="ano" min="2006" max="2099" value="<?= h(date('Y')) ?>" required></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label" for="inut-inicio">Número inicial</label><input class="form-control-os" id="inut-inicio" type="number" name="numero_inicial" min="1" max="999999999" required></div>
            <div class="form-group"><label class="form-label" for="inut-fim">Número final</label><input class="form-control-os" id="inut-fim" type="number" name="numero_final" min="1" max="999999999" required></div>
          </div>
          <div class="form-group"><label class="form-label" for="inut-justificativa">Justificativa</label><textarea class="form-control-os" id="inut-justificativa" name="justificativa" minlength="15" maxlength="255" required></textarea></div>
          <button class="btn btn-danger" type="submit"><i class="bi bi-slash-circle"></i> Solicitar inutilização na SEFAZ</button>
        </form>
        <?php if ($inutilizations !== []): ?><div class="table-responsive mt-3"><table class="table align-middle"><thead><tr><th>Faixa</th><th>Ano</th><th>Status</th><th>Retorno</th></tr></thead><tbody><?php foreach ($inutilizations as $item): ?><tr><td><?= (int) $item['serie'] ?>/<?= (int) $item['numero_inicial'] ?>–<?= (int) $item['numero_final'] ?></td><td><?= (int) $item['ano'] ?></td><td><?= h((string) $item['status']) ?></td><td><?= h(trim((string) ($item['cstat'] ?? '') . ' ' . (string) ($item['xmotivo'] ?? ''))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
