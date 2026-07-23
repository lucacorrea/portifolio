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
$accountsPaymentForm = $accountsReflection->getMethod('paymentForm');
$accountsPaymentForm->setAccessible(true);
$installmentCount = $accountsReflection->getMethod('installmentCount');
$installmentCount->setAccessible(true);

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
financialFlowAssertSame('boleto', $accountsPaymentForm->invoke($accounts, 'boleto'), 'Boleto compensado deve ser aceito no pagamento da OS.');
financialFlowAssertSame(12, $installmentCount->invoke($accounts, '12', 'cartao_credito'), 'Cartão de crédito deve aceitar parcelas válidas.');
financialFlowAssertSame(3, $installmentCount->invoke($accounts, 3, 'boleto'), 'Boleto compensado deve preservar a quantidade de parcelas.');
financialFlowAssertSame(1, $installmentCount->invoke($accounts, 1, 'pix'), 'Pagamento sem parcelamento deve possuir uma parcela.');
financialFlowAssertThrows(static fn() => $installmentCount->invoke($accounts, 2, 'pix'), 'Pix não pode persistir parcelamento inconsistente.');
financialFlowAssertThrows(static fn() => $installmentCount->invoke($accounts, 61, 'cartao_credito'), 'Parcelamento acima do limite deve ser rejeitado.');

$receiptReflection = new ReflectionClass(ReceiptService::class);
$receipt = $receiptReflection->newInstanceWithoutConstructor();
$receiptMoney = $receiptReflection->getMethod('money');
$receiptMoney->setAccessible(true);
$receiptForm = $receiptReflection->getMethod('paymentForm');
$receiptForm->setAccessible(true);
financialFlowAssertSame('1234.56', $receiptMoney->invoke($receipt, '1.234,56'), 'Recibo avulso deve normalizar valor sem float.');
financialFlowAssertSame('pix', $receiptForm->invoke($receipt, 'pix'), 'Forma de pagamento conhecida deve ser aceita.');
financialFlowAssertSame('boleto', $receiptForm->invoke($receipt, 'boleto'), 'Recibo deve reconhecer boleto compensado.');
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
$orderManagementSource = file_get_contents(dirname(__DIR__) . '/src/ServiceOrder/Service/ServiceOrderManagementService.php');
$paymentPageSource = file_get_contents(dirname(__DIR__) . '/pages/ordens-servico.php');
$paymentScriptSource = file_get_contents(dirname(__DIR__) . '/assets/js/ordens-servico-pagamento.js');
financialFlowAssert(is_string($finalizationAction), 'Action de finalização deve ser legível.');
financialFlowAssert(!str_contains((string) $finalizationAction, "requirePermission('os.finalizar_com_pagamento')"), 'Finalização não pode exigir permissão de pagamento.');
financialFlowAssert(str_contains((string) $finalizationAction, 'os_store_post_completion_payment_prompt'), 'Conclusão confirmada deve preparar a pergunta de pagamento posterior.');
financialFlowAssert(str_contains((string) $finalizationAction, "['modal' => null]"), 'Sucesso na conclusão deve remover recovery antigo antes de abrir o pagamento.');
financialFlowAssert(str_contains((string) $paymentAction, "os_action_context('contas_receber.registrar_pagamento')"), 'Pagamento de OS deve exigir permissão financeira.');
financialFlowAssert(str_contains((string) $paymentAction, "can('recibo.emitir')"), 'Pagamento com recibo deve exigir permissão de emissão.');
financialFlowAssert(str_contains((string) $paymentAction, "'payment_token'"), 'Action de pagamento deve receber token idempotente.');
financialFlowAssert(str_contains((string) $paymentAction, "'quantidade_parcelas'"), 'Action de pagamento deve receber a quantidade de parcelas.');
financialFlowAssert(str_contains((string) $paymentAction, "os_store_form_recovery('pay'"), 'Erro no pagamento deve preservar os dados para nova tentativa segura.');
financialFlowAssert(str_contains((string) $paymentPageSource, 'O cliente já pagou este serviço?'), 'A página deve perguntar se a OS concluída foi paga.');
financialFlowAssert(str_contains((string) $paymentPageSource, 'name="quantidade_parcelas"'), 'A modal deve possuir campo acessível de parcelas.');
financialFlowAssert(str_contains((string) $paymentPageSource, 'Boleto já compensado'), 'A interface não pode confundir boleto emitido com boleto pago.');
financialFlowAssert(str_contains((string) $paymentPageSource, 'id="os-pay-leave-pending" type="button" data-bs-dismiss="modal">Não, deixar pendente'), 'Não pago deve fechar a modal sem POST financeiro.');
financialFlowAssert(str_contains((string) $paymentPageSource, 'aria-labelledby="os-pay-title"'), 'A modal de pagamento deve possuir nome acessível.');
financialFlowAssert(str_contains((string) $paymentScriptSource, "method.value === 'boleto' || method.value === 'cartao_credito'"), 'Parcelas devem aparecer somente para boleto e cartão de crédito.');
financialFlowAssert(str_contains((string) $paymentScriptSource, 'clearModalQuery();'), 'Recuperação do pagamento deve ser consumida sem reabrir uma modal vazia no refresh.');
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

$manualCreateStart = strpos((string) $orderManagementSource, 'public function createOrder(');
$budgetCreateStart = strpos((string) $orderManagementSource, 'public function createOrderFromApprovedBudget(');
$approvalStart = strpos((string) $orderManagementSource, 'public function approveBudgetAndCreateOrder(');
financialFlowAssert($manualCreateStart !== false && $budgetCreateStart !== false && $approvalStart !== false, 'Fluxos de criação de OS devem ser localizáveis.');
$manualCreateSource = substr((string) $orderManagementSource, $manualCreateStart, $budgetCreateStart - $manualCreateStart);
$budgetCreateSource = substr((string) $orderManagementSource, $budgetCreateStart, $approvalStart - $budgetCreateStart);
financialFlowAssert(!str_contains($manualCreateSource, 'validateConflicts'), 'Nova OS manual deve aceitar sobreposição de horário da equipe.');
financialFlowAssert(!str_contains($budgetCreateSource, 'validateConflicts'), 'Nova OS de orçamento deve aceitar sobreposição de horário da equipe.');

echo "ServiceOrderFinancialFlowValidationTest: OK\n";
