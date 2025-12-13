<?php
declare(strict_types=1);

function exigirLogin(?string $perfilObrigatorio = null): void
{
    if (empty($_SESSION['usuario_logado']) || empty($_SESSION['usuario_id'])) {
        header('Location: /relatorio/index.php');
        exit;
    }

    if ($perfilObrigatorio) {
        $perfis = $_SESSION['perfis'] ?? [];
        if (!in_array($perfilObrigatorio, $perfis, true)) {
            http_response_code(403);
            die('Acesso negado.');
        }
    }
}
