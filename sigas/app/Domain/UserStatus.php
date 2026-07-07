<?php

declare(strict_types=1);

namespace App\Domain;

enum UserStatus: string
{
    case PENDING = 'pendente';
    case ACTIVE = 'ativo';
    case BLOCKED = 'bloqueado';
    case REJECTED = 'rejeitado';
    case INACTIVE = 'inativo';
}
