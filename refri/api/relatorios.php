<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'summary' => [
    ['label' => 'Total faturado', 'value' => 'R$ 38.920', 'helper' => '+12% em relação ao mês anterior'],
    ['label' => 'OS finalizadas', 'value' => '42', 'helper' => 'Serviços concluídos no período'],
    ['label' => 'Orçamentos aprovados', 'value' => '32', 'helper' => 'R$ 26.780 em oportunidades ganhas'],
    ['label' => 'Taxa de aprovação', 'value' => '68%', 'helper' => 'Média dos últimos 30 dias'],
    ['label' => 'Peças utilizadas', 'value' => '87', 'helper' => 'Itens baixados do estoque'],
    ['label' => 'Ticket médio', 'value' => 'R$ 927', 'helper' => 'Valor médio por OS finalizada'],
  ],
  'revenue' => [
    ['month' => 'Jan', 'value' => 18200],
    ['month' => 'Fev', 'value' => 22600],
    ['month' => 'Mar', 'value' => 19800],
    ['month' => 'Abr', 'value' => 31500],
    ['month' => 'Mai', 'value' => 38920],
    ['month' => 'Jun', 'value' => 42000],
  ],
  'services' => [
    ['name' => 'Higienização', 'value' => 42],
    ['name' => 'Manutenção', 'value' => 37],
    ['name' => 'Instalação', 'value' => 22],
    ['name' => 'Carga gás', 'value' => 19],
    ['name' => 'Visita', 'value' => 16],
  ],
  'budgets' => [
    ['name' => 'Aprovados', 'value' => 32],
    ['name' => 'Recusados', 'value' => 8],
    ['name' => 'Pendentes', 'value' => 18],
  ],
  'table' => [
    ['data' => '22/05/2026', 'cliente' => 'Mercado São José', 'servico' => 'Manutenção Split', 'status' => 'Finalizada', 'valor' => 'R$ 280,00', 'nota' => 'Emitida'],
    ['data' => '21/05/2026', 'cliente' => 'Padaria Modelo', 'servico' => 'Câmara fria', 'status' => 'Finalizada', 'valor' => 'R$ 1.250,00', 'nota' => 'Pendente'],
    ['data' => '20/05/2026', 'cliente' => 'Clínica Vida Norte', 'servico' => 'Troca de peça', 'status' => 'Em andamento', 'valor' => 'R$ 690,00', 'nota' => 'Não emitida'],
    ['data' => '19/05/2026', 'cliente' => 'João Almeida', 'servico' => 'Higienização', 'status' => 'Finalizada', 'valor' => 'R$ 150,00', 'nota' => 'Emitida'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
