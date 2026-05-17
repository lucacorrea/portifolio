<?php
// Configuração base do MVP visual Arte&Flor.
// Nesta etapa as páginas .php funcionam como front-end demonstrativo.

const SITE_NAME = 'Arte&Flor';
const SITE_DESCRIPTION = 'Catálogo de vendas de flores, arranjos, vasos e presentes em Coari-AM.';
const WHATSAPP_NUMBER = '5597000000000';

function base_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/arteFlor/index.php');
    $dir = rtrim(dirname($scriptName), '/');

    if (str_ends_with($dir, '/admin')) {
        $dir = rtrim(dirname($dir), '/');
    }

    return ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

function asset(string $path): string
{
    return base_url() . 'assets/' . ltrim($path, '/');
}
