<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Cargos',
 'description' => 'Catálogo institucional de cargos e funções utilizados nos usuários.',
 'actions' => [
    [
        'label' => 'Novo cargo',
        'icon' => 'plus-circle',
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
        'title' => 'Cargos',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'cargo',
                'label' => 'Cargo'
            ],
            [
                'key' => 'slug',
                'label' => 'Identificador'
            ],
            [
                'key' => 'descricao',
                'label' => 'Descrição'
            ],
            [
                'key' => 'usuarios',
                'label' => 'Usuários'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'cargo' => 'Assistente Social',
                'slug' => 'assistente-social',
                'descricao' => 'Acompanhamento e parecer social',
                'usuarios' => '18',
                'situacao' => 'Ativo'
            ],
            [
                'cargo' => 'Atendente',
                'slug' => 'atendente',
                'descricao' => 'Atendimento e cadastro básico',
                'usuarios' => '24',
                'situacao' => 'Ativo'
            ],
            [
                'cargo' => 'Coordenador(a)',
                'slug' => 'coordenador',
                'descricao' => 'Coordenação de unidade/programa',
                'usuarios' => '9',
                'situacao' => 'Ativo'
            ]
        ],
        'primary' => 'cargo'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
