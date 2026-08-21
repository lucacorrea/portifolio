<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Auditoria',
 'description' => 'Rastreabilidade de autenticação, alterações de acesso e ações administrativas.',
 'actions' => [
    [
        'label' => 'Exportar auditoria',
        'icon' => 'download',
        'primary' => true
    ]
],
 'stats' => [

],
 'filters' => [
    [
        'label' => 'Módulo',
        'options' => [
            'Autenticação',
            'Usuários',
            'Governança',
            'Programas'
        ]
    ],
    [
        'label' => 'Período',
        'options' => [
            'Hoje',
            '7 dias',
            '30 dias'
        ]
    ]
],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Auditoria',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'data',
                'label' => 'Data/hora'
            ],
            [
                'key' => 'usuario',
                'label' => 'Usuário'
            ],
            [
                'key' => 'acao',
                'label' => 'Ação'
            ],
            [
                'key' => 'modulo',
                'label' => 'Módulo'
            ],
            [
                'key' => 'alvo',
                'label' => 'Alvo'
            ],
            [
                'key' => 'resultado',
                'label' => 'Resultado'
            ]
        ],
        'rows' => [
            [
                'data' => '21/08/2026 14:18',
                'usuario' => 'Administrador',
                'acao' => 'Alterou matriz de módulos',
                'modulo' => 'Governança',
                'alvo' => 'CRAS 1',
                'resultado' => 'Sucesso'
            ],
            [
                'data' => '21/08/2026 13:52',
                'usuario' => 'Gestor TI',
                'acao' => 'Revogou sessão',
                'modulo' => 'Usuários',
                'alvo' => 'Usuário #42',
                'resultado' => 'Sucesso'
            ],
            [
                'data' => '21/08/2026 12:40',
                'usuario' => 'Administrador',
                'acao' => 'Alterou perfil',
                'modulo' => 'Usuários',
                'alvo' => 'Usuário #51',
                'resultado' => 'Sucesso'
            ]
        ],
        'primary' => 'data'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
