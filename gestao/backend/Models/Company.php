<?php

declare(strict_types=1);

namespace App\Models;

final class Company
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly ?string $telefone = null,
        public readonly ?string $endereco = null
    ) {
    }
}
