<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class Permission
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $slug,
        public ?string $descricao,
        public string $modulo,
        public bool $ativo,
        public DateTimeImmutable $criadoEm,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nome'],
            (string) $row['slug'],
            $row['descricao'] === null ? null : (string) $row['descricao'],
            (string) $row['modulo'],
            (bool) $row['ativo'],
            new DateTimeImmutable((string) $row['criado_em']),
        );
    }
}
