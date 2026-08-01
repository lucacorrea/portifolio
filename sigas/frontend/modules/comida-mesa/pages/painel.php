<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Painel',
    'description' => 'Visão executiva da operação mensal do Coari Comida na Mesa, sem interferir no fluxo funcional.',
    'actions' => [['label' => 'Abrir operação funcional', 'icon' => 'basket2'], ['label' => 'Resumo da competência', 'icon' => 'file-earmark-text']],
    'stats' => [
        ['label' => 'Famílias previstas', 'value' => '5.000', 'detail' => 'Competência atual', 'icon' => 'people'],
        ['label' => 'Entregas realizadas', 'value' => '4.736', 'detail' => '94,7% da meta', 'icon' => 'box-seam'],
        ['label' => 'Aguardando retirada', 'value' => '264', 'detail' => 'Distribuídas em 8 polos', 'icon' => 'hourglass-split'],
        ['label' => 'Pendências documentais', 'value' => '188', 'detail' => 'Revisão necessária', 'icon' => 'file-earmark-excel'],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Evolução das entregas', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [4210, 4380, 4515, 4690, 4881, 4736], 'chart' => 'line'],
        ['type' => 'info', 'title' => 'Situação operacional', 'items' => [
            ['title' => 'Polos em atendimento', 'text' => 'Oito polos com cronogramas demonstrativos acompanhados.', 'icon' => 'geo-alt', 'badge' => '8 ativos'],
            ['title' => 'Próximo encerramento', 'text' => 'Conferência visual prevista para 31 de julho.', 'icon' => 'calendar-event', 'badge' => '2 dias'],
            ['title' => 'Acesso ao back-end', 'text' => 'Beneficiários, inscrição, consulta e entrega permanecem nas páginas funcionais.', 'icon' => 'shield-check', 'badge' => 'Preservado'],
        ]],
        ['type' => 'timeline', 'title' => 'Últimas movimentações', 'items' => array_map(static fn (array $row): array => ['date' => $row['data'], 'title' => $row['evento'], 'text' => $row['referencia'] . ' · ' . $row['resultado']], $data['historico'])],
    ],
]);
