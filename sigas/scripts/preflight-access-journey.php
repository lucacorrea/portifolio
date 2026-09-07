<?php

declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

$failures = 0;
$warnings = 0;

function preflight_ok(string $message): void
{
    echo '[OK]   ' . $message . PHP_EOL;
}

function preflight_warn(string $message): void
{
    global $warnings;
    $warnings++;
    echo '[WARN] ' . $message . PHP_EOL;
}

function preflight_fail(string $message): void
{
    global $failures;
    $failures++;
    echo '[FAIL] ' . $message . PHP_EOL;
}

function preflight_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table'
    );
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

function preflight_column(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column
         LIMIT 1'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function preflight_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND INDEX_NAME = :index'
    );
    $stmt->execute(['table' => $table, 'index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function preflight_fk_exists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :constraint
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->execute(['table' => $table, 'constraint' => $constraint]);
    return (int) $stmt->fetchColumn() > 0;
}

function preflight_scalar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

try {
    $pdo = Database::connection();
    preflight_ok('Bootstrap e conexão com banco carregados.');

    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $server = stripos($version, 'mariadb') !== false ? 'MariaDB' : 'MySQL/compatível';
    preflight_ok('Servidor: ' . $server . ' ' . $version . '.');

    $requiredTables = [
        'usuarios',
        'setores',
        'niveis_acesso',
        'permissoes',
        'nivel_permissoes',
        'setor_modulos',
        'usuario_modulo_excecoes',
        'pessoas',
        'pe_candidatos',
    ];

    foreach ($requiredTables as $table) {
        if (preflight_table_exists($pdo, $table)) {
            preflight_ok('Tabela obrigatória: ' . $table . '.');
        } else {
            preflight_fail('Tabela obrigatória ausente: ' . $table . '. Não execute a migration.');
        }
    }

    if ($failures > 0) {
        throw new RuntimeException('Estrutura base incompleta.');
    }

    foreach ([
        ['usuarios', 'id'],
        ['setores', 'id'],
        ['permissoes', 'id'],
        ['pessoas', 'id'],
        ['pe_candidatos', 'id'],
    ] as [$table, $column]) {
        $meta = preflight_column($pdo, $table, $column);
        $type = strtolower((string) ($meta['COLUMN_TYPE'] ?? ''));
        if ($meta !== null && str_contains($type, 'bigint') && str_contains($type, 'unsigned')) {
            preflight_ok($table . '.' . $column . ' é BIGINT UNSIGNED.');
        } else {
            preflight_fail($table . '.' . $column . ' precisa ser compatível com BIGINT UNSIGNED.');
        }
    }

    foreach (['cpf', 'revisao_cpf', 'cpf_duplicado'] as $column) {
        if (preflight_column($pdo, 'pe_candidatos', $column) !== null) {
            preflight_ok('Coluna Primeiro Emprego disponível: pe_candidatos.' . $column . '.');
        } else {
            preflight_fail('Coluna ausente: pe_candidatos.' . $column . '. A migration atual depende dela.');
        }
    }

    if ($failures > 0) {
        throw new RuntimeException('Tipos/colunas incompatíveis.');
    }

    $duplicateCpfs = preflight_scalar(
        $pdo,
        "SELECT COUNT(*) FROM (
            SELECT cpf
            FROM pe_candidatos
            WHERE cpf IS NOT NULL AND CHAR_LENGTH(cpf) = 11
            GROUP BY cpf
            HAVING COUNT(*) > 1
        ) d"
    );
    if ($duplicateCpfs > 0) {
        preflight_warn(number_format($duplicateCpfs, 0, ',', '.') . ' CPF(s) aparecem em mais de um candidato; serão excluídos do vínculo automático.');
    } else {
        preflight_ok('Nenhum CPF duplicado entre candidatos com 11 dígitos.');
    }

    $reviewCpf = preflight_scalar(
        $pdo,
        'SELECT COUNT(*) FROM pe_candidatos WHERE COALESCE(revisao_cpf, 0) = 1'
    );
    if ($reviewCpf > 0) {
        preflight_warn(number_format($reviewCpf, 0, ',', '.') . ' candidato(s) estão em revisão de CPF; serão excluídos do vínculo automático.');
    } else {
        preflight_ok('Nenhum candidato marcado para revisão de CPF.');
    }

    $duplicateFlag = preflight_scalar(
        $pdo,
        'SELECT COUNT(*) FROM pe_candidatos WHERE COALESCE(cpf_duplicado, 0) = 1'
    );
    if ($duplicateFlag > 0) {
        preflight_warn(number_format($duplicateFlag, 0, ',', '.') . ' candidato(s) estão marcados como CPF duplicado; serão excluídos do vínculo automático.');
    } else {
        preflight_ok('Nenhum candidato marcado como CPF duplicado.');
    }

    $hasPersonId = preflight_column($pdo, 'pe_candidatos', 'pessoa_id') !== null;
    if ($hasPersonId) {
        preflight_ok('pe_candidatos.pessoa_id já existe; migration seguirá em modo idempotente.');

        $orphanLinks = preflight_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM pe_candidatos c
             LEFT JOIN pessoas p ON p.id = c.pessoa_id
             WHERE c.pessoa_id IS NOT NULL AND p.id IS NULL'
        );
        if ($orphanLinks > 0) {
            preflight_fail(number_format($orphanLinks, 0, ',', '.') . ' vínculo(s) pessoa_id órfãos detectados. Corrija antes de criar a FK.');
        } else {
            preflight_ok('Nenhum pessoa_id órfão no Primeiro Emprego.');
        }

        if (preflight_index_exists($pdo, 'pe_candidatos', 'idx_pe_candidatos_pessoa_id')) {
            preflight_ok('Índice idx_pe_candidatos_pessoa_id já existe.');
        } else {
            preflight_warn('Índice idx_pe_candidatos_pessoa_id ainda não existe; será criado pela migration.');
        }

        if (preflight_fk_exists($pdo, 'pe_candidatos', 'fk_pe_candidatos_pessoa')) {
            preflight_ok('FK fk_pe_candidatos_pessoa já existe.');
        } else {
            preflight_warn('FK fk_pe_candidatos_pessoa ainda não existe; será criada pela migration.');
        }
    } else {
        preflight_ok('pe_candidatos.pessoa_id ainda não existe, como esperado antes da primeira execução.');
    }

    $safeWherePersonId = $hasPersonId ? 'AND c.pessoa_id IS NULL' : '';
    $safeMatches = preflight_scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM pe_candidatos c
         INNER JOIN (
             SELECT cpf
             FROM pe_candidatos
             WHERE cpf IS NOT NULL AND CHAR_LENGTH(cpf) = 11
             GROUP BY cpf
             HAVING COUNT(*) = 1
         ) u ON u.cpf = c.cpf
         INNER JOIN pessoas p ON p.cpf = c.cpf
         WHERE COALESCE(c.revisao_cpf, 0) = 0
           AND COALESCE(c.cpf_duplicado, 0) = 0
           {$safeWherePersonId}"
    );
    preflight_ok(number_format($safeMatches, 0, ',', '.') . ' candidato(s) estão aptos ao vínculo automático seguro com pessoas.');

    foreach (['usuario_permissao_excecoes', 'pessoa_atendimentos', 'pessoa_movimentacoes'] as $newTable) {
        if (preflight_table_exists($pdo, $newTable)) {
            preflight_warn($newTable . ' já existe; a migration será reexecutada de forma idempotente.');
        } else {
            preflight_ok($newTable . ' ainda não existe e poderá ser criado.');
        }
    }

    if (preflight_table_exists($pdo, 'pessoa_atendimentos')
        && !preflight_index_exists($pdo, 'pessoa_atendimentos', 'idx_pessoa_atendimentos_fila_atual')) {
        preflight_warn('Índice da fila atual ainda não existe; a migration irá adicioná-lo.');
    }
} catch (Throwable $exception) {
    if ($failures === 0) {
        preflight_fail('Falha no preflight: ' . $exception->getMessage());
    }
}

echo PHP_EOL;
echo 'Resumo: ' . $failures . ' falha(s), ' . $warnings . ' aviso(s).' . PHP_EOL;

if ($failures > 0) {
    echo 'RESULTADO: BLOQUEADO. Não execute a migration.' . PHP_EOL;
    exit(1);
}

echo 'RESULTADO: APTO PARA MIGRATION. Avisos acima são cenários tratados de forma conservadora.' . PHP_EOL;
exit(0);
