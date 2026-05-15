<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly array $allowedRoles)
    {
    }

    public static function allow(array $roles): self
    {
        return new self($roles);
    }

    public function handle(): ?Response
    {
        $role = Session::get('user_role');

        if (!is_string($role) || !in_array($role, $this->allowedRoles, true)) {
            return Response::html('Acesso negado.', 403);
        }

        return null;
    }
}

