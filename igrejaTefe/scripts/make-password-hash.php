<?php

declare(strict_types=1);

$password = getenv('SUPPORT_PASSWORD');

if (!is_string($password) || $password === '') {
    fwrite(STDERR, "Defina a variavel SUPPORT_PASSWORD antes de executar.\n");
    fwrite(STDERR, "Use apenas uma variavel local temporaria; nao salve a senha no projeto.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Aviso: senha curta. Use somente em ambiente local temporario.\n");
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
