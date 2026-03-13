<?php
declare(strict_types=1);

return [
    // Hospedagem
    // 'host' => 'smtp.seudominio.com',
    // 'port' => 587,
    // 'smtp_auth' => true,
    // 'username' => 'no-reply@seudominio.com',
    // 'password' => 'SUA_SENHA_SMTP',
    // 'encryption' => 'tls',

    // Localhost com Mailpit/MailHog
    'host' => 'localhost',
    'port' => 1025,
    'smtp_auth' => false,
    'username' => '',
    'password' => '',
    'encryption' => 'none',

    'from_email' => 'no-reply@plhb.local',
    'from_name' => 'Painel da Distribuidora PLHB',
    'reply_to_email' => 'suporte@plhb.local',
    'reply_to_name' => 'Suporte PLHB',
    'timeout' => 20,
];