<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once __DIR__ . '/Support/ComidaMesaMemoryPdo.php';

App\Core\Autoloader::register();

use App\DTO\ComidaMesaFilter;
use App\Repositories\ComidaMesaRepository;
use Tests\Support\ComidaMesaMemoryPdo;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        echo 'FAIL: ' . $message . PHP_EOL;
    }
}

function assert_same(mixed $actual, mixed $expected, string $message): void
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: {$message}. Esperado: " . var_export($expected, true) . ' obtido: ' . var_export($actual, true) . PHP_EOL;
    }
}

$pdo = new ComidaMesaMemoryPdo([
    'paginate_total' => 45,
    'paginate_rows' => [['inscricao_id' => 10, 'responsavel_nome' => 'Maria']],
    'cpf_rows' => [
        '12345678909' => [
            'pessoa_id' => 1,
            'responsavel_nome' => 'Maria CPF',
            'cpf' => '12345678909',
            'nis' => null,
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
            'entrega_id' => null,
            'entrega_status' => null,
            'entrega_data' => null,
            'recebedor_nome' => null,
            'motivo_cancelamento' => null,
        ],
    ],
    'locked_competence' => ['id' => 3, 'status' => 'aberta'],
    'locked_registration' => ['id' => 8, 'status' => 'ativa', 'polo_id' => 2, 'polo_ativo' => 1],
    'locked_delivery' => ['id' => 4, 'status' => 'entregue'],
]);
$repository = new ComidaMesaRepository($pdo);

$result = $repository->paginate(new ComidaMesaFilter('Maria 123.456.789-09', 7, null, null, null, null, null, null, 2));
$paginate = $pdo->latestStatementContaining('limit :limit offset :offset');

assert_same($result->getTotal(), 45, 'paginate usa total retornado pelo banco');
assert_true($paginate !== null, 'paginate executa consulta com LIMIT/OFFSET');
assert_true(isset($paginate->bindings[':search_name'], $paginate->bindings[':search_nis'], $paginate->bindings[':search_code'], $paginate->bindings[':search_cpf']), 'busca usa placeholders distintos');
assert_true(!isset($paginate->bindings[':search']), 'busca nao reutiliza placeholder generico');
assert_same($paginate->boundType(':limit'), \PDO::PARAM_INT, 'limit e vinculado como inteiro');
assert_same($paginate->boundType(':offset'), \PDO::PARAM_INT, 'offset e vinculado como inteiro');
assert_same($paginate->boundValue(':offset'), 20, 'offset da pagina 2 usa perPage real');

$repository->paginate(new ComidaMesaFilter('Maria Silva', null));
$nameOnly = $pdo->latestStatementContaining('limit :limit offset :offset');
assert_true($nameOnly !== null && !isset($nameOnly->bindings[':search_cpf']), 'busca textual sem digitos nao cria filtro de CPF');

$repository->paginate(new ComidaMesaFilter(null, null, null, 'recebida'));
$withoutCompetence = $pdo->latestStatementContaining('left join comida_mesa_entregas entrega on 1 = 0');
assert_true($withoutCompetence !== null, 'filtro de entrega sem competencia usa join neutro');
assert_true(str_contains(strtolower($withoutCompetence->sql), '1 = 0'), 'filtro recebida sem competencia nao retorna linhas');
assert_true(!isset($withoutCompetence->bindings[':entrega_competencia_id']), 'filtro sem competencia nao vincula competencia inexistente');

$cpfRow = $repository->findByCpf('(123) 456-78909', 7);
$cpfQuery = $pdo->latestStatementContaining('where p.cpf = :cpf');
assert_true($cpfRow !== null, 'findByCpf retorna pessoa cadastrada');
assert_same($cpfQuery?->boundValue(':cpf'), '12345678909', 'findByCpf normaliza CPF antes da consulta');
assert_same($cpfQuery?->boundType(':entrega_competencia_id'), \PDO::PARAM_INT, 'competencia da busca CPF e vinculada como inteiro');
assert_true(str_contains($cpfQuery?->sql ?? '', "CASE WHEN fam.responsavel_pessoa_id = p.id THEN 1 WHEN fam.status = 'ativo' THEN 2 ELSE 3 END"), 'busca CPF prioriza familia onde a pessoa e responsavel');
assert_true(str_contains($cpfQuery?->sql ?? '', 'fmx.criado_em DESC, fam.id DESC'), 'busca CPF desempata integrante por vinculo mais recente');

$repository->lockCompetenceForDelivery(3);
$repository->lockRegistrationForDelivery(8);
$repository->lockDelivery(8, 3);

assert_true(str_contains(strtolower($pdo->latestStatementContaining('from comida_mesa_competencias')?->sql ?? ''), 'for update'), 'competencia de entrega usa FOR UPDATE');
assert_true(str_contains(strtolower($pdo->latestStatementContaining('from comida_mesa_inscricoes i')?->sql ?? ''), 'for update'), 'inscricao de entrega usa FOR UPDATE');
assert_true(str_contains(strtolower($pdo->latestStatementContaining('from comida_mesa_entregas')?->sql ?? ''), 'for update'), 'entrega mensal usa FOR UPDATE');

$repository->cancelDelivery(4, 99, '2026-07-03 10:00:00', 'Cancelamento operacional');
$allSql = implode("\n", $pdo->executedSql());
assert_true(!preg_match('/\bDELETE\s+FROM\s+comida_mesa_entregas\b/i', $allSql), 'cancelamento nao apaga entregas');

echo $failures === 0 ? 'PASS comida-mesa-repository-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
