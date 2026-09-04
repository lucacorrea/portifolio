<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$data = $governance->levelsPage();

return sigas_frontend_page([
    'title' => 'Níveis de usuário',
    'description' => 'Hierarquia de acesso do SIGAS e quantidade de permissões vinculadas a cada nível.',
    'actions' => [
        [
            'label' => 'Permissões',
            'icon' => 'key',
            'href' => 'governanca-acessos/permissoes.php',
        ],
        [
            'label' => 'Matriz por nível',
            'icon' => 'grid-3x3-gap',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => $data['stats'],
    'filters' => [],
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Autorização',
            'title' => 'Níveis configurados',
            'description' => 'Administrador e Suporte possuem escopo global; os demais níveis atuam dentro do setor autorizado.',
            'columns' => [
                ['key' => 'nivel', 'label' => 'Nível'],
                ['key' => 'prioridade', 'label' => 'Prioridade'],
                ['key' => 'usuarios', 'label' => 'Usuários'],
                ['key' => 'permissoes', 'label' => 'Permissões'],
                ['key' => 'modulos', 'label' => 'Áreas'],
                ['key' => 'escopo', 'label' => 'Escopo'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $data['rows'],
            'primary' => 'nivel',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
