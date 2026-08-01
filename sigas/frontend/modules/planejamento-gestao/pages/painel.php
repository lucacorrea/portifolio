<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Painel',
    'description' => 'Visão executiva dos planos, metas, prazos e capacidade institucional da SEMAS.',
    'actions' => [
        ['label' => 'Atualizar painel', 'icon' => 'arrow-repeat'],
        ['label' => 'Novo plano de ação', 'icon' => 'plus-lg', 'primary' => true],
    ],
    'stats' => [
        ['label' => 'Planos ativos', 'value' => '8', 'detail' => '3 revisados no mês', 'icon' => 'clipboard2-check'],
        ['label' => 'Metas em andamento', 'value' => '37', 'detail' => '68% da carteira', 'icon' => 'bullseye'],
        ['label' => 'Metas concluídas', 'value' => '14', 'detail' => 'No ciclo atual', 'icon' => 'check2-circle'],
        ['label' => 'Metas atrasadas', 'value' => '4', 'detail' => 'Exigem providência', 'icon' => 'exclamation-triangle'],
        ['label' => 'Unidades acompanhadas', 'value' => '12', 'detail' => 'Cobertura municipal', 'icon' => 'buildings'],
        ['label' => 'Equipes', 'value' => '18', 'detail' => '6 áreas técnicas', 'icon' => 'people'],
        ['label' => 'Documentos pendentes', 'value' => '7', 'detail' => '2 de alta prioridade', 'icon' => 'file-earmark-excel'],
        ['label' => 'Alertas', 'value' => '5', 'detail' => 'Revisão nesta semana', 'icon' => 'bell'],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Evolução das metas', 'kicker' => 'Últimos seis meses', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [42, 48, 53, 59, 64, 68], 'chart' => 'line'],
        ['type' => 'chart', 'title' => 'Execução por setor', 'kicker' => 'Percentual realizado', 'labels' => ['Gestão', 'Vigilância', 'Básica', 'Especial'], 'values' => [78, 66, 71, 63], 'chart' => 'bar'],
        ['type' => 'timeline', 'title' => 'Próximos prazos', 'items' => array_slice($data['cronograma'], 0, 3)],
        ['type' => 'info', 'title' => 'Últimas atualizações', 'description' => 'Movimentações demonstrativas registradas pelas áreas.', 'items' => [
            ['icon' => 'check2-circle', 'title' => 'Meta concluída', 'text' => 'Fluxos intersetoriais revisados pela equipe de planejamento.', 'badge' => 'Hoje'],
            ['icon' => 'file-earmark-text', 'title' => 'Documento enviado', 'text' => 'Relatório semestral encaminhado para validação.', 'badge' => 'Ontem'],
            ['icon' => 'exclamation-triangle', 'title' => 'Prazo sinalizado', 'text' => 'Boletim de monitoramento requer atualização.', 'badge' => 'Atenção'],
        ]],
        ['type' => 'info', 'title' => 'Atalhos rápidos', 'description' => 'Acessos visuais às rotinas mais frequentes.', 'items' => [
            ['icon' => 'clipboard-plus', 'title' => 'Criar plano', 'text' => 'Abrir o formulário demonstrativo de planejamento.'],
            ['icon' => 'calendar-plus', 'title' => 'Registrar prazo', 'text' => 'Adicionar compromisso ao cronograma visual.'],
            ['icon' => 'file-earmark-bar-graph', 'title' => 'Gerar relatório', 'text' => 'Preparar uma visualização resumida.'],
        ]],
    ],
]);
