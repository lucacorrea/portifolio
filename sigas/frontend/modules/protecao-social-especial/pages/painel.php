<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Painel',
    'description' => 'Visão agregada da Proteção Social Especial, sem exposição de dados pessoais ou relatos sensíveis.',
    'stats' => $stats([
        ['Acompanhamentos ativos', '286', 'Total agregado', 'clipboard2-pulse'],
        ['Novos registros no mês', '47', 'Sem identificação pessoal', 'inboxes'],
        ['Encaminhamentos abertos', '93', 'Rede especializada', 'send'],
        ['Retornos no prazo', '88%', 'Indicador consolidado', 'check-circle'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Demandas por eixo de proteção', 'chart' => 'bar', 'labels' => ['Família', 'Infância', 'Mulher', 'Pessoa idosa', 'PcD', 'Outros'], 'values' => [82, 61, 49, 34, 28, 32]],
        ['type' => 'info', 'title' => 'Monitoramento protegido', 'description' => 'Somente totais consolidados são apresentados neste painel.', 'items' => [
            ['icon' => 'shield-lock', 'title' => 'Acesso restrito', 'text' => 'Detalhes dependem de autorização específica no servidor.', 'badge' => 'Protegido'],
            ['icon' => 'clock-history', 'title' => 'Revisões pendentes', 'text' => '18 acompanhamentos aguardam revisão técnica.'],
            ['icon' => 'diagram-3', 'title' => 'Rede acionada', 'text' => '32 articulações intersetoriais em andamento.'],
        ]],
        ['type' => 'timeline', 'title' => 'Agenda técnica', 'items' => [
            ['date' => '05 ago', 'title' => 'Reunião de casos — equipe restrita', 'text' => 'Pauta exibida sem nomes ou detalhes sensíveis.'],
            ['date' => '08 ago', 'title' => 'Articulação da rede', 'text' => 'Alinhamento de fluxos de proteção.'],
            ['date' => '12 ago', 'title' => 'Monitoramento de indicadores', 'text' => 'Revisão dos totais agregados do período.'],
        ]],
    ],
]);
