<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Documentos',
    'description' => 'Biblioteca visual de planos, relatórios, portarias, atas e documentos institucionais.',
    'actions' => [['label' => 'Organizar categorias', 'icon' => 'tags'], ['label' => 'Adicionar documento', 'icon' => 'file-earmark-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Documentos', 'value' => '184', 'detail' => 'Biblioteca institucional', 'icon' => 'folder2-open'],
        ['label' => 'Publicados', 'value' => '142', 'detail' => 'Acesso interno', 'icon' => 'check-circle'],
        ['label' => 'Em revisão', 'value' => '21', 'detail' => 'Fluxo demonstrativo', 'icon' => 'pencil-square'],
        ['label' => 'Pendentes', 'value' => '7', 'detail' => 'Requerem validação', 'icon' => 'exclamation-circle'],
    ],
    'search_placeholder' => 'Pesquisar documento, categoria ou setor',
    'filters' => [
        ['label' => 'Categoria', 'options' => ['Plano', 'Relatório', 'Ata', 'Portaria']],
        ['label' => 'Setor', 'options' => ['Gestão', 'Planejamento', 'Todos']],
        ['label' => 'Situação', 'options' => ['Publicado', 'Em revisão', 'Pendente', 'Vigente']],
    ],
    'blocks' => [
        ['type' => 'info', 'title' => 'Coleções institucionais', 'description' => 'Ações de abertura e download são apenas visuais nesta etapa.', 'items' => [
            ['icon' => 'journals', 'title' => 'Planos', 'text' => '18 documentos organizados por ciclo.', 'badge' => '18'],
            ['icon' => 'file-earmark-bar-graph', 'title' => 'Relatórios', 'text' => '76 documentos de execução e monitoramento.', 'badge' => '76'],
            ['icon' => 'file-text', 'title' => 'Portarias e atas', 'text' => '54 documentos institucionais.', 'badge' => '54'],
        ]],
        ['type' => 'table', 'title' => 'Documentos recentes', 'description' => 'Versões e situações para controle visual.', 'columns' => [
            ['key' => 'documento', 'label' => 'Documento'], ['key' => 'categoria', 'label' => 'Categoria'], ['key' => 'setor', 'label' => 'Setor'], ['key' => 'data', 'label' => 'Data'], ['key' => 'versao', 'label' => 'Versão'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['documentos'], 'primary' => 'documento'],
    ],
    'modal' => ['title' => 'Detalhes do documento', 'fields' => ['documento', 'categoria', 'setor', 'data', 'versao', 'situacao']],
]);
