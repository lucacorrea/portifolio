<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// O comando controla a execução explicitamente; o bootstrap não deve executá-la antes.
putenv('DB_AUTO_MIGRATE=false');
$_ENV['DB_AUTO_MIGRATE'] = 'false';
$_SERVER['DB_AUTO_MIGRATE'] = 'false';

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Database $database */
    $database = $app['database'];

    $completed = (new MigrationRunner($database->connection()))
        ->run(dirname(__DIR__) . '/database/migrations');
    if (!$completed) {
        fwrite(STDERR, 'Outra execução de migrations ainda está em andamento.' . PHP_EOL);
        exit(2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Não foi possível executar as migrations. Consulte storage/logs/app.log.' . PHP_EOL);
    exit(1);
}

echo 'Migrations executadas com sucesso.' . PHP_EOL;
