<?php
declare(strict_types=1);
namespace App\Admin\Service;
use App\Access\DTO\AuthenticatedUser;
use RuntimeException;
final class PlatformAdminPolicy { public function require(AuthenticatedUser $user): void { if (!$user->isPlatformAdministrator()) throw new RuntimeException('Acesso negado.'); } }
