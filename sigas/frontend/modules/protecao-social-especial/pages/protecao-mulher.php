<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Proteção à mulher',
    'description' => 'Visão protegida e agregada dos acompanhamentos e encaminhamentos da rede de proteção.',
    'stats' => $stats([
        ['Acompanhamentos ativos', '74', 'Sem identificação', 'gender-female'],
        ['Prioridade imediata', '9', 'Indicador restrito', 'exclamation-triangle'],
        ['Rede acionada', '58', 'No período', 'diagram-3'],
        ['Retornos confirmados', '87%', 'Consolidado', 'telephone-check'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Encaminhamentos por rede', 'chart' => 'bar', 'labels' => ['Saúde', 'Justiça', 'Segurança', 'Acolhimento', 'Trabalho e renda'], 'values' => [22, 18, 14, 7, 11]],
        ['type' => 'info', 'title' => 'Cuidados de privacidade', 'items' => [
            ['icon' => 'shield-lock', 'title' => 'Dados protegidos', 'text' => 'Nomes, endereços e relatos não são exibidos.', 'badge' => 'Restrito'],
            ['icon' => 'telephone', 'title' => 'Contato seguro', 'text' => 'Retornos devem observar o canal seguro registrado.'],
            ['icon' => 'person-check', 'title' => 'Atendimento qualificado', 'text' => 'Fluxos orientados pela equipe de referência.'],
        ]],
    ],
]);
