<?php
declare(strict_types=1);
namespace App\Integration\SO;
final class SoEnvironment
{
    private const KEYS = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'];
    private const KEY_ALIASES = [
        'DB_HOST' => ['SO_DB_HOST', 'DB_HOST'],
        'DB_PORT' => ['SO_DB_PORT', 'DB_PORT'],
        'DB_DATABASE' => ['SO_DB_DATABASE', 'DB_DATABASE'],
        'DB_USERNAME' => ['SO_DB_USERNAME', 'DB_USERNAME'],
        'DB_PASSWORD' => ['SO_DB_PASSWORD', 'DB_PASSWORD'],
        'DB_CHARSET' => ['SO_DB_CHARSET', 'DB_CHARSET'],
    ];
    private array $values = [];

    public function __construct(private readonly string $projectRoot) {}

    public function get(string $key): string
    {
        $this->load();
        if (!in_array($key, self::KEYS, true) || !isset($this->values[$key])) {
            throw new SoIntegrationException('Integração do SO indisponível.');
        }

        return $this->values[$key];
    }

    private function load(): void
    {
        if ($this->values !== []) return;

        $path = $this->resolveFilePath();
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) {
            throw new SoIntegrationException('Integração do SO indisponível.');
        }

        $rawValues = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $rawValues[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
        foreach (self::KEY_ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (($rawValues[$alias] ?? '') !== '') {
                    $this->values[$key] = $rawValues[$alias];
                    break;
                }
            }
        }
        if (($this->values['DB_PORT'] ?? '') === '') $this->values['DB_PORT'] = '3306';
        foreach (self::KEYS as $key) {
            if (($this->values[$key] ?? '') === '') {
                throw new SoIntegrationException('Integração do SO indisponível.');
            }
        }
    }

    private function resolveFilePath(): string
    {
        $projectRoot = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $paths = [];
        $configuredSoEnvironment = $_SERVER['SO_ENV_PATH'] ?? getenv('SO_ENV_PATH');
        if (is_string($configuredSoEnvironment) && trim($configuredSoEnvironment) !== '') {
            $paths[] = trim($configuredSoEnvironment);
        }

        $configuredFluxEnvironment = getenv('FLUXEMPRESA_ENV_PATH');
        if (is_string($configuredFluxEnvironment) && trim($configuredFluxEnvironment) !== '') {
            $paths[] = dirname(rtrim($configuredFluxEnvironment, DIRECTORY_SEPARATOR), 2)
                . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . '.env';
        }

        $paths[] = dirname($projectRoot, 2) . DIRECTORY_SEPARATOR . 'configuracoes' . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . '.env';
        $paths[] = dirname($projectRoot) . DIRECTORY_SEPARATOR . 'configuracoes' . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . '.env';

        $home = getenv('HOME');
        if (is_string($home) && trim($home) !== '') {
            $paths[] = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'configuracao' . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . 'conect' . DIRECTORY_SEPARATOR . '.env';
        }
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] = dirname((string) $_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'configuracao' . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . 'conect' . DIRECTORY_SEPARATOR . '.env';
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path) && is_readable($path)) return $path;
        }

        throw new SoIntegrationException('Integração do SO indisponível.');
    }
}
