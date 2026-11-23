<?php
/**
 * MODELO: criar uma CÓPIA somente na Hostinger em:
 *   <pasta-do-dominio>/obras360-private/conexao.php
 * Não enviar o arquivo preenchido para GitHub ou para public_html.
 * Etapa de teste: todo o sistema usa estas credenciais via app/conexao.php.
 */
return [
    'DB_HOST' => 'srv1885.hstgr.io',
    'DB_PORT' => '3306',
    'DB_NAME' => 'u784961086_obras',
    'DB_USER' => 'u784961086_obras',
    'DB_PASS' => 'COLE_SENHA_ATUAL_MYSQL_AQUI',
    'APP_TIMEZONE' => 'America/Manaus',
    'APP_FORCE_HTTPS' => '1',
    'APP_AUDIT_SALT' => 'COLE_AQUI_64_CARACTERES_HEXADECIMAIS_ALEATORIOS',
];
