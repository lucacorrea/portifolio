<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$data = $governance->accessMatrixPage();

ob_start();
?>
<section class="content-card mt-3">
    <div class="card-heading">
        <div>
            <div class="card-kicker">Como ler a matriz</div>
            <h2>Nível + setor + exceção individual</h2>
            <p>
                Esta visão mostra quantas permissões cada nível possui em cada área.
                O acesso efetivo do usuário também considera o setor e, quando existir,
                uma exceção individual registrada para o módulo.
            </p>
        </div>
        <span class="status-badge status-success">
            <i class="bi bi-shield-lock"></i>
            Controle central
        </span>
    </div>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Matriz de acesso',
    'description' => 'Visão consolidada do que cada nível de usuário pode executar no SIGAS.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Permissões',
            'icon' => 'key',
            'primary' => true,
            'href' => 'governanca-acessos/permissoes.php',
        ],
    ],
    'stats' => $data['stats'],
    'filters' => [],
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Autorização',
            'title' => 'Níveis × áreas de permissão',
            'description' => '“Liberado · N” indica a quantidade de permissões concedidas ao nível naquela área.',
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'primary' => 'nivel',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
