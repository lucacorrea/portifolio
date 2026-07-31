<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\SqlStatementSplitter;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('DB_AUTO_MIGRATE=false');
$_ENV['DB_AUTO_MIGRATE'] = 'false';
$_SERVER['DB_AUTO_MIGRATE'] = 'false';

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Database $database */
    $database = $app['database'];
    $files = glob(dirname(__DIR__) . '/database/seeds/*.sql') ?: [];
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    if ($files === []) {
        throw new RuntimeException('Nenhum seed foi encontrado.');
    }

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Seed inválido.');
        }
        foreach (SqlStatementSplitter::split($sql) as $statement) {
            if (trim($statement) !== '') {
                $database->connection()->exec($statement);
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Seed failed: ' . $exception->getMessage());
    fwrite(STDERR, 'Não foi possível aplicar os seeds. Consulte storage/logs/app.log.' . PHP_EOL);
    exit(1);
}

echo 'Seeds aplicados com sucesso.' . PHP_EOL;
