<?php

declare(strict_types=1);

return sigas_frontend_page([
    'title' => 'Mapas',
    'description' => 'Mapa territorial demonstrativo com camadas, legenda e filtros, sem dependência de API externa.',
    'actions' => [['label' => 'Centralizar mapa', 'icon' => 'crosshair'], ['label' => 'Configurar camadas', 'icon' => 'layers', 'primary' => true]],
    'stats' => [
        ['label' => 'Camadas disponíveis', 'value' => '6', 'detail' => 'Visualização local', 'icon' => 'layers'],
        ['label' => 'Bairros representados', 'value' => '24', 'detail' => 'Zona urbana', 'icon' => 'buildings'],
        ['label' => 'Unidades marcadas', 'value' => '12', 'detail' => 'Rede socioassistencial', 'icon' => 'geo-alt'],
        ['label' => 'Áreas prioritárias', 'value' => '7', 'detail' => 'Legenda de atenção', 'icon' => 'pin-map-fill'],
    ],
    'filters' => [
        ['label' => 'Território', 'options' => ['Norte Urbano', 'Sul Urbano', 'Rural e Ribeirinho']],
        ['label' => 'Camada', 'options' => ['Vulnerabilidades', 'Unidades', 'Cobertura', 'Busca ativa']],
        ['label' => 'Prioridade', 'options' => ['Alta', 'Média', 'Estável']],
    ],
    'blocks' => [
        ['type' => 'info', 'title' => 'Painel lateral e camadas', 'description' => 'Representação sem mapas externos ou chaves de integração.', 'items' => [
            ['icon' => 'square-fill', 'title' => 'Vulnerabilidade alta', 'text' => 'Áreas sintéticas destacadas em tom de alerta.', 'badge' => '7 áreas'],
            ['icon' => 'house-heart', 'title' => 'Unidades socioassistenciais', 'text' => 'CRAS, CREAS e unidades de gestão.', 'badge' => '12 pontos'],
            ['icon' => 'person-walking', 'title' => 'Busca ativa', 'text' => 'Rotas demonstrativas de cobertura territorial.', 'badge' => '3 rotas'],
            ['icon' => 'water', 'title' => 'Comunidades ribeirinhas', 'text' => 'Recortes territoriais agregados.', 'badge' => '18'],
        ]],
        ['type' => 'chart', 'title' => 'Intensidade territorial demonstrativa', 'labels' => ['Norte', 'Sul', 'Central', 'Rural'], 'values' => [82, 64, 48, 76], 'chart' => 'bar'],
        ['type' => 'info', 'title' => 'Território selecionado: Norte Urbano', 'description' => 'Resumo exibido ao selecionar uma área no mapa visual.', 'items' => [
            ['icon' => 'people', 'title' => 'Famílias acompanhadas', 'text' => '1.126 famílias em acompanhamento.'],
            ['icon' => 'building', 'title' => 'Unidade de referência', 'text' => 'CRAS 1 — Território Norte.'],
            ['icon' => 'exclamation-diamond', 'title' => 'Principal alerta', 'text' => 'Insegurança alimentar em tendência crescente.', 'badge' => 'Alta'],
        ]],
    ],
]);
