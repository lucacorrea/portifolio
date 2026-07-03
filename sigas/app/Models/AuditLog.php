<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class AuditLog
{
    public function __construct(
        public int $id,
        public ?int $usuarioId,
        public ?int $usuarioAlvoId,
        public string $acao,
        public string $modulo,
        public ?string $descricao,
        public ?array $dadosAnteriores,
        public ?array $dadosNovos,
        public ?string $ip,
        public ?string $userAgent,
        public DateTimeImmutable $criadoEm,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['usuario_id'] === null ? null : (int) $row['usuario_id'],
            $row['usuario_alvo_id'] === null ? null : (int) $row['usuario_alvo_id'],
            (string) $row['acao'],
            (string) $row['modulo'],
            $row['descricao'] === null ? null : (string) $row['descricao'],
            self::decodeJson($row['dados_anteriores'] ?? null),
            self::decodeJson($row['dados_novos'] ?? null),
            $row['ip'] === null ? null : (string) $row['ip'],
            $row['user_agent'] === null ? null : (string) $row['user_agent'],
            new DateTimeImmutable((string) $row['criado_em']),
        );
    }

    private static function decodeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
