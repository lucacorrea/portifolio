<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Rede de unidades',
    'description' => 'Cadastro visual da estrutura física, responsáveis, equipes e serviços da rede socioassistencial.',
    'actions' => [['label' => 'Visualizar rede', 'icon' => 'diagram-3'], ['label' => 'Nova unidade', 'icon' => 'building-add', 'primary' => true]],
    'stats' => [
        ['label' => 'Unidades ativas', 'value' => '12', 'detail' => 'Rede municipal', 'icon' => 'buildings'],
        ['label' => 'Serviços ofertados', 'value' => '28', 'detail' => 'Em todos os territórios', 'icon' => 'grid'],
        ['label' => 'Servidores vinculados', 'value' => '146', 'detail' => 'Dados demonstrativos', 'icon' => 'people'],
        ['label' => 'Atualização pendente', 'value' => '2', 'detail' => 'Cadastro institucional', 'icon' => 'arrow-repeat'],
    ],
    'search_placeholder' => 'Pesquisar unidade, tipo, território ou serviço',
    'filters' => [
        ['label' => 'Tipo', 'options' => ['Gestão', 'CRAS', 'CREAS']],
        ['label' => 'Situação', 'options' => ['Ativa', 'Atenção']],
        ['label' => 'Território', 'options' => ['Área central', 'Território Norte', 'Território Sul']],
    ],
    'blocks' => [
        ['type' => 'table', 'title' => 'Unidades da rede', 'description' => 'Contatos e capacidade apresentados sem dados pessoais sensíveis.', 'columns' => [
            ['key' => 'unidade', 'label' => 'Unidade'], ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'endereco', 'label' => 'Endereço'], ['key' => 'telefone', 'label' => 'Telefone'], ['key' => 'servidores', 'label' => 'Servidores'], ['key' => 'servicos', 'label' => 'Serviços'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'atualizacao', 'label' => 'Atualização'],
        ], 'rows' => $data['unidades'], 'primary' => 'unidade'],
        ['type' => 'chart', 'title' => 'Capacidade por tipo de unidade', 'labels' => ['Gestão', 'CRAS', 'CREAS', 'Convivência', 'Acolhimento'], 'values' => [28, 46, 24, 32, 16], 'chart' => 'bar'],
    ],
    'modal' => ['title' => 'Resumo da unidade', 'fields' => ['unidade', 'tipo', 'responsavel', 'endereco', 'telefone', 'servidores', 'servicos', 'situacao']],
]);
