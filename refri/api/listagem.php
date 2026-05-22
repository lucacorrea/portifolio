<?php
header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? 'os';

$data = [
  'clientes' => [
    'summary' => [
      ['label' => 'Total de clientes', 'value' => '328', 'icon' => '👥'],
      ['label' => 'Pessoa física', 'value' => '214', 'icon' => '🧍'],
      ['label' => 'Pessoa jurídica', 'value' => '114', 'icon' => '🏢'],
      ['label' => 'Com OS ativa', 'value' => '37', 'icon' => '📋'],
    ],
    'columns' => [
      ['key' => 'nome', 'label' => 'Cliente'],
      ['key' => 'telefone', 'label' => 'WhatsApp'],
      ['key' => 'tipo', 'label' => 'Tipo'],
      ['key' => 'cidade', 'label' => 'Cidade'],
      ['key' => 'status', 'label' => 'Status'],
    ],
    'rows' => [
      ['id' => 1, 'nome' => 'Mercado São José', 'cliente' => 'Mercado São José', 'telefone' => '(92) 99999-1001', 'tipo' => 'Pessoa Jurídica', 'cidade' => 'Manaus', 'status' => 'Aprovado'],
      ['id' => 2, 'nome' => 'João Almeida', 'cliente' => 'João Almeida', 'telefone' => '(92) 99999-1002', 'tipo' => 'Pessoa Física', 'cidade' => 'Manaus', 'status' => 'Aprovado'],
      ['id' => 3, 'nome' => 'Clínica Vida Norte', 'cliente' => 'Clínica Vida Norte', 'telefone' => '(92) 99999-1003', 'tipo' => 'Pessoa Jurídica', 'cidade' => 'Manaus', 'status' => 'Pendente'],
      ['id' => 4, 'nome' => 'Padaria Modelo', 'cliente' => 'Padaria Modelo', 'telefone' => '(92) 99999-1004', 'tipo' => 'Pessoa Jurídica', 'cidade' => 'Manaus', 'status' => 'Aprovado'],
    ],
  ],
  'os' => [
    'summary' => [
      ['label' => 'OS abertas', 'value' => '24', 'icon' => '📋'],
      ['label' => 'Em andamento', 'value' => '13', 'icon' => '⏱'],
      ['label' => 'Finalizadas', 'value' => '42', 'icon' => '✅'],
      ['label' => 'Atrasadas', 'value' => '05', 'icon' => '⚠'],
    ],
    'columns' => [
      ['key' => 'numero', 'label' => 'OS'],
      ['key' => 'cliente', 'label' => 'Cliente'],
      ['key' => 'servico', 'label' => 'Serviço'],
      ['key' => 'status', 'label' => 'Status'],
      ['key' => 'tecnico', 'label' => 'Técnico'],
      ['key' => 'valor', 'label' => 'Valor'],
      ['key' => 'data', 'label' => 'Data'],
    ],
    'rows' => [
      ['numero' => '#OS-000123', 'cliente' => 'Mercado São José', 'servico' => 'Manutenção Split', 'status' => 'Em andamento', 'tecnico' => 'Carlos', 'valor' => 'R$ 280,00', 'data' => '22/05/2026'],
      ['numero' => '#OS-000124', 'cliente' => 'João Almeida', 'servico' => 'Higienização', 'status' => 'Agendada', 'tecnico' => 'Paulo', 'valor' => 'R$ 150,00', 'data' => '23/05/2026'],
      ['numero' => '#OS-000125', 'cliente' => 'Clínica Vida Norte', 'servico' => 'Troca de peça', 'status' => 'Aguardando peça', 'tecnico' => 'Rafael', 'valor' => 'R$ 690,00', 'data' => '24/05/2026'],
      ['numero' => '#OS-000126', 'cliente' => 'Padaria Modelo', 'servico' => 'Câmara fria', 'status' => 'Finalizada', 'tecnico' => 'Marcos', 'valor' => 'R$ 1.250,00', 'data' => '21/05/2026'],
    ],
  ],
  'orcamentos' => [
    'summary' => [
      ['label' => 'Enviados', 'value' => '58', 'icon' => '📨'],
      ['label' => 'Aguardando', 'value' => '18', 'icon' => '⏳'],
      ['label' => 'Aprovados', 'value' => '32', 'icon' => '✅'],
      ['label' => 'Recusados', 'value' => '08', 'icon' => '✖'],
    ],
    'columns' => [
      ['key' => 'numero', 'label' => 'Orçamento'],
      ['key' => 'cliente', 'label' => 'Cliente'],
      ['key' => 'status', 'label' => 'Status'],
      ['key' => 'validade', 'label' => 'Validade'],
      ['key' => 'valor', 'label' => 'Valor'],
      ['key' => 'data', 'label' => 'Data'],
    ],
    'rows' => [
      ['numero' => '#ORC-000081', 'cliente' => 'Mercado São José', 'status' => 'Enviado', 'validade' => '28/05/2026', 'valor' => 'R$ 1.480,00', 'data' => '21/05/2026'],
      ['numero' => '#ORC-000082', 'cliente' => 'Clínica Vida Norte', 'status' => 'Aprovado', 'validade' => '30/05/2026', 'valor' => 'R$ 3.240,00', 'data' => '20/05/2026'],
      ['numero' => '#ORC-000083', 'cliente' => 'João Almeida', 'status' => 'Pendente', 'validade' => '25/05/2026', 'valor' => 'R$ 380,00', 'data' => '22/05/2026'],
      ['numero' => '#ORC-000084', 'cliente' => 'Padaria Modelo', 'status' => 'Recusado', 'validade' => '23/05/2026', 'valor' => 'R$ 2.100,00', 'data' => '19/05/2026'],
    ],
  ],
  'pecas' => [
    'summary' => [
      ['label' => 'Total de peças', 'value' => '146', 'icon' => '📦'],
      ['label' => 'Estoque baixo', 'value' => '12', 'icon' => '⚠'],
      ['label' => 'Sem estoque', 'value' => '04', 'icon' => '🚫'],
      ['label' => 'Valor em estoque', 'value' => 'R$ 28k', 'icon' => '💰'],
    ],
    'columns' => [
      ['key' => 'nome', 'label' => 'Peça'],
      ['key' => 'codigo', 'label' => 'Código'],
      ['key' => 'categoria', 'label' => 'Categoria'],
      ['key' => 'estoque', 'label' => 'Estoque'],
      ['key' => 'status', 'label' => 'Status'],
      ['key' => 'valor', 'label' => 'Valor'],
    ],
    'rows' => [
      ['nome' => 'Filtro split 12.000 BTUs', 'cliente' => 'Filtro split 12.000 BTUs', 'codigo' => 'PÇ-0012', 'categoria' => 'Filtros', 'estoque' => '4 un.', 'status' => 'Estoque baixo', 'valor' => 'R$ 48,00'],
      ['nome' => 'Compressor 1/3 HP', 'cliente' => 'Compressor 1/3 HP', 'codigo' => 'PÇ-0041', 'categoria' => 'Compressores', 'estoque' => '9 un.', 'status' => 'Aprovado', 'valor' => 'R$ 620,00'],
      ['nome' => 'Gás refrigerante R410A', 'cliente' => 'Gás refrigerante R410A', 'codigo' => 'PÇ-0098', 'categoria' => 'Gases', 'estoque' => '2 un.', 'status' => 'Estoque baixo', 'valor' => 'R$ 780,00'],
    ],
  ],
  'servicos' => [
    'summary' => [
      ['label' => 'Serviços ativos', 'value' => '22', 'icon' => '🛠'],
      ['label' => 'Mais vendido', 'value' => 'Higienização', 'icon' => '⭐'],
      ['label' => 'Ticket médio', 'value' => 'R$ 310', 'icon' => '💰'],
      ['label' => 'Categorias', 'value' => '06', 'icon' => '🏷'],
    ],
    'columns' => [
      ['key' => 'nome', 'label' => 'Serviço'],
      ['key' => 'categoria', 'label' => 'Categoria'],
      ['key' => 'tempo', 'label' => 'Tempo médio'],
      ['key' => 'status', 'label' => 'Status'],
      ['key' => 'valor', 'label' => 'Valor base'],
    ],
    'rows' => [
      ['nome' => 'Higienização de ar-condicionado', 'cliente' => 'Higienização de ar-condicionado', 'categoria' => 'Preventiva', 'tempo' => '1h30', 'status' => 'Aprovado', 'valor' => 'R$ 150,00'],
      ['nome' => 'Instalação de split', 'cliente' => 'Instalação de split', 'categoria' => 'Instalação', 'tempo' => '3h00', 'status' => 'Aprovado', 'valor' => 'R$ 450,00'],
      ['nome' => 'Carga de gás', 'cliente' => 'Carga de gás', 'categoria' => 'Corretiva', 'tempo' => '1h00', 'status' => 'Aprovado', 'valor' => 'R$ 280,00'],
    ],
  ],
  'notas' => [
    'summary' => [
      ['label' => 'Emitidas', 'value' => '31', 'icon' => '✅'],
      ['label' => 'Pendentes', 'value' => '09', 'icon' => '⏳'],
      ['label' => 'Rejeitadas', 'value' => '02', 'icon' => '⚠'],
      ['label' => 'Total emitido', 'value' => 'R$ 24k', 'icon' => '🧾'],
    ],
    'columns' => [
      ['key' => 'numero', 'label' => 'Nota'],
      ['key' => 'cliente', 'label' => 'Cliente'],
      ['key' => 'tipo', 'label' => 'Tipo'],
      ['key' => 'status', 'label' => 'Status'],
      ['key' => 'valor', 'label' => 'Valor'],
      ['key' => 'data', 'label' => 'Data'],
    ],
    'rows' => [
      ['numero' => 'NF-000191', 'cliente' => 'Mercado São José', 'tipo' => 'NFS-e', 'status' => 'Emitida', 'valor' => 'R$ 1.480,00', 'data' => '21/05/2026'],
      ['numero' => 'NF-000192', 'cliente' => 'Clínica Vida Norte', 'tipo' => 'NFS-e', 'status' => 'Pendente', 'valor' => 'R$ 3.240,00', 'data' => '22/05/2026'],
      ['numero' => 'NF-000193', 'cliente' => 'Padaria Modelo', 'tipo' => 'NF-e', 'status' => 'Rejeitada', 'valor' => 'R$ 2.100,00', 'data' => '20/05/2026'],
    ],
  ],
];

echo json_encode($data[$tipo] ?? $data['os'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
