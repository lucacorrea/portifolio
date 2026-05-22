<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'stats' => [
    ['label' => 'OS abertas', 'value' => '24', 'helper' => '+8 novas esta semana', 'icon' => '📋', 'tone' => 'blue'],
    ['label' => 'Em andamento', 'value' => '13', 'helper' => '5 com técnico em rota', 'icon' => '⏱', 'tone' => 'amber'],
    ['label' => 'Orçamentos pendentes', 'value' => '18', 'helper' => 'R$ 12.480 em análise', 'icon' => '📄', 'tone' => 'teal'],
    ['label' => 'Faturamento do mês', 'value' => 'R$ 38.920', 'helper' => '+12% vs mês anterior', 'icon' => '↗', 'tone' => 'green'],
  ],
  'osStatus' => [
    ['name' => 'Aberta', 'value' => 24],
    ['name' => 'Agendada', 'value' => 18],
    ['name' => 'Em andamento', 'value' => 13],
    ['name' => 'Aguardando peça', 'value' => 7],
    ['name' => 'Finalizada', 'value' => 42],
  ],
  'revenue' => [
    ['month' => 'Jan', 'value' => 18200],
    ['month' => 'Fev', 'value' => 22600],
    ['month' => 'Mar', 'value' => 19800],
    ['month' => 'Abr', 'value' => 31500],
    ['month' => 'Mai', 'value' => 38920],
    ['month' => 'Jun', 'value' => 42000],
  ],
  'recentOrders' => [
    ['os' => '#OS-000123', 'cliente' => 'Mercado São José', 'servico' => 'Manutenção Split', 'tecnico' => 'Carlos', 'status' => 'Em andamento', 'valor' => 'R$ 280,00', 'data' => '22/05/2026'],
    ['os' => '#OS-000124', 'cliente' => 'João Almeida', 'servico' => 'Higienização', 'tecnico' => 'Paulo', 'status' => 'Agendada', 'valor' => 'R$ 150,00', 'data' => '23/05/2026'],
    ['os' => '#OS-000125', 'cliente' => 'Clínica Vida Norte', 'servico' => 'Troca de peça', 'tecnico' => 'Rafael', 'status' => 'Aguardando peça', 'valor' => 'R$ 690,00', 'data' => '24/05/2026'],
    ['os' => '#OS-000126', 'cliente' => 'Padaria Modelo', 'servico' => 'Câmara fria', 'tecnico' => 'Marcos', 'status' => 'Finalizada', 'valor' => 'R$ 1.250,00', 'data' => '21/05/2026'],
  ],
  'agenda' => [
    ['hora' => '09:30', 'servico' => 'Manutenção preventiva', 'cliente' => 'Mercado São José', 'local' => 'Centro'],
    ['hora' => '11:00', 'servico' => 'Higienização split', 'cliente' => 'João Almeida', 'local' => 'Adrianópolis'],
    ['hora' => '15:20', 'servico' => 'Avaliação câmara fria', 'cliente' => 'Padaria Modelo', 'local' => 'Distrito'],
  ],
  'alerts' => [
    ['title' => 'Estoque baixo', 'text' => 'Filtro de ar 12.000 BTUs abaixo do mínimo.'],
    ['title' => 'Orçamento expirando', 'text' => '3 orçamentos vencem nas próximas 48 horas.'],
    ['title' => 'OS parada', 'text' => '2 serviços aguardam peça há mais de 3 dias.'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
