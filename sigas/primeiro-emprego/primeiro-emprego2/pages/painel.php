<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Painel',
    'description' => 'Visão consolidada dos candidatos, oportunidades, lotações e acompanhamentos do programa.',
    'actions' => [
        ['label' => 'Novo candidato', 'icon' => 'person-plus', 'primary' => true],
        ['label' => 'Nova oportunidade', 'icon' => 'briefcase'],
    ],
    'stats' => pe_demo_stats('painel'),
    'blocks' => [
        [
            'type' => 'chart',
            'kicker' => 'Evolução mensal',
            'title' => 'Candidatos e movimentações',
            'chart' => 'line',
            'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'],
            'values' => [118, 136, 152, 161, 184, 207],
        ],
        [
            'type' => 'info',
            'kicker' => 'Acompanhamento operacional',
            'title' => 'Pendências do programa',
            'description' => 'Pontos que exigem conferência da equipe.',
            'items' => [
                ['icon' => 'calendar-check', 'title' => 'Frequência pendente', 'text' => '14 participantes aguardam fechamento.', 'badge' => '14'],
                ['icon' => 'wallet2', 'title' => 'Bolsas em processamento', 'text' => '148 registros na competência atual.', 'badge' => '148'],
                ['icon' => 'mortarboard', 'title' => 'Capacitações', 'text' => '7 turmas estão em andamento.', 'badge' => '7'],
                ['icon' => 'clipboard2-pulse', 'title' => 'Acompanhamentos', 'text' => '24 participantes possuem próxima ação.', 'badge' => '24'],
            ],
        ],
        [
            'type' => 'agenda',
            'kicker' => 'Agenda e movimentações',
            'title' => 'Próximos compromissos',
            'items' => pe_demo_movements(),
        ],
    ],
    'modal' => ['title' => 'Resumo do painel'],
];
