<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/024_fiscal_documents_lifecycle.sql');
$numbering = file_get_contents($root . '/database/migrations/025_fiscal_document_numbering_by_model.sql');
$hardening = file_get_contents($root . '/database/migrations/027_fiscal_authorization_hardening.sql');
$completion = file_get_contents($root . '/database/sql/fiscal_completion_2026.sql');
$finalSql = file_get_contents($root . '/database/sql/fiscal_sefaz_final_2026.sql');
$preflightSql = file_get_contents($root . '/database/sql/fiscal_preflight_checks.sql');
$repository = file_get_contents($root . '/src/Fiscal/Repository/FiscalDocumentRepository.php');
$service = file_get_contents($root . '/src/Fiscal/Service/FiscalDocumentService.php');
$authorization = file_get_contents($root . '/src/Fiscal/Service/FiscalAuthorizationService.php');
$printer = file_get_contents($root . '/src/Fiscal/Service/FiscalDocumentPrintService.php');
$storage = file_get_contents($root . '/src/Fiscal/Storage/FiscalDocumentStorage.php');
$action = file_get_contents($root . '/actions/nota-fiscal-preparar.php');
$ordersPage = file_get_contents($root . '/pages/ordens-servico.php');
$receivablesPage = file_get_contents($root . '/pages/contas-receber.php');
$billingPage = file_get_contents($root . '/pages/faturamento.php');
$configurationPage = file_get_contents($root . '/pages/configuracoes-fiscais.php');
$inutilization = file_get_contents($root . '/src/Fiscal/Service/FiscalInutilizationService.php');
$productionGate = file_get_contents($root . '/src/Fiscal/Service/FiscalProductionGate.php');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$compactAction = preg_replace('/\s+/', '', (string) $action) ?? '';

$expectations = [
    'migration lifecycle status' => str_contains((string) $migration, 'processamento_status ENUM('),
    'migration immutable snapshot' => str_contains((string) $migration, 'snapshot_json JSON'),
    'migration idempotency' => str_contains((string) $migration, 'uq_documento_fiscal_idempotency'),
    'migration one normal document per order' => str_contains((string) $hardening, 'uq_documento_fiscal_origem_normal'),
    'migration stable key and reconciliation' => str_contains((string) $hardening, 'uq_documento_fiscal_chave')
        && str_contains((string) $hardening, 'reconsulta_apos'),
    'migration event history' => str_contains((string) $migration, 'CREATE TABLE IF NOT EXISTS fiscal_documento_eventos'),
    'completion keeps immutable transmission attempts' => str_contains((string)$completion, 'CREATE TABLE IF NOT EXISTS fiscal_documento_tentativas')
        && str_contains((string)$repository, 'createTransmissionAttempt')
        && str_contains((string)$authorization, 'updateLatestTransmissionAttempt'),
    'completion persists payment allocation' => str_contains((string)$completion, 'CREATE TABLE IF NOT EXISTS fiscal_pagamento_alocacoes')
        && str_contains((string)$repository, 'persistPaymentAllocations'),
    'rejected document has controlled reprepare' => str_contains((string) $repository, 'resetRejectedForRetry')
        && str_contains((string) $service, 'rejeicao_corrigida')
        && str_contains((string) $service, 'assertRejectedRetryModel'),
    'production has explicit gate' => str_contains((string) $productionGate, 'authorized_homologation')
        && str_contains((string) $productionGate, 'production_status_service')
        && str_contains((string) $productionGate, 'schema_compatible'),
    'inutilization is persisted and guarded' => str_contains((string) $finalSql, 'CREATE TABLE IF NOT EXISTS fiscal_inutilizacoes')
        && str_contains((string) $inutilization, 'pendente_confirmacao')
        && str_contains((string) $inutilization, 'assertSameRequest')
        && str_contains((string) $inutilization, 'inutilization_prepare'),
    'only one fiscal series can drive emission' => str_contains((string) $repository, 'LIMIT 2')
        && str_contains((string) $repository, 'Existe mais de uma série ativa'),
    'preflight is read only' => str_contains((string) $preflightSql, 'information_schema.tables')
        && !preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP)\b/i', (string) preg_replace('/^--.*$/m', '', (string) $preflightSql)),
    'number unique by model' => str_contains((string) $numbering, '(ambiente, modelo, serie, numero)'),
    'repository row locking' => str_contains((string) $repository, 'FOR UPDATE'),
    'repository reserves series atomically' => preg_match(
        '/WHERE\s+id\s*=\s*:series_id\s+AND\s+proximo_numero\s*=\s*:expected_number/s',
        (string) $repository
    ) === 1 && str_contains((string) $repository, '$statement->rowCount() !== 1'),
    'service token validation' => str_contains((string) $service, '/^[a-f0-9]{64}$/'),
    'service requires finalized order' => str_contains((string) $service, "!== 'finalizada'"),
    'service snapshots services and payments' => str_contains((string) $service, "'services' =>")
        && str_contains((string) $service, "'payments' =>"),
    'service separates municipal invoice' => str_contains((string) $service, 'Serviços exigem NFS-e'),
    'service validates server gate' => str_contains((string) $service, "['homologation_ready']"),
    'signed xml persisted before network' => strpos((string) $authorization, 'markSignedForTransmission')
        < strpos((string) $authorization, 'sefazEnviaLote'),
    'authorization reconciles receipt or key' => str_contains((string) $authorization, 'sefazConsultaRecibo')
        && str_contains((string) $authorization, 'sefazConsultaChave')
        && str_contains((string) $authorization, "=== '539'"),
    'authorization claims cancellation before sefaz' => str_contains((string) $repository, 'claimCancellation')
        && str_contains((string) $repository, "cancelamento_status = \\'nenhum\\'")
        && strpos((string) $authorization, 'claimCancellation') < strpos((string) $authorization, 'sefazCancela')
        && str_contains((string) $authorization, 'releaseCancellationClaim'),
    'print requires authorized status' => str_contains((string) $printer, "!== 'autorizado'"),
    'print validates cstat 100 or 150' => str_contains((string) $printer, "['100', '150']"),
    'print uses official renderers' => str_contains((string) $printer, 'new Danfe(') && str_contains((string) $printer, 'new Danfce('),
    'storage blocks traversal' => str_contains((string) $storage, '!str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)'),
    'storage verifies checksum' => str_contains((string) $storage, 'hash_equals($expectedSha256'),
    'action is post only' => str_contains((string) $action, 'os_require_post_request()'),
    'action requires fiscal permission' => str_contains($compactAction, "os_action_context('nota_fiscal.emitir')"),
    'action transmits after preparation' => str_contains($compactAction, '->fiscalAuthorization()->transmit('),
    'order dropdown pattern kept' => str_contains((string) $ordersPage, 'table-action-dropdown'),
    'order non fiscal options separated' => str_contains((string) $ordersPage, 'Comprovante não fiscal sem valores')
        && str_contains((string) $ordersPage, 'Comprovante não fiscal com valores'),
    'receivables fiscal only when paid' => str_contains((string) $receivablesPage, "\$account['status'] === 'paga'"),
    'billing only prints authorized' => str_contains((string) $billingPage, "processamento_status'] ?? '') === 'autorizado'"),
    'configuration supports both models' => str_contains((string) $configurationPage, 'modelo=55')
        && str_contains((string) $configurationPage, 'modelo=65'),
    'configuration requires csc only for nfce' => str_contains((string) $configurationPage, "if (\$selectedModel === '65')"),
    'sped da dependency' => ($composer['require']['nfephp-org/sped-da'] ?? null) === '^1.1',
    'sped nfe minimum reviewed release' => ($composer['require']['nfephp-org/sped-nfe'] ?? null) === '^5.2.8',
];

foreach ($expectations as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FiscalDocumentLifecycleTest failed: {$label}\n");
        exit(1);
    }
}

echo 'FiscalDocumentLifecycleTest: OK (' . count($expectations) . " assertions)\n";
