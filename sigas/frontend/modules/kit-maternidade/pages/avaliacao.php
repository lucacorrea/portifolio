<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Avaliação e contemplação',
    'description' => 'Painel para parecer, aptidão, pendências e motivo estruturado de não contemplação.',
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
        'title' => 'Avaliação e contemplação',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'documentos',
                'label' => 'Documentos'
            ],
            [
                'key' => 'visitas',
                'label' => 'Visitas'
            ],
            [
                'key' => 'reunioes',
                'label' => 'Reuniões'
            ],
            [
                'key' => 'parecer',
                'label' => 'Parecer'
            ],
            [
                'key' => 'decisao',
                'label' => 'Decisão'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Raimunda S. Lima',
                'documentos' => 'OK',
                'visitas' => 'OK',
                'reunioes' => 'OK',
                'parecer' => 'Favorável',
                'decisao' => 'Apta'
            ],
            [
                'beneficiaria' => 'Jéssica R. Alves',
                'documentos' => 'Pendente',
                'visitas' => 'OK',
                'reunioes' => '2/3',
                'parecer' => 'Aguardando',
                'decisao' => 'Pendência'
            ],
            [
                'beneficiaria' => 'Lara C. Silva',
                'documentos' => 'OK',
                'visitas' => 'OK',
                'reunioes' => 'OK',
                'parecer' => 'Desfavorável',
                'decisao' => 'Não apta'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
