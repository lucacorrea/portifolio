<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Pós-parto e encerramentos',
    'description' => 'Registro do nascimento e encerramento do fluxo, com destaque para partos ocorridos sem entrega.',
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
        'title' => 'Pós-parto e encerramentos',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'nascimento',
                'label' => 'Nascimento'
            ],
            [
                'key' => 'kit',
                'label' => 'Kit'
            ],
            [
                'key' => 'motivo',
                'label' => 'Motivo'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ],
            [
                'key' => 'encerramento',
                'label' => 'Encerramento'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Paula M. Reis',
                'nascimento' => '19/08/2026',
                'kit' => 'Entregue',
                'motivo' => '—',
                'situacao' => 'Concluído',
                'encerramento' => '20/08/2026'
            ],
            [
                'beneficiaria' => 'Jéssica R. Alves',
                'nascimento' => '20/08/2026',
                'kit' => 'Não entregue',
                'motivo' => 'Documentação pendente',
                'situacao' => 'Ação necessária',
                'encerramento' => '—'
            ],
            [
                'beneficiaria' => 'Diana C. Alves',
                'nascimento' => '17/08/2026',
                'kit' => 'Não entregue',
                'motivo' => 'Mudança de município',
                'situacao' => 'Encerrar sem concessão',
                'encerramento' => 'Pendente'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
