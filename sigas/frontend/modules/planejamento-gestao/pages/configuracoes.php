<?php

declare(strict_types=1);

return sigas_frontend_page([
    'title' => 'Configurações',
    'description' => 'Parâmetros exclusivamente visuais para apoiar a futura configuração dos ciclos de planejamento.',
    'actions' => [['label' => 'Restaurar visual', 'icon' => 'arrow-counterclockwise']],
    'stats' => [
        ['label' => 'Tipos de plano', 'value' => '5', 'detail' => 'Parâmetros visuais', 'icon' => 'journals'],
        ['label' => 'Prioridades', 'value' => '4', 'detail' => 'Crítica a baixa', 'icon' => 'flag'],
        ['label' => 'Status', 'value' => '7', 'detail' => 'Fluxo proposto', 'icon' => 'list-check'],
        ['label' => 'Categorias', 'value' => '8', 'detail' => 'Documentos', 'icon' => 'tags'],
    ],
    'blocks' => [
        ['type' => 'settings', 'title' => 'Tipos de plano', 'fields' => [
            ['label' => 'Tipo principal', 'value' => 'Plano de ação'], ['label' => 'Ciclo padrão', 'value' => 'Anual'], ['label' => 'Revisão', 'value' => 'Trimestral'],
        ]],
        ['type' => 'settings', 'title' => 'Prioridades e status', 'fields' => [
            ['label' => 'Prioridade crítica', 'value' => 'Resposta imediata'], ['label' => 'Status inicial', 'value' => 'Em elaboração'], ['label' => 'Status final', 'value' => 'Concluído'],
        ]],
        ['type' => 'settings', 'title' => 'Categorias de documento', 'fields' => [
            ['label' => 'Categoria 1', 'value' => 'Plano'], ['label' => 'Categoria 2', 'value' => 'Relatório'], ['label' => 'Categoria 3', 'value' => 'Ata e portaria'],
        ]],
        ['type' => 'settings', 'title' => 'Ciclos de monitoramento', 'fields' => [
            ['label' => 'Periodicidade', 'value' => 'Trimestral'], ['label' => 'Alerta de prazo', 'value' => '10 dias antes'], ['label' => 'Revisão gerencial', 'value' => 'Último dia útil'],
        ]],
        ['type' => 'info', 'title' => 'Integração futura', 'description' => 'Nenhuma alteração é persistida nesta etapa.', 'items' => [
            ['icon' => 'info-circle', 'title' => 'Recurso visual — integração futura', 'text' => 'Os parâmetros serão conectados ao back-end somente em etapa autorizada.'],
        ]],
    ],
]);
