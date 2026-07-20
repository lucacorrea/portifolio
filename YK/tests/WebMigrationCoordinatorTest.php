<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/MigrationException.php';
require dirname(__DIR__) . '/src/Core/WebMigrationCoordinator.php';

use App\Core\WebMigrationCoordinator;

function webMigrationAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

function removeWebMigrationTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_dir($path)) {
            removeWebMigrationTestDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yk-web-migration-' . bin2hex(random_bytes(8));
$migrations = $root . DIRECTORY_SEPARATOR . 'migrations';
$cache = $root . DIRECTORY_SEPARATOR . 'cache';
mkdir($migrations, 0770, true);
file_put_contents($migrations . DIRECTORY_SEPARATOR . '001_test.sql', "CREATE TABLE teste (id INT);\n");
$statePath = $cache . DIRECTORY_SEPARATOR . 'state.json';
$lockPath = $cache . DIRECTORY_SEPARATOR . 'state.lock';
$now = 1_700_000_000;

try {
    $runs = 0;
    $coordinator = new WebMigrationCoordinator(
        $migrations,
        $statePath,
        $lockPath,
        static function () use (&$runs): bool {
            ++$runs;
            return true;
        },
        static function () use (&$now): int {
            return $now;
        }
    );
    $coordinator->run();
    $coordinator->run();
    webMigrationAssertSame(1, $runs, 'Fingerprint concluído deve impedir nova execução.');

    $now += 3601;
    $coordinator->run();
    webMigrationAssertSame(2, $runs, 'Sucesso deve ser revalidado periodicamente contra o banco atual.');

    file_put_contents($migrations . DIRECTORY_SEPARATOR . '002_test.sql', "ALTER TABLE teste ADD nome VARCHAR(50);\n");
    $coordinator->run();
    webMigrationAssertSame(3, $runs, 'Fingerprint novo deve executar mesmo após sucesso anterior.');

    $failureState = $cache . DIRECTORY_SEPARATOR . 'failure.json';
    $failureLock = $cache . DIRECTORY_SEPARATOR . 'failure.lock';
    $failureRuns = 0;
    $failing = new WebMigrationCoordinator(
        $migrations,
        $failureState,
        $failureLock,
        static function () use (&$failureRuns): bool {
            ++$failureRuns;
            throw new RuntimeException('password=segredo-que-nao-pode-vazar');
        },
        static fn (): int => $now
    );
    $failing->run();
    $failing->run();
    webMigrationAssertSame(1, $failureRuns, 'Falha deve entrar em cooldown sem propagar a exceção.');
    $persistedFailure = (string) file_get_contents($failureState);
    webMigrationAssertSame(false, str_contains($persistedFailure, 'segredo'), 'Estado não pode persistir mensagem ou segredo.');
    webMigrationAssertSame('failed', json_decode($persistedFailure, true)['status'] ?? null, 'Falha deve ser registrada sem detalhes sensíveis.');
    $failureKeys = array_keys(json_decode($persistedFailure, true));
    sort($failureKeys);
    webMigrationAssertSame(
        ['failure_count', 'fingerprint', 'last_attempt_at', 'last_success_at', 'next_retry_at', 'status', 'version'],
        $failureKeys,
        'Estado deve conter somente metadados operacionais permitidos.'
    );

    file_put_contents($migrations . DIRECTORY_SEPARATOR . '002_test.sql', "ALTER TABLE teste ADD nome_completo VARCHAR(80);\n");
    $failing->run();
    webMigrationAssertSame(2, $failureRuns, 'Fingerprint novo deve ignorar cooldown da versão anterior.');

    $concurrentState = $cache . DIRECTORY_SEPARATOR . 'concurrent.json';
    $concurrentLock = $cache . DIRECTORY_SEPARATOR . 'concurrent.lock';
    $lockHandle = fopen($concurrentLock, 'c+');
    flock($lockHandle, LOCK_EX | LOCK_NB);
    $concurrentRuns = 0;
    $concurrent = new WebMigrationCoordinator(
        $migrations,
        $concurrentState,
        $concurrentLock,
        static function () use (&$concurrentRuns): bool {
            ++$concurrentRuns;
            return true;
        },
        static fn (): int => $now
    );
    $concurrent->run();
    webMigrationAssertSame(0, $concurrentRuns, 'Lock concorrente deve retornar sem aguardar nem executar.');
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    $blockedRuntimePath = $root . DIRECTORY_SEPARATOR . 'blocked-runtime';
    file_put_contents($blockedRuntimePath, 'not-a-directory');
    $fallbackRuns = 0;
    $fallback = new WebMigrationCoordinator(
        $migrations,
        $blockedRuntimePath . DIRECTORY_SEPARATOR . 'state.json',
        $blockedRuntimePath . DIRECTORY_SEPARATOR . 'state.lock',
        static function () use (&$fallbackRuns): bool {
            ++$fallbackRuns;
            return true;
        },
        static fn (): int => $now
    );
    $fallback->run();
    webMigrationAssertSame(1, $fallbackRuns, 'Cache local indisponível deve usar o lock do banco como fallback.');
} finally {
    removeWebMigrationTestDirectory($root);
}

echo "WebMigrationCoordinatorTest: OK\n";
