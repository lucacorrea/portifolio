<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Sessões',
 'description' => 'Sessões autenticadas, origem, último acesso e possibilidade de revogação administrativa.',
 'actions' => [
    [
        'label' => 'Revogar sessões selecionadas',
        'icon' => 'person-x',
        'primary' => true
    ]
],
 'stats' => [

],
 'filters' => [

],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Sessões',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'usuario',
                'label' => 'Usuário'
            ],
            [
                'key' => 'ip',
                'label' => 'IP'
            ],
            [
                'key' => 'dispositivo',
                'label' => 'Dispositivo'
            ],
            [
                'key' => 'ultimo',
                'label' => 'Último acesso'
            ],
            [
                'key' => 'expira',
                'label' => 'Expira'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'usuario' => 'Maria Oliveira',
                'ip' => '10.0.*.*',
                'dispositivo' => 'Chrome / Windows',
                'ultimo' => '14:31',
                'expira' => '16:31',
                'situacao' => 'Ativa'
            ],
            [
                'usuario' => 'João Santos',
                'ip' => '10.0.*.*',
                'dispositivo' => 'Chrome / Android',
                'ultimo' => '14:26',
                'expira' => '16:26',
                'situacao' => 'Ativa'
            ],
            [
                'usuario' => 'Ana Costa',
                'ip' => '10.0.*.*',
                'dispositivo' => 'Edge / Windows',
                'ultimo' => '13:58',
                'expira' => '15:58',
                'situacao' => 'Ativa'
            ]
        ],
        'primary' => 'usuario'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
