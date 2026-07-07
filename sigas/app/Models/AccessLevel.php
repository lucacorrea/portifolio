<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class AccessLevel
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $slug,
        public ?string $descricao,
        public int $prioridade,
        public bool $ativo,
        public DateTimeImmutable $criadoEm,
        public ?DateTimeImmutable $atualizadoEm,
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
            (int) $row['prioridade'],
            (bool) $row['ativo'],
            new DateTimeImmutable((string) $row['criado_em']),
            empty($row['atualizado_em']) ? null : new DateTimeImmutable((string) $row['atualizado_em']),
        );
    }
}
