<?php

declare(strict_types=1);

namespace App\Domain;

enum AccessLevelSlug: string
{
    case ADMINISTRATOR = 'administrador';
    case SUPPORT = 'suporte';
    case MANAGER = 'gestor';
    case TECHNICIAN = 'tecnico';
    case ATTENDANT = 'atendente';
    case READ_ONLY = 'leitura';
}
