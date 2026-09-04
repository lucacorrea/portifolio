<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$data = $governance->dashboard();

ob_start();
?>
<section class="content-card mt-3">
    <div class="card-heading">
        <div>
            <div class="card-kicker">Modelo de autorização</div>
            <h2>Nível define ações; setor limita o contexto operacional</h2>
            <p>
                Administrador e suporte possuem visão global. Os demais usuários recebem
                permissões pelo nível de acesso e atuam dentro do setor autorizado.
                Exceções individuais devem permanecer justificadas e auditáveis.
            </p>
        </div>
        <span class="status-badge status-success">
            <i class="bi bi-shield-check"></i>
            Menor privilégio
        </span>
    </div>

    <div class="program-flow-banner">
        <div class="program-flow-step"><small>1</small><strong>Usuário</strong><span>Identidade única</span></div>
        <div class="program-flow-step"><small>2</small><strong>Setor</strong><span>Contexto operacional</span></div>
        <div class="program-flow-step"><small>3</small><strong>Nível</strong><span>Ações autorizadas</span></div>
        <div class="program-flow-step"><small>4</small><strong>Matriz</strong><span>Permissões por área</span></div>
        <div class="program-flow-step"><small>5</small><strong>Auditoria</strong><span>Rastreabilidade</span></div>
    </div>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();

$recentUsers = [];
foreach ($data['recent_users'] as $row) {
    $recentUsers[] = [
        'usuario' => (string) ($row['usuario'] ?? 'Usuário'),
        'setor' => (string) ($row['setor'] ?? 'Sem setor'),
        'nivel' => (string) ($row['nivel'] ?? 'Sem nível'),
        'ultimo' => (string) ($row['ultimo'] ?? 'Nunca'),
        'situacao' => (string) ($row['situacao'] ?? 'Não definido'),
    ];
}

$levelRows = [];
foreach ($data['levels'] as $row) {
    $levelRows[] = [
        'nivel' => (string) ($row['nivel'] ?? 'Nível'),
        'usuarios' => (string) ($row['usuarios'] ?? '0'),
        'permissoes' => (string) ($row['permissoes'] ?? '0'),
        'modulos' => (string) ($row['modulos'] ?? '0'),
        'escopo' => (string) ($row['escopo'] ?? 'Setor'),
        'situacao' => (string) ($row['situacao'] ?? 'Ativo'),
    ];
}

return sigas_frontend_page([
    'title' => 'Governança e Acessos',
    'description' => 'Administração central de usuários, níveis, permissões, sessões e regras de acesso do SIGAS.',
    'actions' => [
        [
            'label' => 'Usuários',
            'icon' => 'people',
            'href' => 'governanca-acessos/usuarios.php',
        ],
        [
            'label' => 'Revisar matriz',
            'icon' => 'shield-check',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => $data['stats'],
    'filters' => [],
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Contas',
            'title' => 'Usuários em destaque',
            'description' => 'Contas ordenadas com prioridade para pendências e bloqueios.',
            'columns' => [
                ['key' => 'usuario', 'label' => 'Usuário'],
                ['key' => 'setor', 'label' => 'Setor'],
                ['key' => 'nivel', 'label' => 'Nível'],
                ['key' => 'ultimo', 'label' => 'Último acesso'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $recentUsers,
            'primary' => 'usuario',
        ],
        [
            'type' => 'table',
            'kicker' => 'Autorização',
            'title' => 'Níveis de usuário',
            'description' => 'Resumo dos níveis cadastrados e do volume de permissões atualmente associado.',
            'columns' => [
                ['key' => 'nivel', 'label' => 'Nível'],
                ['key' => 'usuarios', 'label' => 'Usuários'],
                ['key' => 'permissoes', 'label' => 'Permissões'],
                ['key' => 'modulos', 'label' => 'Áreas'],
                ['key' => 'escopo', 'label' => 'Escopo'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $levelRows,
            'primary' => 'nivel',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
