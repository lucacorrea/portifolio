<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/SqlStatementSplitter.php';

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
migrationAssertSame(12, count($migrationPaths), 'A sequência atual deve conter 12 migrations.');

$expectedVersion = 1;
foreach ($migrationPaths as $path) {
    $name = basename($path);
    migrationAssertSame(
        true,
        preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $name, $matches) === 1,
        'Nome de migration inválido: ' . $name
    );
    migrationAssertSame($expectedVersion, (int) $matches[1], 'A sequência de migrations deve ser contínua.');
    $sql = file_get_contents($path);
    migrationAssertSame(true, is_string($sql) && trim($sql) !== '', 'Migration vazia: ' . $name);
    migrationAssertSame(true, count(SqlStatementSplitter::split((string) $sql)) > 0, 'Migration sem comandos: ' . $name);
    ++$expectedVersion;
}

echo "MigrationRunnerTest: OK\n";
