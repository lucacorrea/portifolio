<?php
function current_user(): array
{
    return $_SESSION['user'] ?? [
        'nome' => 'Usuário Demo',
        'cargo' => 'Recepção',
        'nivel' => 'recepcao',
    ];
}
