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

function sk_ok(string $message): void { echo '[OK]   ' . $message . PHP_EOL; }
function sk_warn(string $message): void { global $warnings; $warnings++; echo '[WARN] ' . $message . PHP_EOL; }
function sk_fail(string $message): void { global $failures; $failures++; echo '[FAIL] ' . $message . PHP_EOL; }

function sk_table(PDO $pdo, string $table): ?array
{
    $stmt = $pdo->prepare('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
    $stmt->execute(['table' => $table]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function sk_column(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
    $stmt->execute(['table' => $table, 'column' => $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function sk_scalar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

try {
    $pdo = Database::connection();
    sk_ok('Bootstrap e conexão com banco carregados.');
    sk_ok('Servidor: ' . (string) $pdo->query('SELECT VERSION()')->fetchColumn() . '.');

    $required = ['usuarios', 'setores', 'permissoes', 'nivel_permissoes', 'pessoas', 'familias', 'pessoa_atendimentos'];
    foreach ($required as $table) {
        $meta = sk_table($pdo, $table);
        if ($meta === null) {
            sk_fail('Tabela obrigatória ausente: ' . $table . '.');
            continue;
        }
        if (strtolower((string) ($meta['ENGINE'] ?? '')) !== 'innodb') {
            sk_fail($table . ' precisa usar InnoDB para as novas chaves estrangeiras.');
        } else {
            sk_ok('Tabela obrigatória/InnoDB: ' . $table . '.');
        }
    }

    foreach ([['usuarios','id'],['setores','id'],['permissoes','id'],['pessoas','id'],['familias','id'],['pessoa_atendimentos','id']] as [$table, $column]) {
        $meta = sk_column($pdo, $table, $column);
        $type = strtolower((string) ($meta['COLUMN_TYPE'] ?? ''));
        if ($meta !== null && str_contains($type, 'bigint') && str_contains($type, 'unsigned')) sk_ok($table . '.' . $column . ' é BIGINT UNSIGNED.');
        else sk_fail($table . '.' . $column . ' precisa ser BIGINT UNSIGNED compatível.');
    }

    foreach ([['pessoas','cpf'],['familias','responsavel_pessoa_id'],['familias','renda_familiar'],['pessoa_atendimentos','pessoa_id']] as [$table, $column]) {
        sk_column($pdo, $table, $column) === null ? sk_fail('Coluna necessária ausente: ' . $table . '.' . $column . '.') : sk_ok('Coluna necessária: ' . $table . '.' . $column . '.');
    }

    if ($failures === 0) {
        $duplicateFamilyResponsible = sk_scalar($pdo, 'SELECT COUNT(*) FROM (SELECT responsavel_pessoa_id FROM familias WHERE responsavel_pessoa_id IS NOT NULL GROUP BY responsavel_pessoa_id HAVING COUNT(*) > 1) x');
        $duplicateFamilyResponsible > 0 ? sk_warn(number_format($duplicateFamilyResponsible, 0, ',', '.') . ' pessoa(s) aparecem como responsáveis por mais de uma família. O prontuário usará o primeiro vínculo localizado; vale revisar depois.') : sk_ok('Nenhum responsável duplicado entre famílias.');

        $orphanFamilies = sk_scalar($pdo, 'SELECT COUNT(*) FROM familias f LEFT JOIN pessoas p ON p.id = f.responsavel_pessoa_id WHERE f.responsavel_pessoa_id IS NOT NULL AND p.id IS NULL');
        $orphanFamilies > 0 ? sk_fail(number_format($orphanFamilies, 0, ',', '.') . ' família(s) possuem responsável órfão.') : sk_ok('Nenhum responsável de família órfão.');

        $orphanAttendances = sk_scalar($pdo, 'SELECT COUNT(*) FROM pessoa_atendimentos a LEFT JOIN pessoas p ON p.id = a.pessoa_id WHERE p.id IS NULL');
        $orphanAttendances > 0 ? sk_fail(number_format($orphanAttendances, 0, ',', '.') . ' atendimento(s) possuem pessoa órfã.') : sk_ok('Nenhum atendimento com pessoa órfã.');
    }

    foreach (['pessoa_socioeconomico','pessoa_socioeconomico_historico','pessoa_socioeconomico_membros','beneficio_solicitacoes','kit_maternidade_solicitacoes','kit_maternidade_acompanhamentos','kit_maternidade_avaliacoes','kit_maternidade_entregas'] as $table) {
        sk_table($pdo, $table) === null ? sk_ok($table . ' ainda não existe, como esperado antes da migration.') : sk_warn($table . ' já existe. A migration é idempotente; execute o verificador após a aplicação.');
    }

    foreach (['genero','cor_raca','estado_civil','naturalidade','nacionalidade','rg_emissao','rg_uf','whatsapp'] as $column) {
        if (sk_column($pdo, 'pessoas', $column) === null) sk_ok('pessoas.' . $column . ' ainda não existe e será criada pela migration 018.');
        else sk_warn('pessoas.' . $column . ' já existe; a migration 018 manterá a coluna atual.');
    }
} catch (Throwable $exception) {
    sk_fail('Falha no preflight: ' . $exception->getMessage());
}

echo PHP_EOL . 'Resumo: ' . $failures . ' falha(s), ' . $warnings . ' aviso(s).' . PHP_EOL;
if ($failures > 0) {
    echo 'RESULTADO: BLOQUEADO. Não execute as migrations 016/017/018.' . PHP_EOL;
    exit(1);
}

echo 'RESULTADO: APTO PARA MIGRATIONS 016/017/018.' . PHP_EOL;
exit(0);
