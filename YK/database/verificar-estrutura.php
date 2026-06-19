<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este verificador deve ser executado somente via CLI.');
}

$requiredExtensions = ['pdo', 'pdo_mysql'];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Extensao PHP obrigatoria ausente: {$extension}" . PHP_EOL);
        exit(1);
    }
}

$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, 'Defina DB_NAME e DB_USER antes de executar. DB_HOST, DB_PORT e DB_PASS sao opcionais.' . PHP_EOL);
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, 'Falha ao conectar ao banco informado. Verifique host, porta, banco e usuario.' . PHP_EOL);
    exit(1);
}

function scalar(PDO $pdo, string $sql, array $params = []): int|string
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $value = $statement->fetchColumn();

    return $value === false ? 0 : $value;
}

$tables = ['perfis', 'usuarios', 'permissoes', 'perfil_permissoes'];
$tableResults = [];
foreach ($tables as $table) {
    $tableResults[$table] = (int) scalar(
        $pdo,
        'SELECT COUNT(*)
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?',
        [$table]
    );
}

$missingTables = array_keys(array_filter($tableResults, static fn (int $exists): bool => $exists === 0));
if ($missingTables !== []) {
    fwrite(STDERR, 'Tabelas ausentes: ' . implode(', ', $missingTables) . PHP_EOL);
    exit(1);
}

$foreignKeys = (int) scalar(
    $pdo,
    "SELECT COUNT(*)
       FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME IN (
            'fk_usuarios_perfil',
            'fk_perfil_permissoes_perfil',
            'fk_perfil_permissoes_permissao'
        )"
);

$checks = [
    'perfis_total' => (int) scalar($pdo, 'SELECT COUNT(*) FROM perfis'),
    'permissoes_total' => (int) scalar($pdo, 'SELECT COUNT(*) FROM permissoes'),
    'administrador_permissoes' => (int) scalar(
        $pdo,
        "SELECT COUNT(*)
           FROM perfil_permissoes pp
           INNER JOIN perfis p ON p.id = pp.perfil_id
          WHERE p.nome = 'Administrador'"
    ),
    'recepcao_permissoes' => (int) scalar(
        $pdo,
        "SELECT COUNT(*)
           FROM perfil_permissoes pp
           INNER JOIN perfis p ON p.id = pp.perfil_id
          WHERE p.nome = 'Recepção'"
    ),
    'codigos_duplicados' => (int) scalar(
        $pdo,
        'SELECT COUNT(*)
           FROM (
                SELECT codigo
                  FROM permissoes
                 GROUP BY codigo
                HAVING COUNT(*) > 1
           ) duplicados'
    ),
    'foreign_keys_encontradas' => $foreignKeys,
];

echo 'Verificacao da estrutura de usuarios, perfis e permissoes' . PHP_EOL;
echo 'Banco: ' . $dbName . PHP_EOL;
foreach ($tableResults as $table => $exists) {
    echo sprintf('Tabela %-20s %s', $table . ':', $exists === 1 ? 'OK' : 'AUSENTE') . PHP_EOL;
}
foreach ($checks as $name => $value) {
    echo sprintf('%-30s %s', $name . ':', (string) $value) . PHP_EOL;
}

if ($checks['codigos_duplicados'] !== 0 || $checks['foreign_keys_encontradas'] !== 3) {
    fwrite(STDERR, 'A verificacao encontrou inconsistencias.' . PHP_EOL);
    exit(1);
}

echo 'Estrutura verificada sem inconsistencias basicas.' . PHP_EOL;
