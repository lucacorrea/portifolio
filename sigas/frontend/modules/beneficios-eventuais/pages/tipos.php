<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Tipos e regras',
    'description' => 'Catálogo dos benefícios eventuais e parâmetros administrativos.',
    'actions' => [
    [
        'label' => 'Nova ação',
        'icon' => 'plus-circle',
        'primary' => true
    ]
],
    'stats' => [],
    'filters' => [],
    'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Operação',
        'title' => 'Tipos e regras',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'tipo',
                'label' => 'Tipo'
            ],
            [
                'key' => 'unidade',
                'label' => 'Unidade'
            ],
            [
                'key' => 'documentos',
                'label' => 'Documentos'
            ],
            [
                'key' => 'parecer',
                'label' => 'Parecer'
            ],
            [
                'key' => 'ativo',
                'label' => 'Ativo'
            ]
        ],
        'rows' => [
            [
                'tipo' => 'Cesta emergencial',
                'unidade' => 'Unidade',
                'documentos' => 'Obrigatórios',
                'parecer' => 'Sim',
                'ativo' => 'Sim'
            ],
            [
                'tipo' => 'Ajuda humanitária',
                'unidade' => 'R$ / Unidade',
                'documentos' => 'Conforme caso',
                'parecer' => 'Sim',
                'ativo' => 'Sim'
            ],
            [
                'tipo' => 'Outro benefício eventual',
                'unidade' => 'Configurável',
                'documentos' => 'Configurável',
                'parecer' => 'Sim',
                'ativo' => 'Sim'
            ]
        ],
        'primary' => 'tipo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
