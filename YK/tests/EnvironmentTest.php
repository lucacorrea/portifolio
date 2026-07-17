<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Environment.php';

use App\Core\Environment;

function environmentAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export($expected, true)
            . '; obtido: '
            . var_export($actual, true)
        );
    }
}

$previousValue = getenv('DB_AUTO_MIGRATE');

try {
    putenv('DB_AUTO_MIGRATE');
    unset($_ENV['DB_AUTO_MIGRATE'], $_SERVER['DB_AUTO_MIGRATE']);

    $environment = new Environment(__DIR__ . '/missing.env');

    environmentAssertSame(
        'true',
        $environment->get('DB_AUTO_MIGRATE', 'true'),
        'DB_AUTO_MIGRATE deve aceitar o valor padrão usado pelo bootstrap.'
    );

    putenv('DB_AUTO_MIGRATE=false');

    environmentAssertSame(
        'false',
        $environment->get('DB_AUTO_MIGRATE', 'true'),
        'DB_AUTO_MIGRATE deve aceitar o valor definido no ambiente.'
    );
} finally {
    if ($previousValue === false) {
        putenv('DB_AUTO_MIGRATE');
        unset($_ENV['DB_AUTO_MIGRATE'], $_SERVER['DB_AUTO_MIGRATE']);
    } else {
        putenv('DB_AUTO_MIGRATE=' . $previousValue);
        $_ENV['DB_AUTO_MIGRATE'] = $previousValue;
        $_SERVER['DB_AUTO_MIGRATE'] = $previousValue;
    }
}

echo "EnvironmentTest: OK\n";
