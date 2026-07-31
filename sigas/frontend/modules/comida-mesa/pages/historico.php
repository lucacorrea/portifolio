<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Histórico',
    'description' => 'Linha do tempo visual de eventos operacionais demonstrativos.',
    'stats' => [
        ['label' => 'Eventos hoje', 'value' => '186', 'detail' => 'Todos os polos', 'icon' => 'activity'],
        ['label' => 'Entregas registradas', 'value' => '142', 'detail' => 'Movimentações do dia', 'icon' => 'box-seam'],
        ['label' => 'Revisões', 'value' => '31', 'detail' => 'Cadastros analisados', 'icon' => 'pencil-square'],
        ['label' => 'Pendências', 'value' => '13', 'detail' => 'Aguardando retorno', 'icon' => 'exclamation-circle'],
    ],
    'filters' => [['label' => 'Evento', 'options' => ['Entrega registrada', 'Cadastro revisado', 'Documento sinalizado']], ['label' => 'Resultado', 'options' => ['Concluído', 'Aprovado', 'Pendência']]],
    'blocks' => [[
        'type' => 'table', 'title' => 'Eventos recentes',
        'columns' => [['key' => 'data', 'label' => 'Data'], ['key' => 'evento', 'label' => 'Evento'], ['key' => 'referencia', 'label' => 'Referência'], ['key' => 'operador', 'label' => 'Operador'], ['key' => 'resultado', 'label' => 'Resultado']],
        'rows' => $data['historico'], 'primary' => 'evento',
    ]],
]);
