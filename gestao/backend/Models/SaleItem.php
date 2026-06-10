<?php

declare(strict_types=1);

namespace App\Models;

final class SaleItem
{
    public function __construct(
        public int $produtoId,
        public string $produtoNome,
        public float $quantidade,
        public float $precoUnitario
    ) {
    }
}
