<?php
class RoleMiddleware
{
    public static function handle(string $area): void
    {
        AuthMiddleware::handle();

        $permissoes = require dirname(__DIR__) . '/Config/permissions.php';
        $usuario = $_SESSION['user'] ?? [];
        $nivel = $usuario['nivel'] ?? '';
        $perfisPermitidos = $permissoes[$area] ?? [];

        if (in_array($nivel, $perfisPermitidos, true)) {
            return;
        }

        http_response_code(403);
        require dirname(__DIR__) . '/Views/errors/403.php';
        exit;
    }
}
