<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Equipes',
    'description' => 'Composição institucional das equipes por setor e unidade, usando identificadores sintéticos.',
    'actions' => [['label' => 'Mapa de alocação', 'icon' => 'diagram-2'], ['label' => 'Novo vínculo visual', 'icon' => 'person-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Profissionais', 'value' => '146', 'detail' => '12 unidades', 'icon' => 'people'],
        ['label' => 'Equipes completas', 'value' => '9', 'detail' => '75% da rede', 'icon' => 'people-fill'],
        ['label' => 'Em capacitação', 'value' => '18', 'detail' => 'Ciclo atual', 'icon' => 'mortarboard'],
        ['label' => 'Vínculos a revisar', 'value' => '6', 'detail' => 'Sem exposição de dados', 'icon' => 'person-exclamation'],
    ],
    'search_placeholder' => 'Pesquisar identificador, cargo, setor ou unidade',
    'filters' => [
        ['label' => 'Setor', 'options' => ['Proteção Básica', 'Proteção Especial', 'Vigilância']],
        ['label' => 'Unidade', 'options' => ['SEMAS Sede', 'CRAS Norte', 'CREAS']],
        ['label' => 'Situação', 'options' => ['Em atividade', 'Capacitação']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Distribuição por área', 'labels' => ['Serviço Social', 'Psicologia', 'Administrativo', 'Educação Social', 'Dados'], 'values' => [42, 28, 31, 34, 11], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Composição das equipes', 'description' => 'Nomes substituídos por identificadores para proteger dados pessoais.', 'columns' => [
            ['key' => 'servidor', 'label' => 'Servidor'], ['key' => 'cargo', 'label' => 'Cargo'], ['key' => 'setor', 'label' => 'Setor'], ['key' => 'unidade', 'label' => 'Unidade'], ['key' => 'contato', 'label' => 'Contato'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'carga', 'label' => 'Carga horária'],
        ], 'rows' => $data['equipes'], 'primary' => 'servidor'],
    ],
    'modal' => ['title' => 'Vínculo institucional', 'fields' => ['servidor', 'cargo', 'setor', 'unidade', 'contato', 'situacao', 'carga']],
]);
