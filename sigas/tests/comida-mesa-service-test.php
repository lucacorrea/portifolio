<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once __DIR__ . '/Support/ComidaMesaMemoryPdo.php';

App\Core\Autoloader::register();

use App\Repositories\ComidaMesaRepository;
use App\Services\ComidaMesaService;
use Tests\Support\ComidaMesaMemoryPdo;

$failures = 0;

function assert_same(mixed $actual, mixed $expected, string $message): void
{
    global $failures;

    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: {$message}. Esperado: " . var_export($expected, true) . ' obtido: ' . var_export($actual, true) . PHP_EOL;
    }
}

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        echo 'FAIL: ' . $message . PHP_EOL;
    }
}

$pdo = new ComidaMesaMemoryPdo([
    'competence_by_id' => ['id' => 7, 'mes' => 7, 'ano' => 2026, 'status' => 'aberta', 'inicio_entregas' => null, 'fim_entregas' => null],
    'cpf_rows' => [
        '12345678909' => [
            'pessoa_id' => 1,
            'responsavel_nome' => 'Maria CPF',
            'cpf' => '12345678909',
            'nis' => '123',
            'parentesco' => null,
            'familia_id' => 5,
            'familia_codigo' => 'FAM-000005',
            'vinculo_familiar' => 'responsavel',
            'inscricao_id' => 8,
            'inscricao_status' => 'ativa',
            'prioridade' => 'alta',
            'polo_id' => 2,
            'polo_nome' => 'Centro',
            'polo_ativo' => 1,
            'entrega_id' => 12,
            'entrega_status' => 'cancelada',
            'entrega_data' => '2026-07-03 09:00:00',
            'recebedor_nome' => 'Joao',
            'motivo_cancelamento' => 'Registro duplicado',
        ],
    ],
]);
$service = new ComidaMesaService(new ComidaMesaRepository($pdo));
$competence = ['id' => 7, 'mes' => 7, 'ano' => 2026, 'status' => 'aberta'];

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

$status = $service->deliveryStatusForRow([
    'inscricao_status' => 'ativa',
    'entrega_id' => 11,
    'entrega_status' => 'cancelada',
    'entrega_data' => '2026-07-03 09:00:00',
], $competence);
assert_same($status['status'], 'cancelada', 'entrega cancelada tem status proprio');

assert_same($service->deliveryStatusForRow(['inscricao_status' => 'suspensa', 'entrega_id' => null], $competence)['status'], 'bloqueada', 'suspensa fica bloqueada');
assert_same($service->deliveryStatusForRow(['inscricao_status' => 'bloqueada', 'entrega_id' => null], $competence)['status'], 'bloqueada', 'bloqueada fica bloqueada');
assert_same($service->deliveryStatusForRow(['inscricao_status' => 'em_analise', 'entrega_id' => null], $competence)['status'], 'indisponivel', 'em analise fica indisponivel');
assert_same($service->deliveryStatusForRow(['inscricao_status' => 'ativa', 'entrega_id' => null], null)['status'], 'sem_competencia', 'competencia ausente fica sem competencia');

$filter = $service->buildFilter([
    'program_status' => 'status_invalido',
    'delivery_status' => 'entrega_invalida',
]);
assert_same($filter->programStatus, null, 'filtro rejeita status de programa invalido');
assert_same($filter->deliveryStatus, null, 'filtro rejeita status de entrega invalido');
assert_same($service->buildFilter(['page' => '0'])->page, 1, 'paginacao nunca aceita pagina menor que 1');

$active = ['status' => 'ativa', 'polo_id' => 2, 'polo_ativo' => 1];
assert_same($service->deliveryEligibility($active, $competence, null)['action'], 'register', 'ativa sem entrega registra');
assert_same($service->deliveryEligibility($active, $competence, ['status' => 'entregue'])['action'], 'cancel', 'entrega realizada habilita cancelamento');
assert_same($service->deliveryEligibility($active, $competence, ['status' => 'cancelada'])['action'], 'reactivate', 'entrega cancelada habilita reativacao');
assert_same($service->deliveryEligibility(['status' => 'em_analise', 'polo_id' => 2], $competence, null)['allowed'], false, 'inscricao em analise bloqueia entrega');
assert_same($service->deliveryEligibility($active, ['id' => 7, 'status' => 'encerrada'], null)['allowed'], false, 'competencia encerrada bloqueia entrega');
assert_same($service->deliveryEligibility(['status' => 'ativa', 'polo_id' => null], $competence, null)['reason'], 'Inscrição sem polo definido.', 'inscricao ativa sem polo e inelegivel');

$consult = $service->consultCpf('123.456.789-09', 7);
$cpfQuery = $pdo->latestStatementContaining('where p.cpf = :cpf');
assert_same($consult['state'], 'inscrito', 'consulta CPF localiza inscricao');
assert_same($consult['person']['cpf_masked'], '***.***.***-**', 'consulta CPF mascara documento na resposta');
assert_same($consult['person']['vinculo_familiar'], 'responsavel', 'consulta CPF preserva vinculo familiar priorizado');
assert_same($consult['delivery']['status'], 'cancelada', 'consulta CPF exibe entrega cancelada');
assert_same($consult['eligibility']['action'], 'reactivate', 'consulta CPF permite reativar entrega cancelada');
assert_same($cpfQuery?->boundValue(':cpf'), '12345678909', 'consulta CPF usa apenas digitos no reposititorio');

echo $failures === 0 ? 'PASS comida-mesa-service-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
