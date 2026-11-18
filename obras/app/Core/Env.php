<?php
declare(strict_types=1);
namespace App\Core;
/** Configuracao PHP separada. Não depende de arquivo .env. */
final class Env {
    private static array $values = [];
    public static function loadArrayFile(string $file): void {
        if (!is_file($file)) return;
        $settings = require $file;
        if (!is_array($settings)) throw new \RuntimeException('Arquivo de configuracao invalido.');
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) continue;
            self::$values[$key] = (string)$value;
        }
    }
    public static function get(string $name, ?string $default = null): ?string {
        $env = getenv($name);
        return $env !== false ? (string)$env : (self::$values[$name] ?? $default);
    }
}
