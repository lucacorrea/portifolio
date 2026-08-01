<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Bairros e comunidades',
    'description' => 'Recortes locais com famílias, atendimentos, programas e unidade socioassistencial de referência.',
    'actions' => [['label' => 'Visualizar por zona', 'icon' => 'map'], ['label' => 'Cadastrar recorte visual', 'icon' => 'geo-alt', 'primary' => true]],
    'stats' => [
        ['label' => 'Bairros catalogados', 'value' => '24', 'detail' => 'Atualização territorial', 'icon' => 'buildings'],
        ['label' => 'Comunidades', 'value' => '31', 'detail' => 'Inclui áreas ribeirinhas', 'icon' => 'houses'],
        ['label' => 'Programas presentes', 'value' => '11', 'detail' => 'Cobertura intersetorial', 'icon' => 'grid'],
        ['label' => 'Recortes prioritários', 'value' => '7', 'detail' => 'Monitoramento mensal', 'icon' => 'pin-map'],
    ],
    'search_placeholder' => 'Pesquisar bairro, comunidade, território ou unidade',
    'filters' => [
        ['label' => 'Zona', 'options' => ['Urbana', 'Rural']],
        ['label' => 'Território', 'options' => ['Norte Urbano', 'Sul Urbano', 'Rural e Ribeirinho']],
        ['label' => 'Indicador', 'options' => ['Prioritário', 'Atenção', 'Estável']],
    ],
    'blocks' => [
        ['type' => 'table', 'title' => 'Recortes territoriais', 'description' => 'Visão local sem dados pessoais ou endereços individuais.', 'columns' => [
            ['key' => 'nome', 'label' => 'Nome'], ['key' => 'zona', 'label' => 'Zona'], ['key' => 'territorio', 'label' => 'Território'], ['key' => 'familias', 'label' => 'Famílias'], ['key' => 'atendimentos', 'label' => 'Atendimentos'], ['key' => 'programas', 'label' => 'Programas'], ['key' => 'unidade', 'label' => 'Unidade de referência'], ['key' => 'indicador', 'label' => 'Indicador'],
        ], 'rows' => $data['bairros'], 'primary' => 'nome'],
        ['type' => 'chart', 'title' => 'Atendimentos por recorte', 'labels' => ['Horizonte', 'Bairro das Águas', 'Rio Verde'], 'values' => [114, 92, 46], 'chart' => 'bar'],
    ],
    'modal' => ['title' => 'Perfil do bairro ou comunidade', 'fields' => ['nome', 'zona', 'territorio', 'familias', 'atendimentos', 'programas', 'unidade', 'indicador']],
]);
