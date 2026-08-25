<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/excel.php';

if (!cm_can('comida_mesa.importar') && !cm_can('comida_mesa.cadastrar')) {
    throw new App\Exceptions\AuthorizationException('Acesso negado.');
}

$headers = [
    'NOME','CPF','NIS','RG','DATA NASCIMENTO','TELEFONE','EMAIL','ZONA','ENDERECO','NUMERO','COMPLEMENTO',
    'BAIRRO','COMUNIDADE','PONTO DE REFERENCIA','CEP','QUANTIDADE MEMBROS','RENDA FAMILIAR','POLO',
    'STATUS','PRIORIDADE','DATA INSCRICAO','OBSERVACAO','MOTIVO SUSPENSAO'
];
$rows = [[
    'MARIA DA SILVA','01234567890','12345678901','1234567','15/04/1985','92999999999','maria@exemplo.com',
    'URBANA','Rua Exemplo','100','Casa A','CENTRO','','Próximo à praça','69460000',4,1450.00,'Polo Centro',
    'EM ANALISE','NORMAL',date('d/m/Y'),'Linha de exemplo. Apague antes de importar.',''
]];
$formats = array_fill(0, count($headers), 'text');
$formats[15] = 'number';
$formats[16] = 'currency';

$exporter = new ComidaMesaExcelExporter();
$exporter->addSheet(
    'Importação',
    'Modelo de importação — Coari Comida na Mesa',
    'Preencha uma família por linha. CPF e telefone são validados antes da gravação.',
    $headers,
    $rows,
    $formats
)->download('modelo-importacao-comida-na-mesa.xlsx');
