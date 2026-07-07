<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Integrations\Anexo\AnexoIntegrationService;

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

final class AnexoFakeRepository
{
    public int $findCalls = 0;
    public int $familiaresCalls = 0;
    public int $solicitacoesCalls = 0;
    public int $entregasCalls = 0;

    /** @return array<string,mixed>|null */
    public function findSolicitanteByCpf(string $cpf): ?array
    {
        $this->findCalls++;
        if ($cpf === '00000000000') {
            return null;
        }

        return [
            'id' => 1,
            'nome' => 'Pessoa Teste',
            'cpf' => '12345678909',
            'nis' => null,
            'telefone' => '92999990000',
            'bairro_nome' => 'Centro',
            'genero' => null,
            'estado_civil' => null,
            'data_nascimento' => null,
            'nacionalidade' => null,
            'naturalidade' => null,
            'rg' => null,
            'rg_emissao' => null,
            'rg_uf' => null,
            'endereco' => null,
            'numero' => null,
            'complemento' => null,
            'referencia' => null,
            'renda_familiar' => null,
            'total_moradores' => null,
            'total_familias' => null,
            'resumo_caso' => null,
            'conj_nome' => null,
            'conj_cpf' => null,
            'conj_nis' => null,
            'conj_rg' => null,
            'conj_nasc' => null,
            'created_at' => null,
            'updated_at' => null,
            'responsavel' => null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function familiares(int $solicitanteId): array
    {
        $this->familiaresCalls++;
        return [['nome' => 'Familiar Teste', 'data_nascimento' => null, 'parentesco' => 'Filho', 'escolaridade' => null]];
    }

    /** @return list<array<string,mixed>> */
    public function solicitacoes(int $solicitanteId, string $cpf): array
    {
        $this->solicitacoesCalls++;
        return [[
            'id' => 10,
            'ajuda_tipo_id' => 2,
            'ajuda_nome' => 'Cesta',
            'ajuda_categoria' => 'Alimento',
            'resumo_caso' => null,
            'data_solicitacao' => null,
            'status' => 'aberta',
            'created_by' => null,
            'origem' => null,
            'entregas_count' => 0,
            'data_entrega' => null,
            'hora_entrega' => null,
        ]];
    }

    /** @return list<array<string,mixed>> */
    public function entregasPorPessoa(int $solicitanteId, string $cpf): array
    {
        $this->entregasCalls++;
        return [['ajuda_nome' => 'Cesta', 'data_entrega' => '2026-01-02', 'hora_entrega' => '09:00:00', 'entregue' => 'SIM', 'created_at' => null]];
    }
}

$basicRepo = new AnexoFakeRepository();
$basicService = new AnexoIntegrationService($basicRepo, 'enabled');
$basic = $basicService->consultCpfBasic('12345678909');

assert_true($basic['found'] === true, 'consultCpfBasic encontra solicitante');
assert_same($basic['person']['name'] ?? null, 'Pessoa Teste', 'consultCpfBasic retorna nome mínimo');
assert_same($basic['person']['cpf_formatted'] ?? null, '123.456.789-09', 'consultCpfBasic retorna CPF completo formatado');
assert_true(!isset($basic['familiares'], $basic['solicitacoes'], $basic['historico_ajudas']), 'consultCpfBasic não retorna relações');
assert_same($basicRepo->findCalls, 1, 'consultCpfBasic chama findSolicitanteByCpf');
assert_same($basicRepo->familiaresCalls, 0, 'consultCpfBasic não chama familiares');
assert_same($basicRepo->solicitacoesCalls, 0, 'consultCpfBasic não chama solicitações');
assert_same($basicRepo->entregasCalls, 0, 'consultCpfBasic não chama entregas');

$fullRepo = new AnexoFakeRepository();
$fullService = new AnexoIntegrationService($fullRepo, 'enabled');
$full = $fullService->consultCpf('12345678909');

assert_true($full['found'] === true, 'consultCpf completo continua funcionando');
assert_same($full['person']['cpf'], '12345678909', 'consultCpf completo preserva CPF com 11 dígitos');
assert_same($full['person']['cpf_formatted'], '123.456.789-09', 'consultCpf completo retorna CPF formatado para o modal ANEXO');
assert_same($full['person']['cpf_masked'], '123.***.***-09', 'consultCpf completo mantém CPF mascarado por compatibilidade');
assert_same($fullRepo->familiaresCalls, 1, 'consultCpf completo chama familiares');
assert_same($fullRepo->solicitacoesCalls, 1, 'consultCpf completo chama solicitações');
assert_same($fullRepo->entregasCalls, 1, 'consultCpf completo chama entregas');

$api = file_get_contents(dirname(__DIR__) . '/api/comida-mesa/consultar-cpf.php') ?: '';
assert_true(str_contains($api, "['completa', 'entrega_rapida']"), 'endpoint aceita somente modos conhecidos');
assert_true(str_contains($api, '$payload = $service->consultCpf($cpf, $competenceId);'), 'modo entrega_rapida preserva resposta do SIGAS');
assert_true(str_contains($api, "(\$payload['state'] ?? '') !== 'inscrito'"), 'pessoa inscrita não dispara consulta ANEXO rápida');
assert_true(str_contains($api, 'consultCpfBasic($cpf)'), 'pessoa não inscrita usa consulta básica do ANEXO');

echo $failures === 0 ? 'PASS anexo-integration-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
