<?php

declare(strict_types=1);

namespace App\Models;

final class Sale
{
    public function __construct(
        public readonly int $id,
        public readonly int $empresaId,
        public readonly string $numeroVenda,
        public readonly float $total,
        public readonly string $status
    ) {
    }
}
