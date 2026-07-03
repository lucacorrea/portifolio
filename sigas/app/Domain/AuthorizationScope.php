<?php

declare(strict_types=1);

namespace App\Domain;

enum AuthorizationScope: string
{
    case GLOBAL = 'global';
    case SECTOR = 'setorial';
    case SELF = 'proprio_usuario';
    case NONE = 'sem_acesso';
}
