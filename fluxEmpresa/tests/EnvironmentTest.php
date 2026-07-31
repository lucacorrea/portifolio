<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Environment.php';
require dirname(__DIR__) . '/src/Integration/SO/SoIntegrationException.php';
require dirname(__DIR__) . '/src/Integration/SO/SoEnvironment.php';

use App\Core\Environment;
use App\Integration\SO\SoEnvironment;

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
$previousEnvPath = getenv('FLUXEMPRESA_ENV_PATH');
$previousSoEnvPath = getenv('SO_ENV_PATH');
$previousServerSoEnvPath = $_SERVER['SO_ENV_PATH'] ?? null;
$soEnvironmentFile = tempnam(sys_get_temp_dir(), 'flux-so-env-');

try {
    putenv('DB_AUTO_MIGRATE');
    putenv('DB_WEB_MIGRATIONS');
    putenv('FLUXEMPRESA_ENV_PATH');
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
        '/home/usuario/configuracoes/fluxempresa/.env',
        str_replace('\\', '/', Environment::resolveFilePath('/home/usuario/public_html/fluxEmpresa')),
        'O .env padrão deve ser procurado dentro de configuracoes/fluxempresa.'
    );

    if ($soEnvironmentFile === false) {
        throw new RuntimeException('Não foi possível criar o ambiente temporário do SO.');
    }
    file_put_contents($soEnvironmentFile, implode(PHP_EOL, [
        'SO_DB_HOST=so-db.internal',
        'SO_DB_PORT=3307',
        'SO_DB_DATABASE=sistema_so',
        'SO_DB_USERNAME=consulta_flux',
        'SO_DB_PASSWORD=segredo-de-teste',
        'SO_DB_CHARSET=utf8mb4',
    ]));
    putenv('SO_ENV_PATH=' . $soEnvironmentFile);
    unset($_SERVER['SO_ENV_PATH']);
    $soEnvironment = new SoEnvironment(dirname(__DIR__));
    environmentAssertSame(
        'so-db.internal',
        $soEnvironment->get('DB_HOST'),
        'A integração do SO deve aceitar caminho explícito e credenciais isoladas.'
    );
    environmentAssertSame('3307', $soEnvironment->get('DB_PORT'), 'A porta isolada do SO deve ser lida.');
    environmentAssertSame('sistema_so', $soEnvironment->get('DB_DATABASE'), 'O banco isolado do SO deve ser lido.');

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
        putenv('FLUXEMPRESA_ENV_PATH');
    } else {
        putenv('FLUXEMPRESA_ENV_PATH=' . $previousEnvPath);
    }
    if ($previousSoEnvPath === false) {
        putenv('SO_ENV_PATH');
    } else {
        putenv('SO_ENV_PATH=' . $previousSoEnvPath);
    }
    if ($previousServerSoEnvPath === null) {
        unset($_SERVER['SO_ENV_PATH']);
    } else {
        $_SERVER['SO_ENV_PATH'] = $previousServerSoEnvPath;
    }
    if (is_string($soEnvironmentFile) && is_file($soEnvironmentFile)) {
        unlink($soEnvironmentFile);
    }
}

echo "EnvironmentTest: OK\n";
