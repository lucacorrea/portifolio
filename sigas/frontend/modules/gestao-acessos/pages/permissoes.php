<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$data = $governance->permissionsPage();

return sigas_frontend_page([
    'title' => 'Permissões',
    'description' => 'Catálogo real de ações autorizáveis e níveis aos quais cada permissão está vinculada.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Matriz de acesso',
            'icon' => 'grid-3x3-gap',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => $data['stats'],
    'filters' => $data['filters'],
    'search_placeholder' => 'Pesquisar permissão, slug, módulo ou nível',
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Autorização',
            'title' => 'Permissões cadastradas',
            'description' => 'As permissões são agrupadas por módulo e vinculadas aos níveis por meio de nivel_permissoes.',
            'columns' => [
                ['key' => 'permissao', 'label' => 'Permissão'],
                ['key' => 'slug', 'label' => 'Slug'],
                ['key' => 'modulo', 'label' => 'Módulo'],
                ['key' => 'niveis', 'label' => 'Níveis'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $data['rows'],
            'primary' => 'permissao',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
