<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\KitMaternityRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

$failures = 0;
function skv_ok(string $message): void { echo '[OK]   ' . $message . PHP_EOL; }
function skv_fail(string $message): void { global $failures; $failures++; echo '[FAIL] ' . $message . PHP_EOL; }

function skv_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}
function skv_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() === 1;
}
function skv_fk(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $stmt->execute(['table' => $table, 'constraint' => $constraint]);
    return (int) $stmt->fetchColumn() === 1;
}

try {
    $pdo = Database::connection();
    skv_ok('Conexão com o banco carregada.');

    $tables = [
        'pessoa_socioeconomico', 'pessoa_socioeconomico_historico', 'pessoa_socioeconomico_membros',
        'beneficio_solicitacoes', 'kit_maternidade_solicitacoes', 'kit_maternidade_acompanhamentos',
        'kit_maternidade_avaliacoes', 'kit_maternidade_entregas',
    ];
    foreach ($tables as $table) {
        skv_table($pdo, $table) ? skv_ok('Tabela criada: ' . $table . '.') : skv_fail('Tabela ausente: ' . $table . '.');
    }

    foreach ([
        ['pessoa_socioeconomico', 'pessoa_id'],
        ['pessoa_socioeconomico', 'renda_per_capita'],
        ['beneficio_solicitacoes', 'pessoa_id'],
        ['beneficio_solicitacoes', 'decisao'],
        ['kit_maternidade_solicitacoes', 'dpp'],
        ['kit_maternidade_solicitacoes', 'gestacao_risco'],
        ['kit_maternidade_acompanhamentos', 'idade_gestacional_semanas'],
        ['kit_maternidade_acompanhamentos', 'participacao'],
        ['kit_maternidade_avaliacoes', 'parecer_tecnico'],
        ['kit_maternidade_entregas', 'entregue_em'],
    ] as [$table, $column]) {
        skv_column($pdo, $table, $column) ? skv_ok('Coluna validada: ' . $table . '.' . $column . '.') : skv_fail('Coluna ausente: ' . $table . '.' . $column . '.');
    }

    foreach ([
        ['pessoa_socioeconomico', 'fk_pessoa_socioeconomico_pessoa'],
        ['beneficio_solicitacoes', 'fk_beneficio_solicitacoes_pessoa'],
        ['beneficio_solicitacoes', 'fk_beneficio_solicitacoes_atendimento'],
        ['kit_maternidade_solicitacoes', 'fk_kit_maternidade_beneficio'],
        ['kit_maternidade_acompanhamentos', 'fk_kit_acomp_solicitacao'],
        ['kit_maternidade_avaliacoes', 'fk_kit_avaliacoes_solicitacao'],
        ['kit_maternidade_entregas', 'fk_kit_entrega_solicitacao'],
        ['pessoa_socioeconomico_membros', 'fk_socio_membros_socio'],
    ] as [$table, $constraint]) {
        skv_fk($pdo, $table, $constraint) ? skv_ok('FK validada: ' . $constraint . '.') : skv_fail('FK ausente: ' . $constraint . '.');
    }

    $stmt = $pdo->query("SELECT slug FROM permissoes WHERE slug IN ('socioeconomico.visualizar','socioeconomico.editar','socioeconomico.importar_anexo') AND ativo = 1");
    $slugs = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach (['socioeconomico.visualizar','socioeconomico.editar','socioeconomico.importar_anexo'] as $slug) {
        in_array($slug, $slugs, true) ? skv_ok('Permissão ativa: ' . $slug . '.') : skv_fail('Permissão ausente/inativa: ' . $slug . '.');
    }

    $dashboard = (new KitMaternityRepository($pdo))->dashboard();
    if (isset($dashboard['total'], $dashboard['risco'], $dashboard['aptas'], $dashboard['entregues'])) {
        skv_ok('Consulta operacional do Kit Maternidade executada. Total atual: ' . (int) $dashboard['total'] . '.');
    } else {
        skv_fail('Dashboard do Kit Maternidade não retornou a estrutura esperada.');
    }
} catch (Throwable $exception) {
    skv_fail('Falha na verificação: ' . $exception->getMessage());
}

echo PHP_EOL;
if ($failures > 0) {
    echo 'RESULTADO: ESTRUTURA INCOMPLETA (' . $failures . ' falha(s)).' . PHP_EOL;
    exit(1);
}

echo 'RESULTADO: PRONTUÁRIO SOCIOECONÔMICO E KIT MATERNIDADE VALIDADOS.' . PHP_EOL;
exit(0);
