<?php

declare(strict_types=1);

function masterDataDeletionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$sources = [];
foreach ([
    'clientPage' => '/pages/clientes.php',
    'budgetPage' => '/pages/orcamentos.php',
    'servicePage' => '/pages/servicos.php',
    'orderPage' => '/pages/ordens-servico.php',
    'clientAction' => '/actions/cliente-excluir.php',
    'budgetAction' => '/actions/orcamento-excluir.php',
    'serviceAction' => '/actions/servico-excluir.php',
    'orderAction' => '/actions/os-excluir.php',
    'clientRepository' => '/src/CRM/Repository/ClientRepository.php',
    'budgetRepository' => '/src/Sales/Repository/BudgetRepository.php',
    'serviceRepository' => '/src/Catalog/Repository/ServiceRepository.php',
    'orderLifecycle' => '/src/ServiceOrder/Service/ServiceOrderLifecycleService.php',
] as $key => $path) {
    $source = file_get_contents($root . $path);
    masterDataDeletionAssert(is_string($source), 'Arquivo de exclusão deve ser legível: ' . $path);
    $sources[$key] = $source;
}

foreach ([
    ['clientPage', 'cliente.excluir', 'actions/cliente-excluir.php'],
    ['budgetPage', 'orcamento.excluir', 'actions/orcamento-excluir.php'],
    ['servicePage', 'servico.excluir', 'actions/servico-excluir.php'],
] as [$page, $permission, $action]) {
    masterDataDeletionAssert(str_contains($sources[$page], "can('" . $permission . "')"), 'Interface deve respeitar ' . $permission . '.');
    masterDataDeletionAssert(str_contains($sources[$page], $action), 'Interface deve enviar exclusão para ' . $action . '.');
    masterDataDeletionAssert(str_contains($sources[$page], 'table-action-dropdown'), 'Exclusão deve permanecer no menu global de ações da tabela.');
}

foreach ([
    ['clientAction', "client_action_context('cliente.excluir')", 'client_require_post_request()'],
    ['budgetAction', "budget_action_context('orcamento.excluir')", 'budget_require_post_request()'],
    ['serviceAction', "service_action_context('servico.excluir')", 'service_require_post_request()'],
] as [$action, $permissionCheck, $postCheck]) {
    masterDataDeletionAssert(str_contains($sources[$action], $permissionCheck), 'Servidor deve exigir a permissão específica de exclusão.');
    masterDataDeletionAssert(str_contains($sources[$action], $postCheck), 'Exclusão deve aceitar somente POST.');
}

masterDataDeletionAssert(str_contains($sources['clientRepository'], 'excluido_em IS NULL'), 'Clientes excluídos não devem aparecer em consultas operacionais.');
masterDataDeletionAssert(str_contains($sources['clientRepository'], 'hasActiveDocuments'), 'Cliente com documentos ativos deve ser protegido.');
masterDataDeletionAssert(str_contains($sources['budgetRepository'], 'o.excluido_em IS NULL'), 'Orçamentos excluídos não devem aparecer em consultas operacionais.');
masterDataDeletionAssert(str_contains($sources['budgetRepository'], "\$budget['status'] === 'aprovado'"), 'Orçamento aprovado deve ser protegido.');
masterDataDeletionAssert(str_contains($sources['serviceRepository'], 'excluido_em IS NULL'), 'Serviços excluídos não devem aparecer em consultas operacionais.');

masterDataDeletionAssert(!str_contains($sources['orderPage'], 'os-delete-reason'), 'Exclusão de OS não deve exibir campo de motivo.');
masterDataDeletionAssert(!str_contains($sources['orderAction'], "\$_POST['motivo']"), 'A ação de exclusão da OS não deve receber motivo.');
$softDeleteStart = strpos($sources['orderLifecycle'], 'public function softDelete(');
$lockOrderStart = strpos($sources['orderLifecycle'], 'private function lockOrder(');
masterDataDeletionAssert($softDeleteStart !== false && $lockOrderStart !== false, 'Fluxo de exclusão lógica da OS deve ser localizável.');
$orderSoftDelete = substr($sources['orderLifecycle'], $softDeleteStart, $lockOrderStart - $softDeleteStart);
masterDataDeletionAssert(!str_contains($orderSoftDelete, 'requiredReason'), 'Exclusão de OS não deve validar motivo.');
masterDataDeletionAssert(str_contains($orderSoftDelete, 'motivo_exclusao = NULL'), 'Exclusão de OS deve manter compatibilidade gravando motivo nulo.');

echo "MasterDataDeletionTest: OK\n";
