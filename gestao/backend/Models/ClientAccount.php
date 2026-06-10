<?php

declare(strict_types=1);

namespace App\Models;

final class ClientAccount
{
    public function __construct(
        public int $id,
        public int $clienteId,
        public float $saldoAberto,
        public string $vencimento,
        public string $status
    ) {
    }
}
