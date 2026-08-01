<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Cronogramas',
    'description' => 'Visão mensal de reuniões, entregas e prazos vinculados ao planejamento institucional.',
    'actions' => [['label' => 'Hoje', 'icon' => 'calendar-check'], ['label' => 'Novo compromisso', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Compromissos no mês', 'value' => '24', 'detail' => 'Agenda consolidada', 'icon' => 'calendar3'],
        ['label' => 'Entregas', 'value' => '9', 'detail' => '4 nesta semana', 'icon' => 'box-arrow-in-down'],
        ['label' => 'Reuniões', 'value' => '7', 'detail' => '2 intersetoriais', 'icon' => 'people'],
        ['label' => 'Prazos críticos', 'value' => '3', 'detail' => 'Exigem confirmação', 'icon' => 'alarm'],
    ],
    'filters' => [
        ['label' => 'Setor', 'options' => ['Planejamento', 'Vigilância', 'Proteção Básica', 'Proteção Especial']],
        ['label' => 'Período', 'options' => ['Esta semana', 'Agosto de 2026', 'Setembro de 2026']],
        ['label' => 'Tipo', 'options' => ['Reunião', 'Entrega', 'Prazo', 'Oficina']],
    ],
    'blocks' => [
        ['type' => 'info', 'title' => 'Agosto de 2026', 'description' => 'Resumo visual da ocupação do calendário.', 'items' => [
            ['icon' => 'calendar-event', 'title' => 'Semana 1', 'text' => '4 compromissos · 1 entrega crítica', 'badge' => '01–07'],
            ['icon' => 'calendar-event', 'title' => 'Semana 2', 'text' => '7 compromissos · 2 reuniões', 'badge' => '08–14'],
            ['icon' => 'calendar-event', 'title' => 'Semana 3', 'text' => '6 compromissos · oficina técnica', 'badge' => '15–21'],
            ['icon' => 'calendar-event', 'title' => 'Semana 4', 'text' => '7 compromissos · fechamento mensal', 'badge' => '22–31'],
        ]],
        ['type' => 'timeline', 'title' => 'Agenda em lista', 'items' => $data['cronograma']],
    ],
]);
