<?php

declare(strict_types=1);

namespace App\Models;

final class Category
{
    public function __construct(
        public int $id,
        public int $empresaId,
        public string $nome
    ) {
    }
}
