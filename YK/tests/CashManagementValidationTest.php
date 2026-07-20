<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Finance/Service/CashSessionOperations.php';
require dirname(__DIR__) . '/src/Finance/Service/PointOfSaleOperations.php';
require dirname(__DIR__) . '/src/Finance/Service/CashManagementService.php';

use App\Finance\Service\CashManagementService;

function cashAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

function cashAssertThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$reflection = new ReflectionClass(CashManagementService::class);
$cash = $reflection->newInstanceWithoutConstructor();
$money = $reflection->getMethod('moneyCents');
$money->setAccessible(true);
$items = $reflection->getMethod('saleItems');
$items->setAccessible(true);

cashAssertSame(123456, $money->invoke($cash, '1.234,56'), 'O Caixa deve calcular valores em centavos.');
cashAssertSame(0, $money->invoke($cash, '0,00', true), 'Abertura e conferência podem aceitar zero.');
cashAssertThrows(static fn() => $money->invoke($cash, '-1,00'), 'Valores negativos devem ser rejeitados.');
cashAssertThrows(static fn() => $money->invoke($cash, '10,999'), 'Mais de duas casas monetárias devem ser rejeitadas.');

$cart = $items->invoke($cash, [
    ['produto_id' => '9', 'quantidade' => '1,250'],
    ['produto_id' => '3', 'quantidade' => '2'],
    ['produto_id' => '9', 'quantidade' => '0,750'],
]);
cashAssertSame(3, $cart[0]['product_id'], 'O bloqueio de produtos deve seguir ordem estável para reduzir deadlocks.');
cashAssertSame(9, $cart[1]['product_id'], 'Produtos repetidos devem ser agrupados.');
cashAssertSame(2.0, $cart[1]['quantity'], 'Quantidades repetidas devem ser somadas.');
cashAssertThrows(static fn() => $items->invoke($cash, []), 'Venda sem produtos deve ser rejeitada.');
cashAssertThrows(static fn() => $items->invoke($cash, [['produto_id' => '1', 'quantidade' => '0']]), 'Quantidade zero deve ser rejeitada.');

echo "CashManagementValidationTest: OK\n";
