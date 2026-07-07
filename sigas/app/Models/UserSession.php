<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class UserSession
{
    public function __construct(
        public int $id,
        public int $usuarioId,
        public string $identificador,
        public ?string $ip,
        public ?string $userAgent,
        public DateTimeImmutable $ultimoAcessoEm,
        public DateTimeImmutable $expiraEm,
        public ?DateTimeImmutable $revogadaEm,
        public DateTimeImmutable $criadoEm,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['usuario_id'],
            (string) $row['identificador'],
            $row['ip'] === null ? null : (string) $row['ip'],
            $row['user_agent'] === null ? null : (string) $row['user_agent'],
            new DateTimeImmutable((string) $row['ultimo_acesso_em']),
            new DateTimeImmutable((string) $row['expira_em']),
            empty($row['revogada_em']) ? null : new DateTimeImmutable((string) $row['revogada_em']),
            new DateTimeImmutable((string) $row['criado_em']),
        );
    }
}
