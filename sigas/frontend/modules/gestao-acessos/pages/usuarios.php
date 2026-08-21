<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Usuários',
 'description' => 'Contas, setor, cargo, perfil, situação e último acesso.',
 'actions' => [
    [
        'label' => 'Novo usuário',
        'icon' => 'person-plus',
        'primary' => true
    ],
    [
        'label' => 'Pendentes',
        'icon' => 'hourglass-split'
    ]
],
 'stats' => [

],
 'filters' => [
    [
        'label' => 'Setor',
        'options' => [
            'SEMAS Sede',
            'CRAS 1',
            'CRAS 2',
            'CREAS'
        ]
    ],
    [
        'label' => 'Situação',
        'options' => [
            'Ativo',
            'Pendente',
            'Bloqueado',
            'Inativo'
        ]
    ]
],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Usuários',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'usuario',
                'label' => 'Usuário'
            ],
            [
                'key' => 'cpf',
                'label' => 'CPF'
            ],
            [
                'key' => 'cargo',
                'label' => 'Cargo'
            ],
            [
                'key' => 'setor',
                'label' => 'Setor'
            ],
            [
                'key' => 'perfil',
                'label' => 'Perfil'
            ],
            [
                'key' => 'ultimo',
                'label' => 'Último acesso'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'usuario' => 'Maria Oliveira',
                'cpf' => '***.456.***-**',
                'cargo' => 'Assistente Social',
                'setor' => 'CRAS 1',
                'perfil' => 'Técnico',
                'ultimo' => 'Hoje 14:22',
                'situacao' => 'Ativo'
            ],
            [
                'usuario' => 'João Santos',
                'cpf' => '***.782.***-**',
                'cargo' => 'Atendente',
                'setor' => 'CRAS 2',
                'perfil' => 'Atendente',
                'ultimo' => 'Hoje 13:58',
                'situacao' => 'Ativo'
            ],
            [
                'usuario' => 'Ana Costa',
                'cpf' => '***.219.***-**',
                'cargo' => 'Coordenadora',
                'setor' => 'CREAS',
                'perfil' => 'Gestor',
                'ultimo' => 'Ontem 17:40',
                'situacao' => 'Ativo'
            ]
        ],
        'primary' => 'usuario'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
