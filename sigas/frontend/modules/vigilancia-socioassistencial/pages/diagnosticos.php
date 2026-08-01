<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Diagnósticos',
    'description' => 'Produções técnicas e resumos executivos que orientam prioridades socioassistenciais.',
    'actions' => [['label' => 'Biblioteca', 'icon' => 'folder2-open'], ['label' => 'Novo diagnóstico', 'icon' => 'file-earmark-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Diagnósticos', 'value' => '9', 'detail' => 'Acervo técnico', 'icon' => 'clipboard2-data'],
        ['label' => 'Publicados', 'value' => '6', 'detail' => 'Consulta interna', 'icon' => 'check-circle'],
        ['label' => 'Em revisão', 'value' => '2', 'detail' => 'Ciclo atual', 'icon' => 'pencil-square'],
        ['label' => 'Em validação', 'value' => '1', 'detail' => 'Resumo executivo', 'icon' => 'hourglass-split'],
    ],
    'search_placeholder' => 'Pesquisar título, território, período ou responsável',
    'filters' => [
        ['label' => 'Território', 'options' => ['Municipal', 'Rural e Ribeirinho', 'Zona urbana']],
        ['label' => 'Situação', 'options' => ['Publicado', 'Em revisão', 'Validação']],
        ['label' => 'Período', 'options' => ['2026', '1º semestre', '2º trimestre']],
    ],
    'blocks' => [
        ['type' => 'table', 'title' => 'Produções técnicas', 'description' => 'Versões e etapas de elaboração dos diagnósticos.', 'columns' => [
            ['key' => 'titulo', 'label' => 'Título'], ['key' => 'territorio', 'label' => 'Território'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'versao', 'label' => 'Versão'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'data', 'label' => 'Data'],
        ], 'rows' => $data['diagnosticos'], 'primary' => 'titulo'],
        ['type' => 'info', 'title' => 'Resumo executivo em destaque', 'description' => 'Síntese demonstrativa do diagnóstico municipal.', 'items' => [
            ['icon' => 'people', 'title' => 'Cobertura', 'text' => 'Ampliação do acompanhamento nos territórios prioritários.', 'badge' => 'Tendência positiva'],
            ['icon' => 'exclamation-diamond', 'title' => 'Principal alerta', 'text' => 'Desigualdade de acesso em áreas rurais e ribeirinhas.', 'badge' => 'Alta prioridade'],
            ['icon' => 'signpost-split', 'title' => 'Diretriz recomendada', 'text' => 'Integrar busca ativa, equipe volante e atualização cadastral.'],
        ]],
    ],
    'modal' => ['title' => 'Resumo executivo do diagnóstico', 'fields' => ['titulo', 'territorio', 'periodo', 'responsavel', 'versao', 'situacao', 'data']],
]);
