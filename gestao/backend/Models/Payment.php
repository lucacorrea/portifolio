<?php

declare(strict_types=1);

namespace App\Models;

final class Payment
{
    public function __construct(
        public readonly int $id,
        public readonly int $vendaId,
        public readonly string $metodo,
        public readonly float $valor
    ) {
    }
}
