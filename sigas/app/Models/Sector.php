<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class Sector
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $slug,
        public ?string $descricao,
        public bool $ativo,
        public DateTimeImmutable $criadoEm,
        public ?DateTimeImmutable $atualizadoEm,
        public ?DateTimeImmutable $excluidoEm,
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
            (bool) $row['ativo'],
            new DateTimeImmutable((string) $row['criado_em']),
            empty($row['atualizado_em']) ? null : new DateTimeImmutable((string) $row['atualizado_em']),
            empty($row['excluido_em']) ? null : new DateTimeImmutable((string) $row['excluido_em']),
        );
    }
}
