<?php

declare(strict_types=1);

namespace App\Models;

final class Company
{
    public function __construct(
        public int $id,
        public string $nome,
        public ?string $telefone = null,
        public ?string $endereco = null
    ) {
    }
}
