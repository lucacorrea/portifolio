<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migration = file_get_contents($root . '/database/migrations/024_fiscal_documents_lifecycle.sql');
$numbering = file_get_contents($root . '/database/migrations/025_fiscal_document_numbering_by_model.sql');
$repository = file_get_contents($root . '/src/Fiscal/Repository/FiscalDocumentRepository.php');
$service = file_get_contents($root . '/src/Fiscal/Service/FiscalDocumentService.php');
$printer = file_get_contents($root . '/src/Fiscal/Service/FiscalDocumentPrintService.php');
$storage = file_get_contents($root . '/src/Fiscal/Storage/FiscalDocumentStorage.php');
$action = file_get_contents($root . '/actions/nota-fiscal-preparar.php');
$ordersPage = file_get_contents($root . '/pages/ordens-servico.php');
$receivablesPage = file_get_contents($root . '/pages/contas-receber.php');
$billingPage = file_get_contents($root . '/pages/faturamento.php');
$configurationPage = file_get_contents($root . '/pages/configuracoes-fiscais.php');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

$expectations = [
    'migration lifecycle status' => str_contains((string) $migration, 'processamento_status ENUM('),
    'migration immutable snapshot' => str_contains((string) $migration, 'snapshot_json JSON'),
    'migration idempotency' => str_contains((string) $migration, 'uq_documento_fiscal_idempotency'),
    'migration order model uniqueness' => str_contains((string) $migration, 'uq_documento_fiscal_origem_modelo'),
    'migration event history' => str_contains((string) $migration, 'CREATE TABLE IF NOT EXISTS fiscal_documento_eventos'),
    'number unique by model' => str_contains((string) $numbering, '(ambiente, modelo, serie, numero)'),
    'repository row locking' => str_contains((string) $repository, 'FOR UPDATE'),
    'repository reserves series atomically' => str_contains((string) $repository, 'WHERE id = :id AND proximo_numero = :number'),
    'service token validation' => str_contains((string) $service, '/^[a-f0-9]{64}$/'),
    'service requires finalized order' => str_contains((string) $service, "!== 'finalizada'"),
    'service separates municipal invoice' => str_contains((string) $service, 'Serviços exigem NFS-e'),
    'service validates server gate' => str_contains((string) $service, "['homologation_ready']"),
    'print requires authorized status' => str_contains((string) $printer, "!== 'autorizado'"),
    'print validates protocol cstat 100' => str_contains((string) $printer, "\$protocol['status'] !== '100'"),
    'print uses official renderers' => str_contains((string) $printer, 'new Danfe(') && str_contains((string) $printer, 'new Danfce('),
    'storage blocks traversal' => str_contains((string) $storage, '!str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)'),
    'storage verifies checksum' => str_contains((string) $storage, 'hash_equals($expectedSha256'),
    'action is post only' => str_contains((string) $action, 'os_require_post_request()'),
    'action requires fiscal permission' => str_contains((string) $action, "os_action_context('nota_fiscal.emitir')"),
    'order dropdown pattern kept' => str_contains((string) $ordersPage, 'table-action-dropdown'),
    'order non fiscal options separated' => str_contains((string) $ordersPage, 'Comprovante não fiscal sem valores')
        && str_contains((string) $ordersPage, 'Comprovante não fiscal com valores'),
    'receivables fiscal only when paid' => str_contains((string) $receivablesPage, "\$account['status'] === 'paga'"),
    'billing only prints authorized' => str_contains((string) $billingPage, "processamento_status'] ?? '') === 'autorizado'"),
    'configuration supports both models' => str_contains((string) $configurationPage, 'configuracoes-fiscais.php?modelo=55')
        && str_contains((string) $configurationPage, 'configuracoes-fiscais.php?modelo=65'),
    'configuration requires csc only for nfce' => str_contains((string) $configurationPage, "if (\$selectedModel === '65')"),
    'sped da dependency' => ($composer['require']['nfephp-org/sped-da'] ?? null) === '^1.1',
];

foreach ($expectations as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FiscalDocumentLifecycleTest failed: {$label}\n");
        exit(1);
    }
}

echo 'FiscalDocumentLifecycleTest: OK (' . count($expectations) . " assertions)\n";
