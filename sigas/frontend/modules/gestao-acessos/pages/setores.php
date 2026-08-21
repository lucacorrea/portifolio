<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Setores',
 'description' => 'Unidades e setores organizacionais usados para escopo operacional e visibilidade de módulos.',
 'actions' => [
    [
        'label' => 'Novo setor',
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
        'title' => 'Setores',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'setor',
                'label' => 'Setor'
            ],
            [
                'key' => 'slug',
                'label' => 'Identificador'
            ],
            [
                'key' => 'usuarios',
                'label' => 'Usuários'
            ],
            [
                'key' => 'modulos',
                'label' => 'Módulos liberados'
            ],
            [
                'key' => 'perfil_pred',
                'label' => 'Perfil predominante'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'setor' => 'SEMAS — Sede Administrativa',
                'slug' => 'semas-sede',
                'usuarios' => '18',
                'modulos' => 'Todos autorizados',
                'perfil_pred' => 'Gestão',
                'situacao' => 'Ativo'
            ],
            [
                'setor' => 'CRAS 1',
                'slug' => 'cras-1',
                'usuarios' => '15',
                'modulos' => '7',
                'perfil_pred' => 'Técnico',
                'situacao' => 'Ativo'
            ],
            [
                'setor' => 'CRAS 2',
                'slug' => 'cras-2',
                'usuarios' => '14',
                'modulos' => '7',
                'perfil_pred' => 'Técnico',
                'situacao' => 'Ativo'
            ],
            [
                'setor' => 'CREAS',
                'slug' => 'creas',
                'usuarios' => '11',
                'modulos' => '3 + protegidos',
                'perfil_pred' => 'Técnico',
                'situacao' => 'Ativo'
            ]
        ],
        'primary' => 'setor'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
