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
        'false',
        $environment->get('DB_WEB_MIGRATIONS', 'false'),
        'DB_WEB_MIGRATIONS deve permanecer desativado por padrão para proteger a disponibilidade.'
    );

    environmentAssertSame(
        'false',
        $environment->get('FISCAL_INTEGRATION_ENABLED', 'false'),
        'A integração fiscal deve nascer desativada.'
    );

    environmentAssertSame(
        'false',
        $environment->get('FISCAL_PRODUCTION_ENABLED', 'false'),
        'A emissão fiscal em produção deve exigir liberação explícita.'
    );

    environmentAssertSame(
        '/home/usuario/configuracoes/yk/.env',
        str_replace('\\', '/', Environment::resolveFilePath('/home/usuario/public_html/YK')),
        'O .env padrão deve ser procurado dentro de configuracoes/yk.'
    );

    $bootstrapSource = file_get_contents(dirname(__DIR__) . '/bootstrap.php');
    environmentAssertSame(
        true,
        is_string($bootstrapSource)
            && str_contains($bootstrapSource, "max(86400, (int) \$environment->get('SESSION_TIMEOUT'")
            && str_contains($bootstrapSource, "max(86400, (int) \$environment->get('SESSION_ABSOLUTE_TIMEOUT'"),
        'Configurações antigas não devem reduzir a sessão para menos de 24 horas.'
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
