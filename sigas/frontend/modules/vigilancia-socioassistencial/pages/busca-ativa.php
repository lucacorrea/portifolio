<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Busca ativa',
    'description' => 'Planejamento e acompanhamento visual de ações territoriais, públicos e pendências.',
    'actions' => [['label' => 'Agenda de campo', 'icon' => 'calendar3'], ['label' => 'Nova ação', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => [
        ['label' => 'Ações em campo', 'value' => '3', 'detail' => 'Ciclo atual', 'icon' => 'person-walking'],
        ['label' => 'Registros localizados', 'value' => '408', 'detail' => 'Dados agregados', 'icon' => 'person-check'],
        ['label' => 'Pendências', 'value' => '82', 'detail' => 'Encaminhamento visual', 'icon' => 'clipboard-x'],
        ['label' => 'Territórios cobertos', 'value' => '6', 'detail' => '75% da programação', 'icon' => 'map'],
    ],
    'search_placeholder' => 'Pesquisar ação, território, público ou equipe',
    'filters' => [
        ['label' => 'Território', 'options' => ['Norte Urbano', 'Sul Urbano', 'Rural e Ribeirinho']],
        ['label' => 'Situação', 'options' => ['Programada', 'Em campo', 'Concluída']],
        ['label' => 'Período', 'options' => ['Jul/2026', 'Agosto de 2026']],
    ],
    'blocks' => [
        ['type' => 'table', 'title' => 'Ações de busca ativa', 'description' => 'Cobertura e pendências apresentadas em totais sintéticos.', 'columns' => [
            ['key' => 'acao', 'label' => 'Ação'], ['key' => 'territorio', 'label' => 'Território'], ['key' => 'publico', 'label' => 'Público'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'equipe', 'label' => 'Equipe'], ['key' => 'registros', 'label' => 'Registros'], ['key' => 'pendencias', 'label' => 'Pendências'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['buscas'], 'primary' => 'acao'],
        ['type' => 'timeline', 'title' => 'Próximas etapas de campo', 'items' => [
            ['date' => '02 AGO', 'title' => 'Reunião de preparação', 'text' => 'Definição dos territórios e materiais da equipe.'],
            ['date' => '05 AGO', 'title' => 'Início da rota ribeirinha', 'text' => 'Saída da equipe volante para cobertura programada.'],
            ['date' => '16 AGO', 'title' => 'Consolidação parcial', 'text' => 'Revisão visual dos registros e pendências.'],
            ['date' => '23 AGO', 'title' => 'Avaliação do ciclo', 'text' => 'Síntese dos resultados territoriais.'],
        ]],
    ],
    'modal' => ['title' => 'Detalhes da ação de busca ativa', 'fields' => ['acao', 'territorio', 'publico', 'periodo', 'equipe', 'registros', 'pendencias', 'situacao']],
]);
