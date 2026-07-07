<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once __DIR__ . '/Support/ComidaMesaMemoryPdo.php';

App\Core\Autoloader::register();

use App\Repositories\ComidaMesaRepository;
use App\Repositories\AuditLogRepository;
use App\Services\AuditService;
use App\Services\ComidaMesaService;
use App\DTO\ComidaMesaCadastroData;
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

function registration_data(array $overrides = []): ComidaMesaCadastroData
{
    return ComidaMesaCadastroData::fromArray($overrides + [
        'inscricao_id' => '8',
        'versao_atualizacao' => '2026-07-01 08:00:00',
        'nome' => 'Maria CPF',
        'cpf' => '12345678909',
        'telefone' => '92999990000',
        'zona' => 'urbana',
        'logradouro' => 'Rua A',
        'numero' => '100',
        'bairro' => 'Centro',
        'quantidade_membros' => '3',
        'status' => 'em_analise',
        'prioridade' => 'normal',
        'data_inscricao' => '2026-07-01',
        'observacao' => 'Cadastro revisado',
    ]);
}

function registration_row(array $overrides = []): array
{
    return $overrides + [
        'id' => 8,
        'familia_id' => 5,
        'familia_codigo' => 'FAM-000005',
        'responsavel_pessoa_id' => 1,
        'zona' => 'urbana',
        'logradouro' => 'Rua A',
        'numero' => '100',
        'complemento' => null,
        'bairro' => 'Centro',
        'comunidade' => null,
        'ponto_referencia' => null,
        'cep' => null,
        'quantidade_membros' => 3,
        'renda_familiar' => null,
        'nome' => 'Maria CPF',
        'cpf' => '12345678909',
        'nis' => null,
        'rg' => null,
        'data_nascimento' => null,
        'telefone' => '92999990000',
        'email' => null,
        'polo_nome' => null,
        'status' => 'em_analise',
        'prioridade' => 'normal',
        'data_inscricao' => '2026-07-01',
        'data_aprovacao' => null,
        'aprovado_por' => null,
        'criado_em' => '2026-07-01 08:00:00',
        'atualizado_em' => null,
        'versao_atualizacao' => '2026-07-01 08:00:00',
        'polo_id' => null,
        'observacao' => null,
        'motivo_suspensao' => null,
    ];
}

final class RegistrationEditMemoryPdo extends PDO
{
    /** @var list<RegistrationEditMemoryStatement> */
    public array $statements = [];
    private bool $transaction = false;

    /** @param array<string,mixed> $fixtures */
    public function __construct(private readonly array $fixtures = [])
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = new RegistrationEditMemoryStatement($this, $query);
        $this->statements[] = $statement;

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string
    {
        return '9001';
    }

    public function latestStatementContaining(string $needle): ?RegistrationEditMemoryStatement
    {
        $needle = strtolower($needle);

        for ($index = count($this->statements) - 1; $index >= 0; $index--) {
            if (str_contains(strtolower($this->statements[$index]->sql), $needle)) {
                return $this->statements[$index];
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function rowsFor(RegistrationEditMemoryStatement $statement): array
    {
        $sql = strtolower(preg_replace('/\s+/', ' ', trim($statement->sql)) ?? $statement->sql);

        if (str_contains($sql, 'from comida_mesa_inscricoes') && str_contains($sql, 'for update')) {
            return isset($this->fixtures['locked_registration']) ? [$this->fixtures['locked_registration']] : [];
        }

        if (str_contains($sql, 'from comida_mesa_inscricoes i') && str_contains($sql, 'inner join familias f')) {
            return isset($this->fixtures['detail_registration']) ? [$this->fixtures['detail_registration']] : [];
        }

        if (str_contains($sql, 'select * from pessoas where cpf = :cpf')) {
            return isset($this->fixtures['person_by_cpf']) ? [$this->fixtures['person_by_cpf']] : [];
        }

        if (str_contains($sql, 'from familias f') && str_contains($sql, 'left join familia_membros fm')) {
            return isset($this->fixtures['family_link']) ? [$this->fixtures['family_link']] : [];
        }

        if (str_contains($sql, 'select * from comida_mesa_inscricoes where familia_id = :familia_id')) {
            return isset($this->fixtures['registration_by_family']) ? [$this->fixtures['registration_by_family']] : [];
        }

        return [];
    }
}

final class RegistrationEditMemoryStatement extends PDOStatement
{
    /** @var array<string,array{value:mixed,type:int}> */
    public array $bindings = [];
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private int $cursor = 0;

    public function __construct(
        private readonly RegistrationEditMemoryPdo $pdo,
        public readonly string $sql,
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$this->normalizeParam($param)] = ['value' => $value, 'type' => $type];

        return true;
    }

    public function execute(?array $params = null): bool
    {
        foreach ($params ?? [] as $key => $value) {
            $this->bindValue(is_int($key) ? $key : ':' . ltrim((string) $key, ':'), $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $this->rows = $this->pdo->rowsFor($this);
        $this->cursor = 0;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->cursor++] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? [];

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        return reset($row);
    }

    private function normalizeParam(string|int $param): string
    {
        return is_int($param) ? (string) $param : ':' . ltrim($param, ':');
    }
}

function service_with_fixtures(array $fixtures): array
{
    $row = $fixtures['detail_registration'] ?? $fixtures['locked_registration'] ?? registration_row();
    $fixtures += [
        'person_by_cpf' => [
            'id' => 1,
            'nome' => $row['nome'] ?? 'Maria CPF',
            'cpf' => $row['cpf'] ?? '12345678909',
        ],
        'family_link' => [
            'id' => $row['familia_id'] ?? 5,
            'codigo' => $row['familia_codigo'] ?? 'FAM-000005',
            'responsavel_pessoa_id' => 1,
            'vinculo_familiar' => 'responsavel',
        ],
        'registration_by_family' => [
            'id' => $row['id'] ?? 8,
            'familia_id' => $row['familia_id'] ?? 5,
        ],
    ];
    $pdo = new RegistrationEditMemoryPdo($fixtures);

    return [
        new ComidaMesaService(new ComidaMesaRepository($pdo)),
        new AuditService(new AuditLogRepository($pdo)),
        $pdo,
    ];
}

function assert_exception_code(callable $callback, int $expectedCode, string $message): void
{
    global $failures;

    try {
        $callback();
        $failures++;
        echo "FAIL: {$message}. Nenhuma excecao foi lancada." . PHP_EOL;
    } catch (RuntimeException $exception) {
        if ($exception->getCode() !== $expectedCode) {
            $failures++;
            echo "FAIL: {$message}. Esperado codigo {$expectedCode} obtido {$exception->getCode()}" . PHP_EOL;
        }
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
assert_same($service->formatCpf('12345678909'), '123.456.789-09', 'formatacao completa de CPF');
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
assert_same($consult['cpf_formatado'], '123.456.789-09', 'consulta CPF retorna documento formatado completo');
assert_same($consult['person']['cpf_formatado'], '123.456.789-09', 'consulta CPF retorna pessoa com documento formatado completo');
assert_same($consult['person']['cpf_masked'], '***.***.***-**', 'consulta CPF mascara documento na resposta');
assert_same($consult['person']['vinculo_familiar'], 'responsavel', 'consulta CPF preserva vinculo familiar priorizado');
assert_same($consult['delivery']['status'], 'cancelada', 'consulta CPF exibe entrega cancelada');
assert_same($consult['eligibility']['action'], 'reactivate', 'consulta CPF permite reativar entrega cancelada');
assert_same($cpfQuery?->boundValue(':cpf'), '12345678909', 'consulta CPF usa apenas digitos no reposititorio');

[$firstEditService, $firstEditAudit, $firstEditPdo] = service_with_fixtures([
    'locked_registration' => registration_row([
        'criado_em' => '2026-07-01 08:00:00',
        'atualizado_em' => null,
        'versao_atualizacao' => '2026-07-01 08:00:00',
    ]),
    'detail_registration' => registration_row([
        'criado_em' => '2026-07-01 08:00:00',
        'atualizado_em' => null,
        'versao_atualizacao' => '2026-07-01 08:00:00',
    ]),
]);
$firstEdit = $firstEditService->saveRegistration(registration_data(), 99, $firstEditAudit);
$firstEditLock = $firstEditPdo->latestStatementContaining('for update');
assert_same($firstEdit['created'], false, 'primeira edicao de inscricao existente nao cria novo cadastro');
assert_true(str_contains(strtolower($firstEditLock?->sql ?? ''), 'coalesce(atualizado_em, criado_em) as versao_atualizacao'), 'bloqueio de edicao calcula versao por atualizado_em ou criado_em');
assert_true($firstEditPdo->latestStatementContaining('update comida_mesa_inscricoes set') !== null, 'primeira edicao com versao baseada em criado_em e aceita');

[$laterEditService, $laterEditAudit, $laterEditPdo] = service_with_fixtures([
    'locked_registration' => registration_row([
        'atualizado_em' => '2026-07-03 10:00:00',
        'versao_atualizacao' => '2026-07-03 10:00:00',
    ]),
    'detail_registration' => registration_row([
        'atualizado_em' => '2026-07-03 10:00:00',
        'versao_atualizacao' => '2026-07-03 10:00:00',
    ]),
]);
$laterEdit = $laterEditService->saveRegistration(registration_data(['versao_atualizacao' => '2026-07-03 10:00:00']), 99, $laterEditAudit);
assert_same($laterEdit['created'], false, 'edicao posterior aceita a versao baseada em atualizado_em');
assert_true($laterEditPdo->latestStatementContaining('update comida_mesa_inscricoes set') !== null, 'edicao posterior executa update quando versao atual confere');

[$conflictService, $conflictAudit] = service_with_fixtures([
    'locked_registration' => registration_row([
        'atualizado_em' => '2026-07-03 10:00:00',
        'versao_atualizacao' => '2026-07-03 10:00:00',
    ]),
    'detail_registration' => registration_row([
        'atualizado_em' => '2026-07-03 10:00:00',
        'versao_atualizacao' => '2026-07-03 10:00:00',
    ]),
]);
assert_exception_code(
    fn () => $conflictService->saveRegistration(registration_data(['versao_atualizacao' => '2026-07-01 08:00:00']), 99, $conflictAudit),
    409,
    'versao divergente retorna conflito 409'
);

echo $failures === 0 ? 'PASS comida-mesa-service-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
