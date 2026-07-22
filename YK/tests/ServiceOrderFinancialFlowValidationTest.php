<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Finance/Service/AccountsReceivableManagementService.php';
require dirname(__DIR__) . '/src/Finance/Service/ReceiptService.php';
require dirname(__DIR__) . '/src/ServiceOrder/Service/ServiceOrderFinalizationService.php';

use App\Finance\Service\AccountsReceivableManagementService;
use App\Finance\Service\ReceiptService;
use App\ServiceOrder\Service\ServiceOrderFinalizationService;

function financialFlowAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

function financialFlowAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function financialFlowAssertThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$accountsReflection = new ReflectionClass(AccountsReceivableManagementService::class);
$accounts = $accountsReflection->newInstanceWithoutConstructor();
$paymentToken = $accountsReflection->getMethod('paymentToken');
$paymentToken->setAccessible(true);
$moneyToCents = $accountsReflection->getMethod('moneyToCents');
$moneyToCents->setAccessible(true);

$token = '9d0bf2e4-9c4f-4ed7-a7d9-11f1782c2374';
financialFlowAssertSame($token, $paymentToken->invoke($accounts, $token), 'Token UUID deve ser aceito sem alteração.');
financialFlowAssertThrows(
    static fn() => $paymentToken->invoke($accounts, 'curto'),
    'Token curto não pode proteger uma operação financeira.'
);
financialFlowAssertThrows(
    static fn() => $paymentToken->invoke($accounts, '<script>identificador-invalido</script>'),
    'Token com caracteres fora do contrato deve ser rejeitado.'
);
financialFlowAssertSame(123456, $moneyToCents->invoke($accounts, '1.234,56'), 'Pagamento deve operar em centavos.');

$receiptReflection = new ReflectionClass(ReceiptService::class);
$receipt = $receiptReflection->newInstanceWithoutConstructor();
$receiptMoney = $receiptReflection->getMethod('money');
$receiptMoney->setAccessible(true);
$receiptForm = $receiptReflection->getMethod('paymentForm');
$receiptForm->setAccessible(true);
financialFlowAssertSame('1234.56', $receiptMoney->invoke($receipt, '1.234,56'), 'Recibo avulso deve normalizar valor sem float.');
financialFlowAssertSame('pix', $receiptForm->invoke($receipt, 'pix'), 'Forma de pagamento conhecida deve ser aceita.');
financialFlowAssertThrows(static fn() => $receiptMoney->invoke($receipt, '0'), 'Recibo de valor zero deve ser rejeitado.');
financialFlowAssertThrows(static fn() => $receiptForm->invoke($receipt, 'cripto'), 'Forma desconhecida deve ser rejeitada.');

$finalizationReflection = new ReflectionClass(ServiceOrderFinalizationService::class);
$finalization = $finalizationReflection->newInstanceWithoutConstructor();
$executionItems = $finalizationReflection->getMethod('executionItems');
$executionItems->setAccessible(true);
$items = $executionItems->invoke($finalization, [
    'execution_items' => [[
        'type' => 'servico',
        'description' => 'Manutenção preventiva',
        'quantity' => '2',
        'unit_price' => '150,00',
        'discount' => '10,00',
    ]],
]);
financialFlowAssertSame('servico', $items[0]['type'], 'Finalização deve aceitar item de serviço válido.');
financialFlowAssertSame(150.0, $items[0]['unit_price'], 'Valor executado deve ser normalizado no servidor.');
financialFlowAssertThrows(
    static fn() => $executionItems->invoke($finalization, ['execution_items' => [[
        'type' => 'invalido', 'description' => 'Item', 'quantity' => '1', 'unit_price' => '10',
    ]]]),
    'Tipo de execução inválido deve ser rejeitado.'
);

$finalizationAction = file_get_contents(dirname(__DIR__) . '/actions/os-finalizar.php');
$paymentAction = file_get_contents(dirname(__DIR__) . '/actions/os-pagar.php');
$standaloneReceiptAction = file_get_contents(dirname(__DIR__) . '/actions/recibo-avulso-emitir.php');
$lifecycleSource = file_get_contents(dirname(__DIR__) . '/src/ServiceOrder/Service/ServiceOrderLifecycleService.php');
$receiptSource = file_get_contents(dirname(__DIR__) . '/src/Finance/Service/ReceiptService.php');
$accountsSource = file_get_contents(dirname(__DIR__) . '/src/Finance/Service/AccountsReceivableManagementService.php');
$orderRepositorySource = file_get_contents(dirname(__DIR__) . '/src/ServiceOrder/Repository/ServiceOrderRepository.php');
financialFlowAssert(is_string($finalizationAction), 'Action de finalização deve ser legível.');
financialFlowAssert(!str_contains((string) $finalizationAction, "requirePermission('os.finalizar_com_pagamento')"), 'Finalização não pode exigir permissão de pagamento.');
financialFlowAssert(str_contains((string) $paymentAction, "os_action_context('contas_receber.registrar_pagamento')"), 'Pagamento de OS deve exigir permissão financeira.');
financialFlowAssert(str_contains((string) $paymentAction, "can('recibo.emitir')"), 'Pagamento com recibo deve exigir permissão de emissão.');
financialFlowAssert(str_contains((string) $paymentAction, "'payment_token'"), 'Action de pagamento deve receber token idempotente.');
financialFlowAssert(str_contains((string) $standaloneReceiptAction, "os_action_context('recibo.emitir')"), 'Recibo avulso deve exigir autorização própria.');
financialFlowAssert(str_contains((string) $lifecycleSource, 'total_origem'), 'Estorno deve restaurar o total anterior da OS quando houver snapshot.');
financialFlowAssert(str_contains((string) $lifecycleSource, 'reversePaymentsAndCash'), 'Estorno deve preservar a compensação financeira e de Caixa.');
financialFlowAssert(!str_contains((string) $receiptSource, 'LIKE :search OR'), 'Busca de recibos não pode reutilizar placeholders com prepared statements nativos.');
financialFlowAssert(!str_contains((string) $accountsSource, 'LIKE :search OR'), 'Busca de contas a receber não pode reutilizar placeholders com prepared statements nativos.');
financialFlowAssert(
    str_contains((string) $orderRepositorySource, '$this->bindForm($statement, $data, $totals, false);'),
    'Edição de OS não pode vincular o parâmetro :status ausente do UPDATE.'
);
financialFlowAssert(
    str_contains((string) $orderRepositorySource, 'if ($includeStatus)'),
    'Binding compartilhado deve incluir :status somente nas queries que possuem esse placeholder.'
);

echo "ServiceOrderFinancialFlowValidationTest: OK\n";
