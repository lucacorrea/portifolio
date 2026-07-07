<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once __DIR__ . '/Support/ComidaMesaMemoryPdo.php';

App\Core\Autoloader::register();

use App\Repositories\ComidaMesaRepository;
use App\Services\ComidaMesaService;
use App\Core\Logger;
use Tests\Support\ComidaMesaMemoryPdo;

$failures = 0;
$logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigas_comida_mesa_test_logs_' . bin2hex(random_bytes(4));
mkdir($logDir, 0700, true);
Logger::configure($logDir);

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

final class ComidaMesaDetailMemoryPdo extends PDO
{
    /** @var list<ComidaMesaDetailMemoryStatement> */
    public array $statements = [];

    /** @param array<string,mixed> $fixtures */
    public function __construct(private readonly array $fixtures = [])
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = new ComidaMesaDetailMemoryStatement($this, $query);
        $this->statements[] = $statement;

        return $statement;
    }

    /** @return list<array<string,mixed>> */
    public function rowsFor(ComidaMesaDetailMemoryStatement $statement): array
    {
        $sql = strtolower(preg_replace('/\s+/', ' ', trim($statement->sql)) ?? $statement->sql);

        if (str_contains($sql, 'from comida_mesa_inscricoes i') && str_contains($sql, 'inner join familias f')) {
            return isset($this->fixtures['detail_registration']) ? [$this->fixtures['detail_registration']] : [];
        }

        if (str_contains($sql, 'from comida_mesa_entregas e')) {
            return $this->fixtures['detail_deliveries'] ?? [];
        }

        if (str_contains($sql, 'from familia_membros fm inner join pessoas p')) {
            return $this->fixtures['detail_members'] ?? [];
        }

        if (str_contains($sql, 'from comida_mesa_documentos d')) {
            return $this->fixtures['detail_documents'] ?? [];
        }

        if (str_contains($sql, 'from comida_mesa_historico h')) {
            return $this->fixtures['detail_history'] ?? [];
        }

        return [];
    }
}

final class ComidaMesaDetailMemoryStatement extends PDOStatement
{
    /** @var array<string,array{value:mixed,type:int}> */
    public array $bindings = [];
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private int $cursor = 0;

    public function __construct(
        private readonly ComidaMesaDetailMemoryPdo $pdo,
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

    private function normalizeParam(string|int $param): string
    {
        return is_int($param) ? (string) $param : ':' . ltrim($param, ':');
    }
}

$detailRegistration = [
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
    'cep' => '69000000',
    'quantidade_membros' => 3,
    'renda_familiar' => '850.50',
    'nome' => 'Maria Responsavel',
    'cpf' => '12345678909',
    'nis' => '123',
    'rg' => null,
    'data_nascimento' => '1990-01-01',
    'telefone' => '92999990000',
    'email' => null,
    'polo_nome' => 'Centro',
    'status' => 'ativa',
    'prioridade' => 'alta',
    'data_inscricao' => '2026-07-01',
    'data_aprovacao' => '2026-07-02 08:00:00',
    'criado_em' => '2026-07-01 08:00:00',
    'atualizado_em' => '2026-07-03 08:00:00',
    'versao_atualizacao' => '2026-07-03 08:00:00',
    'polo_id' => 2,
    'observacao' => null,
    'motivo_suspensao' => null,
];

$pdo = new ComidaMesaDetailMemoryPdo([
    'detail_registration' => $detailRegistration,
    'detail_members' => [
        ['id' => 1, 'nome' => 'Maria Responsavel', 'cpf' => '12345678909', 'parentesco' => 'responsavel', 'responsavel' => 1, 'renda_mensal' => null, 'criado_em' => '2026-07-01'],
        ['id' => 2, 'nome' => 'Filho Integrante', 'cpf' => '98765432100', 'parentesco' => 'filho', 'responsavel' => 0, 'renda_mensal' => null, 'criado_em' => '2026-07-01'],
    ],
    'detail_deliveries' => [
        ['id' => 12, 'competencia_id' => 7, 'ano' => 2026, 'mes' => 7, 'status' => 'cancelada', 'entregue_em' => '2026-07-03 09:00:00', 'cancelada_em' => '2026-07-03 10:00:00'],
    ],
    'detail_documents' => [
        ['id' => 20, 'tipo' => 'cpf', 'descricao' => 'Documento CPF', 'criado_em' => '2026-07-03 08:00:00', 'enviado_por_nome' => 'Operador', 'nome_original' => 'cpf.pdf', 'mime_type' => 'application/pdf', 'tamanho' => 2048],
    ],
    'detail_history' => [
        ['id' => 30, 'acao' => 'cadastro_editado', 'descricao' => 'Historico legado', 'dados_anteriores' => '{json invalido', 'dados_novos' => '{"status":"ativa"}', 'criado_em' => '2026-07-03 11:00:00', 'usuario_nome' => 'Operador'],
    ],
]);

$service = new ComidaMesaService(new ComidaMesaRepository($pdo));

$detail = $service->detail(8, true, true, true);

assert_true(!array_key_exists('cpf', $detail), 'detalhe nunca expoe CPF bruto do responsavel');
assert_same($detail['cpf_completo'], '123.456.789-09', 'detalhe com permissao retorna CPF formatado para edicao');
assert_same($detail['cpf_mascarado'], '***.***.***-**', 'detalhe mantem CPF mascarado');
assert_true(!array_key_exists('cpf', $detail['integrantes'][0]), 'detalhe remove CPF bruto dos integrantes');
assert_same($detail['integrantes'][1]['cpf_completo'], '987.654.321-00', 'detalhe retorna CPF completo formatado de integrante');
assert_same($detail['integrantes'][1]['cpf_mascarado'], '***.***.***-**', 'detalhe mascara CPF de integrante');
assert_same($detail['entregas'][0]['status_label'], 'Cancelada', 'historico operacional exibe entrega cancelada');
assert_same($detail['entregas'][0]['competencia_label'], 'Julho de 2026', 'entrega recebe rotulo de competencia');
assert_same($detail['documentos'][0]['tamanho_formatado'], '2,0 KB', 'documento recebe tamanho formatado');
assert_same($detail['historico'][0]['before'], [], 'historico com JSON anterior invalido vira array vazio');
assert_same($detail['historico'][0]['after'], ['status' => 'ativa'], 'historico com JSON novo valido e decodificado');
assert_true(isset($detail['historico'][0]['changes'][0]), 'historico invalido nao interrompe calculo de mudancas');
assert_same($detail['versao_atualizacao'], '2026-07-03 08:00:00', 'detalhe retorna versao_atualizacao baseada na ultima atualizacao');

$withoutCpfPermission = $service->detail(8, false, false, false);
assert_same($withoutCpfPermission['cpf_completo'], '123.456.789-09', 'detalhe autenticado retorna CPF completo mesmo sem permissao de edicao');
assert_same($withoutCpfPermission['documentos'], [], 'detalhe respeita ausencia de permissao para documentos');
assert_same($withoutCpfPermission['historico'], [], 'detalhe respeita ausencia de permissao para historico');

$firstVersionRegistration = $detailRegistration;
$firstVersionRegistration['atualizado_em'] = null;
$firstVersionRegistration['versao_atualizacao'] = '2026-07-01 08:00:00';
$firstVersion = (new ComidaMesaService(new ComidaMesaRepository(new ComidaMesaDetailMemoryPdo([
    'detail_registration' => $firstVersionRegistration,
    'detail_members' => [],
    'detail_deliveries' => [],
    'detail_documents' => [],
    'detail_history' => [],
]))))->detail(8, true, false, false);
assert_same($firstVersion['versao_atualizacao'], '2026-07-01 08:00:00', 'detalhe retorna versao_atualizacao baseada no criado_em quando atualizado_em e nulo');

$comidaMesaJs = file_get_contents(dirname(__DIR__) . '/assets/js/comida-mesa.js');
assert_true($comidaMesaJs !== false, 'teste consegue ler JS do modulo Comida na Mesa');
assert_true(str_contains((string) $comidaMesaJs, 'versao_atualizacao: data.versao_atualizacao'), 'JS preenche versao_atualizacao usando data.versao_atualizacao');
assert_true(!str_contains((string) $comidaMesaJs, 'versao_atualizacao: data.atualizado_em'), 'JS nao usa data.atualizado_em como versao do formulario');
assert_true(str_contains((string) $comidaMesaJs, 'const fullCpf = (data = {})'), 'JS possui helper para CPF completo');
assert_true(str_contains((string) $comidaMesaJs, '{ label: "CPF", value: fullCpf(data), span: 3 }'), 'modal do beneficiario usa CPF completo pelo helper');
assert_true(str_contains((string) $comidaMesaJs, 'CPF ${label(fullCpf(item))}'), 'modal do beneficiario usa CPF completo de integrantes');
assert_true(str_contains((string) $comidaMesaJs, 'beneficiary-detail-card'), 'modal do beneficiario separa dados em cards');
assert_true(str_contains((string) $comidaMesaJs, 'beneficiary-detail-card--full'), 'modal do beneficiario usa cards principais em largura total');
assert_true(str_contains((string) $comidaMesaJs, 'beneficiary-detail-card--four'), 'modal do beneficiario usa card 4/12 para integrantes');
assert_true(str_contains((string) $comidaMesaJs, 'beneficiary-detail-card--eight'), 'modal do beneficiario usa card 8/12 para historico de entregas');
assert_true(str_contains((string) $comidaMesaJs, 'beneficiary-detail-card--half'), 'modal do beneficiario usa cards 6/12 para documentos e historico');
assert_true(str_contains((string) $comidaMesaJs, '["CPF", fullCpf(person)]'), 'modal ANEXO usa CPF formatado pelo helper');
assert_true(!preg_match('/data-[^=]*cpf=["\'][^"\']*(cpf|\\$\\{)/i', (string) $comidaMesaJs), 'JS do Comida na Mesa nao coloca valor de CPF em data-*');

$moduloPhp = file_get_contents(dirname(__DIR__) . '/modulo.php');
assert_true($moduloPhp !== false, 'teste consegue ler modulo.php');
assert_true(str_contains((string) $moduloPhp, '$service->formatCpf((string) $row[\'cpf\'])'), 'modulo.php usa formatCpf na listagem');
assert_true(!str_contains((string) $moduloPhp, '$service->maskCpf((string) $row[\'cpf\'])'), 'modulo.php nao usa maskCpf na listagem');

@unlink($logDir . DIRECTORY_SEPARATOR . 'application.log');
@rmdir($logDir);

echo $failures === 0 ? 'PASS comida-mesa-operational-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
