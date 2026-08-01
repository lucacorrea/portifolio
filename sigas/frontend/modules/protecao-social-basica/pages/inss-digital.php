<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['table'];
return sigas_frontend_page([
    'title' => 'INSS Digital',
    'description' => 'Controle visual de orientações, protocolos e pendências documentais do atendimento assistido.',
    'actions' => [['label' => 'Novo atendimento', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => $stats([
        ['Atendimentos no mês', '196', 'Orientação assistida', 'laptop'],
        ['Protocolos acompanhados', '84', 'Em andamento', 'file-earmark-text'],
        ['Pendências documentais', '27', 'Requer orientação', 'file-earmark-excel'],
        ['Concluídos', '112', 'No mês', 'check-circle'],
    ]),
    'filters' => [
        ['label' => 'Serviço', 'options' => ['Orientação', 'Protocolo', 'Consulta']],
        ['label' => 'Situação', 'options' => ['Em andamento', 'Pendente', 'Concluído']],
    ],
    'blocks' => [
        $table('Atendimentos assistidos', ['referencia' => 'Protocolo', 'servico' => 'Serviço', 'data' => 'Data', 'situacao' => 'Situação'], [
            ['referencia' => 'INSS-D-0814', 'servico' => 'Orientação', 'data' => '01/08/2026', 'situacao' => 'Concluído'],
            ['referencia' => 'INSS-D-0831', 'servico' => 'Protocolo', 'data' => '02/08/2026', 'situacao' => 'Em andamento'],
            ['referencia' => 'INSS-D-0846', 'servico' => 'Consulta', 'data' => '03/08/2026', 'situacao' => 'Pendente'],
        ]),
        ['type' => 'info', 'title' => 'Orientações de atendimento', 'items' => [
            ['icon' => 'shield-check', 'title' => 'Privacidade', 'text' => 'Conferir consentimento antes de acessar serviços externos.'],
            ['icon' => 'file-check', 'title' => 'Documentação', 'text' => 'Validar a lista mínima conforme o serviço solicitado.'],
            ['icon' => 'clock-history', 'title' => 'Acompanhamento', 'text' => 'Registrar somente referências necessárias ao retorno.'],
        ]],
    ],
    'modal' => ['title' => 'Detalhes do atendimento assistido', 'fields' => []],
]);
