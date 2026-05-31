<?php

declare(strict_types=1);

namespace App\Models;

final class ClientAccount
{
    public function __construct(
        public readonly int $id,
        public readonly int $clienteId,
        public readonly float $saldoAberto,
        public readonly string $vencimento,
        public readonly string $status
    ) {
    }
}
