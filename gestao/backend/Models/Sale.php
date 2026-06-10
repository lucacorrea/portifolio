<?php

declare(strict_types=1);

namespace App\Models;

final class Sale
{
    public function __construct(
        public int $id,
        public int $empresaId,
        public string $numeroVenda,
        public float $total,
        public string $status
    ) {
    }
}
