<?php
declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap.php';

try {
    /** @var Database $database */
    $database = $app['database'];
    $connection = $database->connection();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Nao foi possivel conectar ao banco configurado.' . PHP_EOL);
    exit(1);
}

function checkScalar(PDO $connection, string $sql, array $params = []): int
{
    $statement = $connection->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

$tables = ['perfis', 'usuarios', 'permissoes', 'perfil_permissoes'];
$missingTables = [];

foreach ($tables as $table) {
    $exists = checkScalar(
        $connection,
        'SELECT COUNT(*)
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name',
        ['table_name' => $table]
    );

    if ($exists !== 1) {
        $missingTables[] = $table;
    }
}

if ($missingTables !== []) {
    fwrite(STDERR, 'Tabelas ausentes: ' . implode(', ', $missingTables) . PHP_EOL);
    exit(1);
}

$requiredIndexes = [
    ['perfis', 'uk_perfis_nome'],
    ['perfis', 'idx_perfis_status'],
    ['usuarios', 'uk_usuarios_usuario'],
    ['usuarios', 'uk_usuarios_email'],
    ['usuarios', 'idx_usuarios_perfil'],
    ['usuarios', 'idx_usuarios_status'],
    ['permissoes', 'uk_permissoes_codigo'],
    ['perfil_permissoes', 'PRIMARY'],
    ['perfil_permissoes', 'idx_perfil_permissoes_permissao'],
];

$missingIndexes = [];
foreach ($requiredIndexes as [$table, $index]) {
    $exists = checkScalar(
        $connection,
        'SELECT COUNT(*)
           FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name',
        [
            'table_name' => $table,
            'index_name' => $index,
        ]
    );

    if ($exists < 1) {
        $missingIndexes[] = $table . '.' . $index;
    }
}

$foreignKeys = checkScalar(
    $connection,
    "SELECT COUNT(*)
       FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME IN (
            'fk_usuarios_perfil',
            'fk_perfil_permissoes_perfil',
            'fk_perfil_permissoes_permissao'
        )"
);

$permissionCount = checkScalar($connection, 'SELECT COUNT(*) FROM permissoes');
$profileCount = checkScalar($connection, 'SELECT COUNT(*) FROM perfis');
$administratorPermissionCount = checkScalar(
    $connection,
    "SELECT COUNT(*)
       FROM perfil_permissoes pp
       INNER JOIN perfis p ON p.id = pp.perfil_id
      WHERE p.nome = 'Administrador'"
);
$receptionPermissionCount = checkScalar(
    $connection,
    "SELECT COUNT(*)
       FROM perfil_permissoes pp
       INNER JOIN perfis p ON p.id = pp.perfil_id
      WHERE p.nome = 'Recepção'"
);
$duplicateCodes = checkScalar(
    $connection,
    'SELECT COUNT(*)
       FROM (
            SELECT codigo
              FROM permissoes
             GROUP BY codigo
            HAVING COUNT(*) > 1
       ) duplicados'
);

$errors = [];
if ($missingIndexes !== []) {
    $errors[] = 'Indices ausentes: ' . implode(', ', $missingIndexes);
}
if ($foreignKeys !== 3) {
    $errors[] = 'Quantidade de foreign keys inesperada.';
}
if ($duplicateCodes !== 0) {
    $errors[] = 'Existem codigos de permissao duplicados.';
}

echo 'Verificacao da estrutura de acesso' . PHP_EOL;
echo 'Tabelas: OK' . PHP_EOL;
echo 'Indices basicos: ' . ($missingIndexes === [] ? 'OK' : 'FALHA') . PHP_EOL;
echo 'Foreign keys: ' . $foreignKeys . '/3' . PHP_EOL;
echo 'Perfis: ' . $profileCount . PHP_EOL;
echo 'Permissoes: ' . $permissionCount . PHP_EOL;
echo 'Permissoes Administrador: ' . $administratorPermissionCount . PHP_EOL;
echo 'Permissoes Recepcao: ' . $receptionPermissionCount . PHP_EOL;

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'Estrutura verificada sem inconsistencias basicas.' . PHP_EOL;
