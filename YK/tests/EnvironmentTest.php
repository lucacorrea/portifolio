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
$previousWebValue = getenv('DB_WEB_MIGRATIONS');
$previousEnvPath = getenv('YK_ENV_PATH');

try {
    putenv('DB_AUTO_MIGRATE');
    putenv('DB_WEB_MIGRATIONS');
    putenv('YK_ENV_PATH');
    unset($_ENV['DB_AUTO_MIGRATE'], $_SERVER['DB_AUTO_MIGRATE']);
    unset($_ENV['DB_WEB_MIGRATIONS'], $_SERVER['DB_WEB_MIGRATIONS']);

    $environment = new Environment(__DIR__ . '/missing.env');

    environmentAssertSame(
        'false',
        $environment->get('DB_AUTO_MIGRATE', 'false'),
        'DB_AUTO_MIGRATE deve aceitar o valor padrão usado pelo bootstrap.'
    );

    putenv('DB_AUTO_MIGRATE=true');

    environmentAssertSame(
        'true',
        $environment->get('DB_AUTO_MIGRATE', 'false'),
        'DB_AUTO_MIGRATE deve aceitar o valor definido no ambiente.'
    );

    environmentAssertSame(
        'true',
        $environment->get('DB_WEB_MIGRATIONS', 'true'),
        'DB_WEB_MIGRATIONS deve permitir atualização interna por padrão.'
    );

    environmentAssertSame(
        '/home/usuario/configuracoes/yk/.env',
        str_replace('\\', '/', Environment::resolveFilePath('/home/usuario/public_html/YK')),
        'O .env padrão deve ser procurado dentro de configuracoes/yk.'
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
    if ($previousWebValue === false) {
        putenv('DB_WEB_MIGRATIONS');
        unset($_ENV['DB_WEB_MIGRATIONS'], $_SERVER['DB_WEB_MIGRATIONS']);
    } else {
        putenv('DB_WEB_MIGRATIONS=' . $previousWebValue);
        $_ENV['DB_WEB_MIGRATIONS'] = $previousWebValue;
        $_SERVER['DB_WEB_MIGRATIONS'] = $previousWebValue;
    }
    if ($previousEnvPath === false) {
        putenv('YK_ENV_PATH');
    } else {
        putenv('YK_ENV_PATH=' . $previousEnvPath);
    }
}

echo "EnvironmentTest: OK\n";
