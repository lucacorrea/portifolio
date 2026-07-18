<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Purchasing/Service/SupplierManagementService.php';
require dirname(__DIR__) . '/src/Finance/Service/AccountsPayableManagementService.php';

use App\Finance\Service\AccountsPayableManagementService;
use App\Purchasing\Service\SupplierManagementService;

function payablesAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

function payablesAssertThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$supplierReflection = new ReflectionClass(SupplierManagementService::class);
$supplier = $supplierReflection->newInstanceWithoutConstructor();
$supplierPayload = $supplierReflection->getMethod('payload');
$supplierPayload->setAccessible(true);

$supplierData = $supplierPayload->invoke($supplier, [
    'tipo_pessoa' => 'juridica',
    'nome' => 'Fornecedor Teste',
    'documento' => '11.222.333/0001-81',
    'email' => 'financeiro@example.com',
    'estado' => 'am',
]);
payablesAssertSame('11222333000181', $supplierData['documento'], 'O CNPJ deve ser normalizado para dígitos.');
payablesAssertSame('AM', $supplierData['estado'], 'A UF deve ser normalizada.');
payablesAssertThrows(
    static fn() => $supplierPayload->invoke($supplier, ['tipo_pessoa' => 'juridica', 'nome' => '<b>Fornecedor</b>']),
    'HTML não pode ser aceito no cadastro de fornecedor.'
);
payablesAssertThrows(
    static fn() => $supplierPayload->invoke($supplier, ['tipo_pessoa' => 'juridica', 'nome' => 'Fornecedor', 'email' => 'invalido']),
    'E-mail inválido deve ser rejeitado.'
);
payablesAssertThrows(
    static fn() => $supplierPayload->invoke($supplier, ['tipo_pessoa' => 'fisica', 'nome' => 'Fornecedor', 'documento' => '111.111.111-11']),
    'CPF com dígitos repetidos deve ser rejeitado.'
);

$accountReflection = new ReflectionClass(AccountsPayableManagementService::class);
$account = $accountReflection->newInstanceWithoutConstructor();
$accountPayload = $accountReflection->getMethod('payload');
$accountPayload->setAccessible(true);

$accountData = $accountPayload->invoke($account, [
    'fornecedor_id' => '7',
    'descricao' => 'Compra de material',
    'data_emissao' => '2026-07-18',
    'vencimento_em' => '2026-07-25',
    'valor' => '1.234,56',
]);
payablesAssertSame('1234.56', $accountData['valor'], 'O valor deve ser normalizado sem usar float.');
payablesAssertThrows(
    static fn() => $accountPayload->invoke($account, [
        'fornecedor_id' => '7', 'descricao' => 'Conta', 'vencimento_em' => '2026-07-25', 'valor' => '0',
    ]),
    'Conta com valor zero deve ser rejeitada.'
);
payablesAssertThrows(
    static fn() => $accountPayload->invoke($account, [
        'fornecedor_id' => '7', 'descricao' => 'Conta', 'data_emissao' => '2026-07-26',
        'vencimento_em' => '2026-07-25', 'valor' => '10,00',
    ]),
    'Vencimento anterior à emissão deve ser rejeitado.'
);
payablesAssertThrows(
    static fn() => $accountPayload->invoke($account, [
        'fornecedor_id' => '7', 'descricao' => 'Conta', 'vencimento_em' => '0001-01-01', 'valor' => '10,00',
    ]),
    'Datas fora do intervalo do MariaDB devem ser rejeitadas.'
);

echo "PayablesValidationTest: OK\n";
