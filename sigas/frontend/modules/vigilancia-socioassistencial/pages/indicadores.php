<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Indicadores',
    'description' => 'Catálogo de indicadores socioassistenciais com período, território, tendência e fonte.',
    'actions' => [['label' => 'Comparar períodos', 'icon' => 'arrow-left-right'], ['label' => 'Novo indicador', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => [
        ['label' => 'Indicadores ativos', 'value' => '46', 'detail' => '8 categorias', 'icon' => 'graph-up'],
        ['label' => 'Atualizados no mês', 'value' => '38', 'detail' => '83% do catálogo', 'icon' => 'arrow-repeat'],
        ['label' => 'Tendência de atenção', 'value' => '9', 'detail' => 'Requer análise', 'icon' => 'exclamation-circle'],
        ['label' => 'Fontes catalogadas', 'value' => '7', 'detail' => 'Bases demonstrativas', 'icon' => 'database'],
    ],
    'search_placeholder' => 'Pesquisar indicador, território, categoria ou fonte',
    'filters' => [
        ['label' => 'Período', 'options' => ['Jul/2026', '2º trimestre', '1º semestre']],
        ['label' => 'Território', 'options' => ['Municipal', 'Zona urbana', 'Norte Urbano', 'Sul Urbano']],
        ['label' => 'Categoria', 'options' => ['Cobertura', 'Cadastro', 'Serviços', 'Demanda']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Variação dos indicadores selecionados', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [54, 57, 61, 66, 72, 78], 'chart' => 'line'],
        ['type' => 'table', 'title' => 'Catálogo de indicadores', 'description' => 'Valores agregados e exclusivamente demonstrativos.', 'columns' => [
            ['key' => 'indicador', 'label' => 'Indicador'], ['key' => 'categoria', 'label' => 'Categoria'], ['key' => 'territorio', 'label' => 'Território'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'valor', 'label' => 'Valor atual'], ['key' => 'comparacao', 'label' => 'Comparação'], ['key' => 'tendencia', 'label' => 'Tendência'], ['key' => 'fonte', 'label' => 'Fonte'], ['key' => 'atualizacao', 'label' => 'Atualização'],
        ], 'rows' => $data['indicadores'], 'primary' => 'indicador'],
    ],
    'modal' => ['title' => 'Detalhes do indicador', 'fields' => ['indicador', 'categoria', 'territorio', 'periodo', 'valor', 'comparacao', 'tendencia', 'fonte', 'atualizacao']],
]);
