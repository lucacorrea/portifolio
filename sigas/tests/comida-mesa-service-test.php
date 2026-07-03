<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Repositories\ComidaMesaRepository;
use App\Services\ComidaMesaService;

$failures = 0;

function assert_same(mixed $actual, mixed $expected, string $message): void
{
    global $failures;

    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: {$message}. Esperado: " . var_export($expected, true) . ' obtido: ' . var_export($actual, true) . PHP_EOL;
    }
}

$repository = (new ReflectionClass(ComidaMesaRepository::class))->newInstanceWithoutConstructor();
$service = new ComidaMesaService($repository);
$competence = ['id' => 1, 'mes' => 7, 'ano' => 2026];

assert_same($service->maskCpf('12345678909'), '***.***.***-**', 'mascara de CPF');
assert_same($service->formatCompetence(7, 2026), 'Julho de 2026', 'formatacao de competencia');

$status = $service->deliveryStatusForRow(['inscricao_status' => 'ativa', 'entrega_id' => null], $competence);
assert_same($status['status'], 'aguardando', 'ativa sem entrega fica aguardando');

$status = $service->deliveryStatusForRow([
    'inscricao_status' => 'ativa',
    'entrega_id' => 10,
    'entrega_status' => 'entregue',
    'entrega_data' => '2026-07-03 09:00:00',
], $competence);
assert_same($status['status'], 'recebida', 'ativa com entrega entregue fica recebida');

$status = $service->deliveryStatusForRow(['inscricao_status' => 'suspensa', 'entrega_id' => null], $competence);
assert_same($status['status'], 'bloqueada', 'suspensa fica bloqueada');

$status = $service->deliveryStatusForRow(['inscricao_status' => 'bloqueada', 'entrega_id' => null], $competence);
assert_same($status['status'], 'bloqueada', 'bloqueada fica bloqueada');

$status = $service->deliveryStatusForRow(['inscricao_status' => 'em_analise', 'entrega_id' => null], $competence);
assert_same($status['status'], 'indisponivel', 'em analise fica indisponivel');

$status = $service->deliveryStatusForRow(['inscricao_status' => 'ativa', 'entrega_id' => null], null);
assert_same($status['status'], 'sem_competencia', 'competencia ausente fica sem competencia');

$filter = $service->buildFilter([
    'program_status' => 'status_invalido',
    'delivery_status' => 'entrega_invalida',
]);
assert_same($filter->programStatus, null, 'filtro rejeita status de programa invalido');
assert_same($filter->deliveryStatus, null, 'filtro rejeita status de entrega invalido');

$filter = $service->buildFilter(['page' => '0']);
assert_same($filter->page, 1, 'paginacao nunca aceita pagina menor que 1');

echo $failures === 0 ? 'PASS comida-mesa-service-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
