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

function verify_ok(string $message): void
{
    echo '[OK]   ' . $message . PHP_EOL;
}

function verify_warn(string $message): void
{
    global $warnings;
    $warnings++;
    echo '[WARN] ' . $message . PHP_EOL;
}

function verify_fail(string $message): void
{
    global $failures;
    $failures++;
    echo '[FAIL] ' . $message . PHP_EOL;
}

function verify_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

function verify_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() === 1;
}

function verify_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND INDEX_NAME = :index'
    );
    $stmt->execute(['table' => $table, 'index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function verify_fk_exists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :constraint
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->execute(['table' => $table, 'constraint' => $constraint]);
    return (int) $stmt->fetchColumn() > 0;
}

function verify_scalar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

try {
    $pdo = Database::connection();
    verify_ok('Conexão com banco disponível.');

    foreach (['usuario_permissao_excecoes', 'pessoa_atendimentos', 'pessoa_movimentacoes'] as $table) {
        if (verify_table_exists($pdo, $table)) {
            verify_ok('Tabela criada/disponível: ' . $table . '.');
        } else {
            verify_fail('Tabela ausente após migration: ' . $table . '.');
        }
    }

    if (!verify_column_exists($pdo, 'pe_candidatos', 'pessoa_id')) {
        verify_fail('Coluna pe_candidatos.pessoa_id não foi criada.');
    } else {
        verify_ok('Coluna pe_candidatos.pessoa_id disponível.');
    }

    if (!verify_index_exists($pdo, 'pe_candidatos', 'idx_pe_candidatos_pessoa_id')) {
        verify_fail('Índice idx_pe_candidatos_pessoa_id ausente.');
    } else {
        verify_ok('Índice idx_pe_candidatos_pessoa_id disponível.');
    }

    if (!verify_fk_exists($pdo, 'pe_candidatos', 'fk_pe_candidatos_pessoa')) {
        verify_fail('FK fk_pe_candidatos_pessoa ausente.');
    } else {
        verify_ok('FK fk_pe_candidatos_pessoa disponível.');
    }

    if (!verify_index_exists($pdo, 'pessoa_atendimentos', 'idx_pessoa_atendimentos_fila_atual')) {
        verify_fail('Índice composto idx_pessoa_atendimentos_fila_atual ausente.');
    } else {
        verify_ok('Índice composto da fila operacional disponível.');
    }

    if ($failures > 0) {
        throw new RuntimeException('Estrutura pós-migration incompleta.');
    }

    $orphanLinks = verify_scalar(
        $pdo,
        'SELECT COUNT(*)
         FROM pe_candidatos c
         LEFT JOIN pessoas p ON p.id = c.pessoa_id
         WHERE c.pessoa_id IS NOT NULL AND p.id IS NULL'
    );
    if ($orphanLinks > 0) {
        verify_fail(number_format($orphanLinks, 0, ',', '.') . ' vínculo(s) pessoa_id órfãos detectados.');
    } else {
        verify_ok('Nenhum vínculo pessoa_id órfão.');
    }

    $cpfMismatch = verify_scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM pe_candidatos c
         INNER JOIN pessoas p ON p.id = c.pessoa_id
         WHERE c.pessoa_id IS NOT NULL
           AND c.cpf IS NOT NULL
           AND p.cpf IS NOT NULL
           AND c.cpf <> p.cpf"
    );
    if ($cpfMismatch > 0) {
        verify_fail(number_format($cpfMismatch, 0, ',', '.') . ' candidato(s) possuem pessoa_id apontando para CPF diferente.');
    } else {
        verify_ok('Nenhum vínculo candidato/pessoa com CPF divergente.');
    }

    $linkedDuplicates = verify_scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM pe_candidatos c
         INNER JOIN (
             SELECT cpf
             FROM pe_candidatos
             WHERE cpf IS NOT NULL AND CHAR_LENGTH(cpf) = 11
             GROUP BY cpf
             HAVING COUNT(*) > 1
         ) d ON d.cpf = c.cpf
         WHERE c.pessoa_id IS NOT NULL"
    );
    if ($linkedDuplicates > 0) {
        verify_warn(number_format($linkedDuplicates, 0, ',', '.') . ' candidato(s) com CPF repetido possuem vínculo central. Revise se são resoluções manuais legítimas.');
    } else {
        verify_ok('Backfill não vinculou candidatos de CPF duplicado.');
    }

    $permissionCount = verify_scalar(
        $pdo,
        "SELECT COUNT(*) FROM permissoes
         WHERE slug = 'governanca.excecoes_usuario' AND ativo = 1"
    );
    if ($permissionCount !== 1) {
        verify_fail('Permissão governanca.excecoes_usuario ausente ou inconsistente.');
    } else {
        verify_ok('Permissão governanca.excecoes_usuario ativa.');
    }

    $missingGlobalPermission = verify_scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM niveis_acesso n
         LEFT JOIN nivel_permissoes np
           ON np.nivel_id = n.id
          AND np.permissao_id = (
              SELECT id FROM permissoes
              WHERE slug = 'governanca.excecoes_usuario'
              LIMIT 1
          )
         WHERE n.slug IN ('administrador', 'suporte')
           AND n.ativo = 1
           AND np.permissao_id IS NULL"
    );
    if ($missingGlobalPermission > 0) {
        verify_fail('Administrador/Suporte ativo sem governanca.excecoes_usuario.');
    } else {
        verify_ok('Administrador/Suporte receberam a permissão de exceções individuais.');
    }

    $invalidAttendancePersons = verify_scalar(
        $pdo,
        'SELECT COUNT(*)
         FROM pessoa_atendimentos a
         LEFT JOIN pessoas p ON p.id = a.pessoa_id
         WHERE p.id IS NULL'
    );
    if ($invalidAttendancePersons > 0) {
        verify_fail(number_format($invalidAttendancePersons, 0, ',', '.') . ' atendimento(s) sem pessoa central válida.');
    } else {
        verify_ok('Todos os atendimentos rastreados apontam para pessoa válida.');
    }

    $invalidMovementLinks = verify_scalar(
        $pdo,
        'SELECT COUNT(*)
         FROM pessoa_movimentacoes m
         LEFT JOIN pessoa_atendimentos a ON a.id = m.atendimento_id
         WHERE a.id IS NULL OR a.pessoa_id <> m.pessoa_id'
    );
    if ($invalidMovementLinks > 0) {
        verify_fail(number_format($invalidMovementLinks, 0, ',', '.') . ' movimentação(ões) com vínculo inconsistente.');
    } else {
        verify_ok('Movimentações consistentes com seus atendimentos.');
    }
} catch (Throwable $exception) {
    if ($failures === 0) {
        verify_fail('Falha na verificação: ' . $exception->getMessage());
    }
}

echo PHP_EOL;
echo 'Resumo: ' . $failures . ' falha(s), ' . $warnings . ' aviso(s).' . PHP_EOL;

if ($failures > 0) {
    echo 'RESULTADO: NÃO LIBERAR A FUNCIONALIDADE.' . PHP_EOL;
    exit(1);
}

echo 'RESULTADO: MIGRATION VALIDADA. Estrutura pronta para teste funcional controlado.' . PHP_EOL;
exit(0);
