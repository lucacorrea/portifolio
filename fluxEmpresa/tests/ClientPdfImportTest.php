<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\CRM\Import\A7ClientReportMapper;
use App\CRM\Import\ClientPdfParser;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

function positioned(float $x, float $y, string $text): array
{
    return [[1, 0, 0, 1, $x, $y], $text];
}

$mapper = new A7ClientReportMapper();
$rows = $mapper->mapPage([
    positioned(31.32, 496.56, '3140'),
    positioned(62.88, 498.00, 'ABILIO JORGE'),
    positioned(334.32, 498.00, '21 DE ABRIL, 53 - SANTA HELENA'),
    positioned(578.04, 498.00, 'COARI'),
    positioned(655.32, 498.00, 'AM'),
    positioned(678.60, 498.00, '93'),
    positioned(701.04, 498.00, '99129-3999'),
], 1);

assertSameValue(1, count($rows), 'Deve mapear uma linha do relatório.');
assertSameValue('A7-3140', $rows[0]['code'], 'Deve preservar o código A7.');
assertSameValue('ABILIO JORGE', $rows[0]['name'], 'Deve mapear o nome pela coordenada.');
assertSameValue('(93) 99129-3999', $rows[0]['phone'], 'Deve combinar DDD e telefone.');
assertSameValue('21 DE ABRIL', $rows[0]['address'], 'Deve separar o logradouro.');
assertSameValue('53', $rows[0]['number'], 'Deve separar o número.');
assertSameValue('SANTA HELENA', $rows[0]['district'], 'Deve separar o bairro.');

$fixture = getenv('CLIENT_IMPORT_PDF') ?: '';
if ($fixture !== '') {
    $result = (new ClientPdfParser($mapper))->parse($fixture);
    assertSameValue(109, $result['pages'], 'O PDF de clientes deve ter 109 páginas.');
    assertSameValue(4105, count($result['rows']), 'O PDF de clientes deve conter 4.105 registros.');
    $codes = array_unique(array_column($result['rows'], 'code'));
    assertSameValue(4105, count($codes), 'Todos os códigos A7 devem ser únicos.');
    $rowsByCode = array_column($result['rows'], null, 'code');
    assertSameValue(true, isset($rowsByCode['A7-766']), 'O registro A7-766 deve existir no relatório.');
    assertSameValue(null, $rowsByCode['A7-766']['state'] ?? null, 'UF inválida deve ficar pendente, sem quebrar a importação.');
}

echo "ClientPdfImportTest: OK\n";
