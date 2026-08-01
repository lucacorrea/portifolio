<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Painel',
    'description' => 'Leitura territorial agregada de cobertura, vulnerabilidades, busca ativa e diagnósticos.',
    'actions' => [['label' => 'Atualizar leitura', 'icon' => 'arrow-repeat'], ['label' => 'Novo diagnóstico', 'icon' => 'clipboard-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Famílias acompanhadas', 'value' => '3.842', 'detail' => '+4,8% no período', 'icon' => 'people'],
        ['label' => 'Territórios monitorados', 'value' => '8', 'detail' => 'Urbano e rural', 'icon' => 'map'],
        ['label' => 'Alertas de vulnerabilidade', 'value' => '12', 'detail' => '4 de alta prioridade', 'icon' => 'exclamation-diamond'],
        ['label' => 'Buscas ativas', 'value' => '6', 'detail' => '3 em campo', 'icon' => 'search'],
        ['label' => 'Diagnósticos', 'value' => '9', 'detail' => '2 em validação', 'icon' => 'clipboard2-data'],
        ['label' => 'Bairros prioritários', 'value' => '7', 'detail' => 'Revisão mensal', 'icon' => 'geo-alt'],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Evolução mensal do acompanhamento', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [3320, 3415, 3528, 3610, 3702, 3842], 'chart' => 'line'],
        ['type' => 'chart', 'title' => 'Indicadores por território', 'labels' => ['Norte', 'Sul', 'Central', 'Rural'], 'values' => [82, 64, 58, 76], 'chart' => 'bar'],
        ['type' => 'info', 'title' => 'Mapa territorial demonstrativo', 'description' => 'Representação visual por zonas, sem chave ou serviço cartográfico externo.', 'items' => [
            ['icon' => 'geo-fill', 'title' => 'Norte Urbano', 'text' => 'Alta concentração de famílias acompanhadas.', 'badge' => 'Prioritário'],
            ['icon' => 'geo-fill', 'title' => 'Sul Urbano', 'text' => 'Cobertura estável e cadastros em atualização.', 'badge' => 'Monitorado'],
            ['icon' => 'geo-fill', 'title' => 'Rural e Ribeirinho', 'text' => 'Acesso territorial como principal alerta.', 'badge' => 'Prioritário'],
        ]],
        ['type' => 'info', 'title' => 'Alertas recentes', 'items' => [
            ['icon' => 'exclamation-triangle', 'title' => 'Demanda acima da capacidade', 'text' => 'Serviço de Convivência no território Sul.', 'badge' => 'Hoje'],
            ['icon' => 'person-walking', 'title' => 'Busca ativa em campo', 'text' => 'Cobertura de comunidades ribeirinhas em andamento.', 'badge' => 'Em campo'],
            ['icon' => 'clipboard-check', 'title' => 'Diagnóstico atualizado', 'text' => 'Leitura territorial rural publicada para consulta.', 'badge' => '20/07'],
        ]],
    ],
]);
