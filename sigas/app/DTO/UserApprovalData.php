<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UserApprovalData
{
    public function __construct(
        public int $setorId,
        public int $nivelId,
        public ?string $observacaoInterna,
        public int $aprovadorId,
    ) {
    }
}
