<?php

declare(strict_types=1);

namespace App\Models;

final class SaleItem
{
    public function __construct(
        public readonly int $produtoId,
        public readonly string $produtoNome,
        public readonly float $quantidade,
        public readonly float $precoUnitario
    ) {
    }
}
