<?php
require __DIR__ . '/data.php';
$data = ky_data();
$key = 'os';
$items = $data[$key] ?? [];
$summaries = [
  'clientes' => [
    ['label'=>'Total de clientes','value'=>'124','helper'=>'98 ativos','tone'=>'blue','icon'=>'CL'],
    ['label'=>'Pessoa jurídica','value'=>'86','helper'=>'Empresas cadastradas','tone'=>'teal','icon'=>'PJ'],
    ['label'=>'Pessoa física','value'=>'38','helper'=>'Clientes residenciais','tone'=>'green','icon'=>'PF'],
    ['label'=>'Com OS ativa','value'=>'19','helper'=>'Atendimentos em aberto','tone'=>'amber','icon'=>'OS'],
  ],
  'os' => [
    ['label'=>'OS abertas','value'=>'24','helper'=>'8 novas','tone'=>'blue','icon'=>'AB'],
    ['label'=>'Em andamento','value'=>'13','helper'=>'Equipe em campo','tone'=>'amber','icon'=>'EX'],
    ['label'=>'Finalizadas','value'=>'42','helper'=>'No mês atual','tone'=>'green','icon'=>'OK'],
    ['label'=>'Atrasadas','value'=>'3','helper'=>'Precisam de ação','tone'=>'red','icon'=>'AT'],
  ],
  'orcamentos' => [
    ['label'=>'Enviados','value'=>'31','helper'=>'Este mês','tone'=>'blue','icon'=>'EN'],
    ['label'=>'Aguardando','value'=>'18','helper'=>'Aprovação cliente','tone'=>'amber','icon'=>'AG'],
    ['label'=>'Aprovados','value'=>'12','helper'=>'Taxa 68%','tone'=>'green','icon'=>'AP'],
    ['label'=>'Recusados','value'=>'5','helper'=>'Revisar motivos','tone'=>'red','icon'=>'RE'],
  ],
  'pecas' => [
    ['label'=>'Total de peças','value'=>'248','helper'=>'Cadastradas','tone'=>'blue','icon'=>'PC'],
    ['label'=>'Estoque baixo','value'=>'9','helper'=>'Repor rápido','tone'=>'amber','icon'=>'BX'],
    ['label'=>'Sem estoque','value'=>'4','helper'=>'Crítico','tone'=>'red','icon'=>'SE'],
    ['label'=>'Valor em estoque','value'=>'R$ 18.740','helper'=>'Custo aproximado','tone'=>'green','icon'=>'R$'],
  ],
  'servicos' => [
    ['label'=>'Serviços ativos','value'=>'18','helper'=>'Catálogo atual','tone'=>'green','icon'=>'AT'],
    ['label'=>'Manutenção','value'=>'8','helper'=>'Categoria principal','tone'=>'blue','icon'=>'MT'],
    ['label'=>'Instalação','value'=>'5','helper'=>'Serviços de venda','tone'=>'teal','icon'=>'IN'],
    ['label'=>'Ticket médio','value'=>'R$ 286','helper'=>'Base do catálogo','tone'=>'amber','icon'=>'TM'],
  ],
  'notas' => [
    ['label'=>'Emitidas','value'=>'28','helper'=>'No mês','tone'=>'green','icon'=>'EM'],
    ['label'=>'Pendentes','value'=>'6','helper'=>'Aguardando emissão','tone'=>'amber','icon'=>'PE'],
    ['label'=>'Rejeitadas','value'=>'2','helper'=>'Corrigir dados','tone'=>'red','icon'=>'RJ'],
    ['label'=>'Valor fiscal','value'=>'R$ 21.890','helper'=>'Documentado','tone'=>'blue','icon'=>'R$'],
  ],
];
json_response(['summary' => $summaries[$key] ?? [], 'items' => $items]);
