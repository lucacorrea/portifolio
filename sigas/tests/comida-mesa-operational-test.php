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

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        echo 'FAIL: ' . $message . PHP_EOL;
    }
}

$repository = (new ReflectionClass(ComidaMesaRepository::class))->newInstanceWithoutConstructor();
$service = new ComidaMesaService($repository);
$openCompetence = ['id' => 1, 'status' => 'aberta'];
$closedCompetence = ['id' => 1, 'status' => 'encerrada'];
$active = ['status' => 'ativa', 'polo_id' => 1, 'polo_ativo' => 1];

assert_same($service->deliveryEligibility($active, $openCompetence, null)['action'], 'register', 'inscricao ativa sem entrega registra');
assert_same($service->deliveryEligibility(['status' => 'em_analise', 'polo_id' => 1], $openCompetence, null)['allowed'], false, 'inscricao em analise bloqueia entrega');
assert_same($service->deliveryEligibility($active, $closedCompetence, null)['allowed'], false, 'competencia encerrada bloqueia entrega');
assert_same($service->deliveryEligibility($active, $openCompetence, ['status' => 'entregue'])['action'], 'cancel', 'entrega realizada habilita cancelamento');
assert_same($service->deliveryEligibility($active, $openCompetence, ['status' => 'cancelada'])['action'], 'reactivate', 'entrega cancelada habilita reativacao');

$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ComidaMesaService.php') ?: '';
$viewerSource = file_get_contents(dirname(__DIR__) . '/api/comida-mesa/visualizar-documento.php') ?: '';
$consultaSource = file_get_contents(dirname(__DIR__) . '/consulta-documento.php') ?: '';

assert_true(str_contains($serviceSource, 'unset($row[\'cpf\'])'), 'detail remove cpf interno');
assert_true(str_contains($serviceSource, 'cpf_completo'), 'detail retorna cpf_completo apenas no caminho de edicao');
assert_true(str_contains($viewerSource, 'filename*=UTF-8'), 'visualizador usa filename UTF-8');
assert_true(str_contains($viewerSource, 'preg_replace'), 'visualizador sanitiza nome original');
assert_true(str_contains($consultaSource, '$authService->requireUser()'), 'consulta-documento exige autenticacao');
assert_true(str_contains($consultaSource, 'comida_mesa.consultar_cpf'), 'consulta-documento exige permissao de consulta');
assert_true(!str_contains($consultaSource, 'integration-demo.js'), 'consulta funcional nao carrega integration-demo');
assert_true(!str_contains($consultaSource, 'demoScenario'), 'consulta funcional nao possui seletor demo');

echo $failures === 0 ? 'PASS comida-mesa-operational-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
