<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Beneficiárias',
    'description' => 'Base de gestantes vinculadas ao programa, com etapa atual, DPP e próximas ações.',
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
        'title' => 'Beneficiárias',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'cpf',
                'label' => 'CPF'
            ],
            [
                'key' => 'dpp',
                'label' => 'DPP'
            ],
            [
                'key' => 'mes',
                'label' => 'Mês atual'
            ],
            [
                'key' => 'etapa',
                'label' => 'Etapa'
            ],
            [
                'key' => 'pendencia',
                'label' => 'Pendência'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Ana P. Souza',
                'cpf' => '***.418.***-**',
                'dpp' => '18/09/2026',
                'mes' => '8º mês',
                'etapa' => 'Aguardando Kit',
                'pendencia' => 'Nenhuma'
            ],
            [
                'beneficiaria' => 'Maria L. Costa',
                'cpf' => '***.803.***-**',
                'dpp' => '20/11/2026',
                'mes' => '6º mês',
                'etapa' => 'Em acompanhamento',
                'pendencia' => '1 reunião'
            ],
            [
                'beneficiaria' => 'Raimunda S. Lima',
                'cpf' => '***.002.***-**',
                'dpp' => '29/08/2026',
                'mes' => '9º mês',
                'etapa' => 'Apta',
                'pendencia' => 'Definir entrega'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
