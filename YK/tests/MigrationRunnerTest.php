<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/SqlStatementSplitter.php';
require dirname(__DIR__) . '/src/Core/MigrationException.php';
require dirname(__DIR__) . '/src/Core/MigrationRunner.php';

use App\Core\MigrationRunner;
use App\Core\SqlStatementSplitter;

function migrationAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

$sample = <<<'SQL'
-- comentário com ; não encerra comando
SET @sql := 'SELECT ''valor;interno''';
/* bloco ; ignorado */
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SQL;

$sampleStatements = SqlStatementSplitter::split($sample);
migrationAssertSame(4, count($sampleStatements), 'O parser deve respeitar comentários e ponto e vírgula dentro de string.');
migrationAssertSame(true, str_contains($sampleStatements[0], "valor;interno"), 'O conteúdo da string SQL deve ser preservado.');

$migrationPaths = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrationPaths, SORT_NATURAL | SORT_FLAG_CASE);
migrationAssertSame(14, count($migrationPaths), 'A sequência atual deve conter 14 migrations.');

$expectedVersion = 1;
foreach ($migrationPaths as $path) {
    $name = basename($path);
    migrationAssertSame(
        true,
        preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $name, $matches) === 1,
        'Nome de migration inválido: ' . $name
    );
    migrationAssertSame($expectedVersion, (int) $matches[1], 'A sequência de migrations deve ser contínua.');
    migrationAssertSame(
        true,
        MigrationRunner::supportsVersion((int) $matches[1]),
        'Toda migration versionada deve estar homologada para execução automática.'
    );
    $sql = file_get_contents($path);
    migrationAssertSame(true, is_string($sql) && trim($sql) !== '', 'Migration vazia: ' . $name);
    migrationAssertSame(true, count(SqlStatementSplitter::split((string) $sql)) > 0, 'Migration sem comandos: ' . $name);
    ++$expectedVersion;
}

migrationAssertSame(false, MigrationRunner::supportsVersion($expectedVersion), 'Versão futura não pode ser executada sem homologação.');

$commissionMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/014_create_monthly_commission_goals.sql');
migrationAssertSame(true, is_string($commissionMigration), 'A migration de metas mensais deve ser legível.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'uq_meta_comissao_competencia_ativa'), 'Apenas uma configuração ativa deve existir por competência.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'uq_meta_comissao_competencia_versao'), 'O histórico deve preservar versões da competência.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'relatorio.comissao.visualizar'), 'A permissão de visualização deve ser criada.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'relatorio.meta_comissao.configurar'), 'A permissão de configuração deve ser criada.');

echo "MigrationRunnerTest: OK\n";
