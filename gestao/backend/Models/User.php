<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public int $id,
        public int $empresaId,
        public string $nome,
        public string $email,
        public string $nivel,
        public bool $ativo
    ) {
    }
}
