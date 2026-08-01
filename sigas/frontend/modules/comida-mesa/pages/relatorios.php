<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Central visual de consultas consolidadas e exportações demonstrativas.',
    'actions' => [['label' => 'Gerar relatório visual', 'icon' => 'file-earmark-bar-graph', 'primary' => true], ['label' => 'Exportar demonstração', 'icon' => 'download']],
    'stats' => [
        ['label' => 'Modelos disponíveis', 'value' => '8', 'detail' => 'Operação e gestão', 'icon' => 'files'],
        ['label' => 'Relatórios recentes', 'value' => '12', 'detail' => 'Últimos 30 dias', 'icon' => 'clock-history'],
        ['label' => 'Competências analisadas', 'value' => '7', 'detail' => 'Ano de 2026', 'icon' => 'calendar3'],
        ['label' => 'Polos consolidados', 'value' => '8', 'detail' => 'Cobertura atual', 'icon' => 'geo-alt'],
    ],
    'filters' => [['label' => 'Tipo', 'options' => ['Entregas', 'Beneficiários', 'Polos', 'Pendências']], ['label' => 'Período', 'options' => ['Mês atual', 'Último trimestre', 'Ano atual']]],
    'blocks' => [
        ['type' => 'info', 'title' => 'Catálogo de relatórios', 'items' => [
            ['title' => 'Execução por competência', 'text' => 'Famílias previstas, entregas e percentual executado.', 'icon' => 'bar-chart'],
            ['title' => 'Cobertura por polo', 'text' => 'Capacidade, famílias vinculadas e situação do atendimento.', 'icon' => 'pin-map'],
            ['title' => 'Pendências documentais', 'text' => 'Resumo agregado sem exposição de documentos pessoais.', 'icon' => 'file-earmark-excel'],
        ]],
        ['type' => 'chart', 'title' => 'Execução das competências', 'labels' => array_column($data['competencias'], 'competencia'), 'values' => [94.7, 99.2, 0], 'chart' => 'bar'],
    ],
]);
