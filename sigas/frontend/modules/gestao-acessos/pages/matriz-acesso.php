<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Matriz de acesso',
 'description' => 'Definição dos módulos disponíveis por setor; exceções individuais devem ser justificadas.',
 'actions' => [
    [
        'label' => 'Editar matriz',
        'icon' => 'grid-3x3-gap-fill',
        'primary' => true
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
            'CREAS',
            'Primeiro Emprego'
        ]
    ]
],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Matriz de acesso',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'setor',
                'label' => 'Setor'
            ],
            [
                'key' => 'kit',
                'label' => 'Kit'
            ],
            [
                'key' => 'aluguel',
                'label' => 'Aluguel'
            ],
            [
                'key' => 'eventuais',
                'label' => 'Eventuais'
            ],
            [
                'key' => 'comida',
                'label' => 'Comida'
            ],
            [
                'key' => 'emprego',
                'label' => '1º Emprego'
            ],
            [
                'key' => 'creas',
                'label' => 'CREAS'
            ]
        ],
        'rows' => [
            [
                'setor' => 'SEMAS Sede',
                'kit' => 'Sim',
                'aluguel' => 'Sim',
                'eventuais' => 'Sim',
                'comida' => 'Sim',
                'emprego' => 'Sim',
                'creas' => 'Conforme perfil'
            ],
            [
                'setor' => 'CRAS 1',
                'kit' => 'Sim',
                'aluguel' => 'Consulta',
                'eventuais' => 'Sim',
                'comida' => 'Sim',
                'emprego' => 'Não',
                'creas' => 'Protegido'
            ],
            [
                'setor' => 'CRAS 2',
                'kit' => 'Sim',
                'aluguel' => 'Consulta',
                'eventuais' => 'Sim',
                'comida' => 'Sim',
                'emprego' => 'Não',
                'creas' => 'Protegido'
            ],
            [
                'setor' => 'Primeiro Emprego',
                'kit' => 'Não',
                'aluguel' => 'Não',
                'eventuais' => 'Consulta histórica',
                'comida' => 'Consulta histórica',
                'emprego' => 'Sim',
                'creas' => 'Não'
            ]
        ],
        'primary' => 'setor'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
