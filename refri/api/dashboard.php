<?php
require __DIR__ . '/data.php';
$data = ky_data();
json_response([
  'stats' => [
    ['label'=>'OS abertas','value'=>'24','helper'=>'+8 novas esta semana','tone'=>'blue','icon'=>'OS'],
    ['label'=>'Em execução','value'=>'13','helper'=>'5 técnicos em rota','tone'=>'amber','icon'=>'EX'],
    ['label'=>'Orçamentos pendentes','value'=>'18','helper'=>'R$ 12.480 em análise','tone'=>'teal','icon'=>'OR'],
    ['label'=>'Faturamento do mês','value'=>'R$ 38.920','helper'=>'+12% vs mês anterior','tone'=>'green','icon'=>'R$'],
  ],
  'osStatus' => [
    ['label'=>'Aberta','value'=>24], ['label'=>'Agendada','value'=>18], ['label'=>'Em andamento','value'=>13], ['label'=>'Aguardando peça','value'=>7], ['label'=>'Finalizada','value'=>42]
  ],
  'revenue' => [
    ['label'=>'Jan','value'=>18200], ['label'=>'Fev','value'=>22600], ['label'=>'Mar','value'=>19800], ['label'=>'Abr','value'=>31500], ['label'=>'Mai','value'=>38920], ['label'=>'Jun','value'=>42000]
  ],
  'orders' => array_slice($data['os'],0,4),
  'schedule' => [
    ['time'=>'09:30','title'=>'Manutenção preventiva','client'=>'Mercado São José','status'=>'Agendada'],
    ['time'=>'11:00','title'=>'Higienização Split','client'=>'João Almeida','status'=>'Agendada'],
    ['time'=>'14:30','title'=>'Troca de peça','client'=>'Clínica Vida Norte','status'=>'Aguardando peça'],
  ],
  'alerts' => [
    ['title'=>'Peças com estoque baixo','text'=>'Capacitor 35µF abaixo do mínimo definido.'],
    ['title'=>'Orçamentos próximos de expirar','text'=>'2 propostas vencem nos próximos 3 dias.'],
    ['title'=>'Notas pendentes','text'=>'Existem 3 documentos fiscais aguardando ação.'],
  ]
]);
