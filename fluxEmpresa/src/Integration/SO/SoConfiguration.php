<?php

declare(strict_types=1);

namespace App\Integration\SO;

use InvalidArgumentException;

final class SoConfiguration
{
    public function __construct(
        private readonly bool $enabled, private readonly string $baseUrl, private readonly string $path,
        private readonly string $clientId, private readonly string $secret, private readonly int $connectTimeout,
        private readonly int $timeout, private readonly bool $verifyTls, private readonly string $environment
    ) {
        if ($enabled) {
            $url = parse_url($baseUrl);
            if (!is_array($url) || !isset($url['scheme'], $url['host']) || $url['host'] === '' || isset($url['user'], $url['pass'])) throw new InvalidArgumentException('URL da integração SO inválida.');
            if ($environment === 'production' && ($url['scheme'] !== 'https' || !$verifyTls)) throw new InvalidArgumentException('A integração SO exige HTTPS com TLS validado em produção.');
            if ($clientId === '' || $secret === '') throw new InvalidArgumentException('Credenciais da integração SO não configuradas.');
        }
    }
    public function enabled(): bool { return $this->enabled; }
    public function url(): string { return rtrim($this->baseUrl, '/') . '/' . ltrim($this->path, '/'); }
    public function clientId(): string { return $this->clientId; }
    public function secret(): string { return $this->secret; }
    public function connectTimeout(): int { return $this->connectTimeout; }
    public function timeout(): int { return $this->timeout; }
    public function verifyTls(): bool { return $this->verifyTls; }
}
