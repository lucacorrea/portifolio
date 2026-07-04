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
    'atualizado_em' => '2026-07-03 08:00:00',
    'polo_id' => 2,
    'observacao' => null,
    'motivo_suspensao' => null,
];

$pdo = new ComidaMesaMemoryPdo([
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
assert_same($detail['integrantes'][1]['cpf_mascarado'], '***.***.***-**', 'detalhe mascara CPF de integrante');
assert_same($detail['entregas'][0]['status_label'], 'Cancelada', 'historico operacional exibe entrega cancelada');
assert_same($detail['entregas'][0]['competencia_label'], 'Julho de 2026', 'entrega recebe rotulo de competencia');
assert_same($detail['documentos'][0]['tamanho_formatado'], '2,0 KB', 'documento recebe tamanho formatado');
assert_same($detail['historico'][0]['before'], [], 'historico com JSON anterior invalido vira array vazio');
assert_same($detail['historico'][0]['after'], ['status' => 'ativa'], 'historico com JSON novo valido e decodificado');
assert_true(isset($detail['historico'][0]['changes'][0]), 'historico invalido nao interrompe calculo de mudancas');

$withoutCpfPermission = $service->detail(8, false, false, false);
assert_true(!array_key_exists('cpf_completo', $withoutCpfPermission), 'detalhe sem permissao nao retorna CPF completo');
assert_same($withoutCpfPermission['documentos'], [], 'detalhe respeita ausencia de permissao para documentos');
assert_same($withoutCpfPermission['historico'], [], 'detalhe respeita ausencia de permissao para historico');

@unlink($logDir . DIRECTORY_SEPARATOR . 'application.log');
@rmdir($logDir);

echo $failures === 0 ? 'PASS comida-mesa-operational-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
