<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['table'];
return sigas_frontend_page([
    'title' => 'Cadastro Único',
    'description' => 'Acompanhamento visual de atualização cadastral, entrevistas e cobertura territorial.',
    'actions' => [['label' => 'Nova entrevista', 'icon' => 'person-plus', 'primary' => true]],
    'stats' => $stats([
        ['Famílias cadastradas', '18.420', 'Base demonstrativa', 'people'],
        ['Atualizadas', '84%', 'Últimos 24 meses', 'check-circle'],
        ['Revisões pendentes', '612', 'Prioridade operacional', 'arrow-repeat'],
        ['Entrevistas agendadas', '96', 'Próximos 7 dias', 'calendar3'],
    ]),
    'filters' => [
        ['label' => 'Situação', 'options' => ['Atualizado', 'Em revisão', 'Pendente']],
        ['label' => 'Zona', 'options' => ['Urbana', 'Rural']],
    ],
    'search_placeholder' => 'Pesquisar referência familiar ou território',
    'blocks' => [
        $table('Fila de atualização', ['referencia' => 'Referência', 'territorio' => 'Território', 'situacao' => 'Situação', 'prazo' => 'Prazo'], [
            ['referencia' => 'FAM-2026-0184', 'territorio' => 'Zona Norte', 'situacao' => 'Em revisão', 'prazo' => '07/08/2026'],
            ['referencia' => 'FAM-2026-0317', 'territorio' => 'Zona Rural', 'situacao' => 'Pendente', 'prazo' => '09/08/2026'],
            ['referencia' => 'FAM-2026-0442', 'territorio' => 'Centro', 'situacao' => 'Atualizado', 'prazo' => 'Concluído'],
        ]),
        ['type' => 'chart', 'title' => 'Atualizações por mês', 'chart' => 'line', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [610, 742, 688, 801, 845, 902]],
    ],
    'modal' => ['title' => 'Detalhes da atualização cadastral', 'fields' => []],
]);
