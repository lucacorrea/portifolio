<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Permissões',
 'description' => 'Catálogo de ações autorizáveis por módulo e perfil.',
 'actions' => [
    [
        'label' => 'Nova permissão',
        'icon' => 'key-fill',
        'primary' => true
    ]
],
 'stats' => [

],
 'filters' => [
    [
        'label' => 'Módulo',
        'options' => [
            'Governança',
            'Kit Maternidade',
            'Aluguel Social',
            'Benefícios Eventuais',
            'Comida na Mesa',
            'Primeiro Emprego'
        ]
    ]
],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Permissões',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'permissao',
                'label' => 'Permissão'
            ],
            [
                'key' => 'slug',
                'label' => 'Slug'
            ],
            [
                'key' => 'modulo',
                'label' => 'Módulo'
            ],
            [
                'key' => 'perfis',
                'label' => 'Perfis'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'permissao' => 'Visualizar Kit Maternidade',
                'slug' => 'kit_maternidade.visualizar',
                'modulo' => 'Kit Maternidade',
                'perfis' => 'Gestor, Técnico, Leitura',
                'situacao' => 'Ativa'
            ],
            [
                'permissao' => 'Entregar Kit Maternidade',
                'slug' => 'kit_maternidade.entregar',
                'modulo' => 'Kit Maternidade',
                'perfis' => 'Gestor, Técnico',
                'situacao' => 'Ativa'
            ],
            [
                'permissao' => 'Gerenciar Aluguel Social',
                'slug' => 'aluguel_social.gerenciar',
                'modulo' => 'Aluguel Social',
                'perfis' => 'Gestor, Técnico',
                'situacao' => 'Ativa'
            ],
            [
                'permissao' => 'Gerenciar módulos por setor',
                'slug' => 'governanca.modulos',
                'modulo' => 'Governança',
                'perfis' => 'Administrador',
                'situacao' => 'Ativa'
            ]
        ],
        'primary' => 'permissao'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
