<?php
declare(strict_types=1);
namespace App\Integration\SO;
final class SoEnvironment
{
    private const KEYS = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'];
    private array $values = [];
    public function __construct(private readonly string $projectRoot) {}
    public function get(string $key): string
    {
        $this->load();
        if (!in_array($key, self::KEYS, true) || !isset($this->values[$key])) throw new SoIntegrationException('Integração do SO indisponível.');
        return $this->values[$key];
    }
    private function load(): void
    {
        if ($this->values !== []) return;
        $path = $this->resolveFilePath();
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) throw new SoIntegrationException('Integração do SO indisponível.');
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (in_array($key, self::KEYS, true)) $this->values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
        if (($this->values['DB_PORT'] ?? '') === '') $this->values['DB_PORT'] = '3306';
        foreach (self::KEYS as $key) if (($this->values[$key] ?? '') === '') throw new SoIntegrationException('Integração do SO indisponível.');
    }

    private function resolveFilePath(): string
    {
        $projectRoot = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $default = dirname($projectRoot, 2) . DIRECTORY_SEPARATOR . 'configuracoes' . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . '.env';
        $configuredFluxEnvironment = getenv('FLUXEMPRESA_ENV_PATH');
        if (!is_string($configuredFluxEnvironment) || trim($configuredFluxEnvironment) === '') return $default;

        return dirname(rtrim($configuredFluxEnvironment, DIRECTORY_SEPARATOR), 2)
            . DIRECTORY_SEPARATOR . 'so' . DIRECTORY_SEPARATOR . '.env';
    }
}
