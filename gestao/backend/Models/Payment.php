<?php

declare(strict_types=1);

namespace App\Models;

final class Payment
{
    public function __construct(
        public int $id,
        public int $vendaId,
        public string $metodo,
        public float $valor
    ) {
    }
}
