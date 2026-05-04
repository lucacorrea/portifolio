<?php
function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function current_user_name(string $fallback = 'Usuário'): string
{
    return current_user()['nome'] ?? $fallback;
}

function current_user_role(string $fallback = 'Usuário'): string
{
    return current_user()['cargo'] ?? $fallback;
}
