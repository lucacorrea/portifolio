<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceUsersService $governanceUsers */
$governanceUsers = require dirname(__DIR__) . '/users-bootstrap.php';
$data = $governanceUsers->page();

return sigas_frontend_page([
    'title' => 'Usuários',
    'description' => 'Contas reais do SIGAS com dados administrativos, setor, nível, situação e histórico básico de acesso.',
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
    'search_placeholder' => 'Pesquisar por nome, CPF, e-mail, cargo, setor ou nível',
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Governança',
            'title' => 'Usuários do SIGAS',
            'description' => 'Clique em uma linha para abrir as ações. Nesta etapa, a consulta é somente leitura.',
            'columns' => [
                ['key' => 'usuario', 'label' => 'Usuário'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'cargo', 'label' => 'Cargo'],
                ['key' => 'setor', 'label' => 'Setor'],
                ['key' => 'nivel', 'label' => 'Nível'],
                ['key' => 'ultimo_acesso', 'label' => 'Último acesso'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $data['rows'],
            'primary' => 'usuario',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
