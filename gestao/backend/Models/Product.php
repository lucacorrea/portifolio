<?php

declare(strict_types=1);

namespace App\Models;

final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly int $empresaId,
        public readonly string $nome,
        public readonly float $precoVenda,
        public readonly float $quantidade
    ) {
    }
}
