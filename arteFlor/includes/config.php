<?php
// Configuracao base do MVP Arte&Flor.
// Nesta fase nao ha banco, login real, API ou pagamento integrado.

const SITE_NAME = 'Arte&Flor';
const SITE_DESCRIPTION = 'Catálogo demonstrativo de flores, arranjos, vasos e presentes.';
const WHATSAPP_NUMBER = '5597000000000';
const BASE_PATH = '/arteFlor/';

function site_url($path = '')
{
    return BASE_PATH . ltrim($path, '/');
}

function asset($path)
{
    return site_url('assets/' . ltrim($path, '/'));
}
