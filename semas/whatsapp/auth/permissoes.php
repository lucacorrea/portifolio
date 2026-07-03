<?php
declare(strict_types=1);

function whatsapp_role_from_semas(string $role): string
{
    if ($role === 'suporte' || $role === 'secretario') {
        return 'administrador';
    }
    if ($role === 'admin') {
        return 'operador';
    }
    if ($role === 'prefeito') {
        return 'leitor';
    }
    return 'leitor';
}

function whatsapp_user_can(string $permission): bool
{
    $role = (string)($_SESSION['semas_whatsapp_role'] ?? '');
    if ($role === 'administrador') {
        return true;
    }
    if ($role === 'operador') {
        return in_array($permission, ['visualizar', 'criar_campanha', 'enviar_campanha', 'ver_conversa', 'exportar'], true);
    }
    if ($role === 'revisor') {
        return in_array($permission, ['visualizar', 'ver_conversa', 'revisar', 'atualizar_resumo', 'exportar'], true);
    }
    return $role === 'leitor' && in_array($permission, ['visualizar', 'ver_conversa', 'exportar'], true);
}
