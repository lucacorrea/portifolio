<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UserData
{
    public function __construct(
        public string $nome,
        public string $cpf,
        public ?string $matricula,
        public ?string $cargo,
        public string $email,
        public ?string $telefone,
        public ?int $setorSolicitadoId,
        public ?string $senhaHash = null,
    ) {
    }
}
