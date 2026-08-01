<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Metas',
    'description' => 'Acompanhe entregas, responsáveis, prioridades e progresso das metas institucionais.',
    'actions' => [['label' => 'Revisar prioridades', 'icon' => 'sliders'], ['label' => 'Nova meta', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => [
        ['label' => 'Metas do ciclo', 'value' => '55', 'detail' => 'Todos os planos', 'icon' => 'bullseye'],
        ['label' => 'No prazo', 'value' => '37', 'detail' => '67% do total', 'icon' => 'check-circle'],
        ['label' => 'A vencer', 'value' => '8', 'detail' => 'Próximos 30 dias', 'icon' => 'clock'],
        ['label' => 'Atrasadas', 'value' => '4', 'detail' => 'Plano de resposta', 'icon' => 'exclamation-triangle'],
    ],
    'search_placeholder' => 'Pesquisar meta, plano ou responsável',
    'filters' => [
        ['label' => 'Prioridade', 'options' => ['Crítica', 'Alta', 'Média']],
        ['label' => 'Status', 'options' => ['Em andamento', 'Concluída', 'Atrasada']],
        ['label' => 'Plano', 'options' => ['Plano Municipal', 'Qualificação da Rede', 'Fortalecimento da Vigilância']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Progresso consolidado por prioridade', 'labels' => ['Crítica', 'Alta', 'Média', 'Baixa'], 'values' => [44, 69, 76, 84], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Metas em acompanhamento', 'description' => 'Progresso expresso em percentual para leitura também nos cards móveis.', 'columns' => [
            ['key' => 'meta', 'label' => 'Meta'], ['key' => 'plano', 'label' => 'Plano'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'prazo', 'label' => 'Prazo'], ['key' => 'progresso', 'label' => 'Progresso'], ['key' => 'prioridade', 'label' => 'Prioridade'], ['key' => 'status', 'label' => 'Status'], ['key' => 'atualizacao', 'label' => 'Atualização'],
        ], 'rows' => $data['metas'], 'primary' => 'meta'],
    ],
    'modal' => ['title' => 'Detalhes da meta', 'fields' => ['meta', 'plano', 'responsavel', 'prazo', 'progresso', 'prioridade', 'status', 'atualizacao']],
]);
