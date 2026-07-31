<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Documentos',
    'description' => 'Visão de acompanhamento documental, sem realizar uploads ou alterar cadastros.',
    'actions' => [['label' => 'Orientações documentais', 'icon' => 'book']],
    'stats' => [
        ['label' => 'Cadastros completos', 'value' => '4.812', 'detail' => '96,2% da base', 'icon' => 'file-earmark-check'],
        ['label' => 'Pendências', 'value' => '188', 'detail' => 'Revisão demonstrativa', 'icon' => 'file-earmark-excel'],
        ['label' => 'Termos coletados', 'value' => '4.736', 'detail' => 'Competência atual', 'icon' => 'file-earmark-text'],
        ['label' => 'Categorias', 'value' => '3', 'detail' => 'Tipos acompanhados', 'icon' => 'folder2-open'],
    ],
    'filters' => [['label' => 'Categoria', 'options' => ['Inscrição', 'Elegibilidade', 'Entrega']], ['label' => 'Situação', 'options' => ['Monitorado', 'Em coleta']]],
    'blocks' => [[
        'type' => 'table', 'title' => 'Controle documental',
        'columns' => [['key' => 'documento', 'label' => 'Documento'], ['key' => 'categoria', 'label' => 'Categoria'], ['key' => 'familias', 'label' => 'Famílias'], ['key' => 'pendencias', 'label' => 'Pendências'], ['key' => 'validade', 'label' => 'Validade'], ['key' => 'situacao', 'label' => 'Situação']],
        'rows' => $data['documentos'], 'primary' => 'documento',
    ]],
]);
