<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
 'title' => 'Perfis e níveis',
 'description' => 'Níveis de acesso que agrupam permissões e limites de atuação.',
 'actions' => [
    [
        'label' => 'Novo perfil',
        'icon' => 'person-plus',
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
        'title' => 'Perfis e níveis',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'perfil',
                'label' => 'Perfil'
            ],
            [
                'key' => 'prioridade',
                'label' => 'Prioridade'
            ],
            [
                'key' => 'usuarios',
                'label' => 'Usuários'
            ],
            [
                'key' => 'permissoes',
                'label' => 'Permissões'
            ],
            [
                'key' => 'escopo',
                'label' => 'Escopo'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'perfil' => 'Administrador',
                'prioridade' => '10',
                'usuarios' => '2',
                'permissoes' => 'Todas',
                'escopo' => 'Global',
                'situacao' => 'Ativo'
            ],
            [
                'perfil' => 'Gestor',
                'prioridade' => '30',
                'usuarios' => '12',
                'permissoes' => 'Gestão',
                'escopo' => 'Setor',
                'situacao' => 'Ativo'
            ],
            [
                'perfil' => 'Técnico',
                'prioridade' => '40',
                'usuarios' => '34',
                'permissoes' => 'Técnicas',
                'escopo' => 'Setor',
                'situacao' => 'Ativo'
            ],
            [
                'perfil' => 'Atendente',
                'prioridade' => '50',
                'usuarios' => '24',
                'permissoes' => 'Cadastro/consulta',
                'escopo' => 'Setor',
                'situacao' => 'Ativo'
            ]
        ],
        'primary' => 'perfil'
    ]
],
 'demo' => true,
 'show_states' => true,
]);
