<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly int $empresaId,
        public readonly string $nome,
        public readonly string $email,
        public readonly string $nivel,
        public readonly bool $ativo
    ) {
    }
}
