<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Manaus');

if (!isset($_SESSION['demo_user'])) {
    $_SESSION['demo_user'] = [
        'nome' => 'Carlos Almeida',
        'perfil' => 'Administrador',
        'empresa' => 'Bar Central',
        'unidade' => 'Unidade Centro'
    ];
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function navActive(string $page, string $current): string {
    return $page === $current ? 'is-active' : '';
}

$demoUser = $_SESSION['demo_user'];
