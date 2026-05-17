<?php
// Configuração base do MVP Arte&Flor.
// Nesta fase não há banco de dados. Os dados vêm de JSON local.

const SITE_NAME = 'Arte&Flor';
const SITE_DESCRIPTION = 'Catálogo de vendas de flores, arranjos, vasos e presentes em Coari-AM.';
const WHATSAPP_NUMBER = '5597000000000';
const BASE_URL = './';

function asset(string $path): string
{
    return BASE_URL . 'assets/' . ltrim($path, '/');
}
