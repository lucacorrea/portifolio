<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UserAccessChangeData
{
    public function __construct(
        public ?int $novoSetorId,
        public ?int $novoNivelId,
        public string $motivo,
        public int $operadorId,
    ) {
    }
}
