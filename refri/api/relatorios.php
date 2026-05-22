<?php
require __DIR__ . '/data.php';
json_response([
  'summary' => [
    ['label'=>'Total faturado','value'=>'R$ 38.920','helper'=>'+12% vs mês anterior','tone'=>'green','icon'=>'R$'],
    ['label'=>'OS finalizadas','value'=>'42','helper'=>'No período','tone'=>'blue','icon'=>'OS'],
    ['label'=>'Orçamentos aprovados','value'=>'12','helper'=>'Taxa comercial saudável','tone'=>'green','icon'=>'AP'],
    ['label'=>'Taxa de aprovação','value'=>'68%','helper'=>'+7 pontos percentuais','tone'=>'teal','icon'=>'%'],
    ['label'=>'Peças utilizadas','value'=>'86','helper'=>'Saídas em OS','tone'=>'amber','icon'=>'PC'],
    ['label'=>'Ticket médio','value'=>'R$ 427','helper'=>'Por atendimento','tone'=>'blue','icon'=>'TM'],
  ],
  'revenue' => [
    ['label'=>'Jan','value'=>18200], ['label'=>'Fev','value'=>22600], ['label'=>'Mar','value'=>19800], ['label'=>'Abr','value'=>31500], ['label'=>'Mai','value'=>38920], ['label'=>'Jun','value'=>42000]
  ],
  'services' => [
    ['label'=>'Higienização','value'=>42], ['label'=>'Manutenção','value'=>37], ['label'=>'Instalação','value'=>22], ['label'=>'Carga de gás','value'=>19], ['label'=>'Visita técnica','value'=>16]
  ],
  'budgets' => [
    ['label'=>'Aprovados','value'=>68], ['label'=>'Recusados','value'=>21], ['label'=>'Pendentes','value'=>31]
  ],
  'parts' => [
    ['nome'=>'Capacitor 35µF','qtd'=>18,'total'=>'R$ 630,00','status'=>'Estoque baixo'],
    ['nome'=>'Filtro secador 1/4','qtd'=>12,'total'=>'R$ 540,00','status'=>'Sem estoque'],
    ['nome'=>'Gás R410A','qtd'=>9,'total'=>'R$ 3.060,00','status'=>'Normal'],
  ],
  'rows' => [
    ['data'=>'22/05/2026','cliente'=>'Mercado São José','tipo'=>'OS','servico'=>'Manutenção Split','status'=>'Em andamento','tecnico'=>'Carlos','valor'=>'R$ 280,00','pagamento'=>'Pendente','nota'=>'Pendente'],
    ['data'=>'21/05/2026','cliente'=>'Padaria Modelo','tipo'=>'OS','servico'=>'Câmara fria','status'=>'Finalizada','tecnico'=>'Marcos','valor'=>'R$ 1.250,00','pagamento'=>'Pago','nota'=>'Emitida'],
    ['data'=>'20/05/2026','cliente'=>'Clínica Vida Norte','tipo'=>'Orçamento','servico'=>'Troca de peça','status'=>'Aprovado','tecnico'=>'Rafael','valor'=>'R$ 2.360,00','pagamento'=>'Pendente','nota'=>'Não emitida'],
  ]
]);
