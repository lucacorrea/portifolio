<?php
class RoleMiddleware
{
    public static function handle(string $area): void
    {
        $permissoes = require dirname(__DIR__) . '/Config/permissions.php';
        $usuario = $_SESSION['user'] ?? [];
        $nivel = $usuario['nivel'] ?? 'recepcao';
        $perfisPermitidos = $permissoes[$area] ?? [];

        if ($nivel === 'dono' || in_array($nivel, $perfisPermitidos, true)) {
            return;
        }

        http_response_code(403);
        exit('Acesso negado.');
    }
}
