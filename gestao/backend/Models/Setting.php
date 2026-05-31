<?php

declare(strict_types=1);

namespace App\Models;

final class Setting
{
    public function __construct(
        public readonly string $chave,
        public readonly ?string $valor
    ) {
    }
}
