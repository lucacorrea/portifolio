<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Fiscal\Service\FiscalDocumentXmlBuilder;

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

function fiscalBuilderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$snapshot = [
    'company' => [
        'razao_social'=>'K YAMAGUCHI COMERCIO DE ELETRODOMESTICOS LTDA',
        'nome_fantasia'=>'K YAMAGUCHI REFRIGERACAO','documento'=>'14171052000135',
        'inscricao_estadual'=>'041234567','inscricao_municipal'=>'12345','crt'=>1,
        'cnae_principal'=>'4753900','telefone'=>'92999999999','endereco_logradouro'=>'Rua Teste',
        'endereco_numero'=>'100','endereco_complemento'=>null,'endereco_bairro'=>'Centro',
        'endereco_cidade'=>'Coari','endereco_uf'=>'AM','endereco_cep'=>'69460000',
        'codigo_municipio_ibge'=>'1301209',
    ],
    'customer' => [
        'cliente_nome'=>'CLIENTE TESTE','cliente_documento'=>'12345678909','indicador_ie'=>'nao_contribuinte',
        'inscricao_estadual'=>null,'cliente_email'=>null,'endereco'=>'Rua Cliente','cliente_numero'=>'10',
        'complemento'=>null,'bairro'=>'Centro','cidade'=>'Coari','uf'=>'AM','cep'=>'69460000',
        'cliente_codigo_municipio'=>'1301209',
    ],
    'service_order'=>['number'=>'OS-000001'],
    'items'=>[ [
        'produto_id'=>1,'codigo'=>'PRO-000001','descricao'=>'CAPACITOR','unidade'=>'UN','quantidade'=>'1.0000',
        'valor_unitario'=>'100.00','desconto'=>'0.00','subtotal'=>'100.00','ncm'=>'84159090','cest'=>null,
        'origem_mercadoria'=>'0','cfop_padrao'=>'5102','csosn'=>'102','cst_icms'=>null,
        'cst_pis'=>'07','cst_cofins'=>'07','aliquota_icms'=>'0.0000','aliquota_pis'=>'0.0000',
        'aliquota_cofins'=>'0.0000','codigo_barras'=>null,'gtin_tributavel'=>null,'unidade_tributavel'=>'UN',
    ] ],
    'services'=>[['servico_id'=>1,'descricao'=>'Instalacao','subtotal'=>'50.00']],
    'payments'=>[['id'=>1,'valor'=>'150.00','forma_pagamento'=>'pix','quantidade_parcelas'=>1]],
    'totals'=>['products'=>'100.00','invoice'=>'100.00'],
    'fiscal'=>['environment'=>'homologacao','model'=>'55','series'=>1,'number'=>1,'cnf'=>'87654321'],
];
$document = [
    'id'=>1,'ambiente'=>'homologacao','modelo'=>'55','serie'=>1,'numero'=>1,'cnf'=>'87654321',
    'snapshot_json'=>json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
];
$result = (new FiscalDocumentXmlBuilder())->build($document);
$dom = new DOMDocument();
fiscalBuilderAssert($dom->loadXML($result['xml'], LIBXML_NONET), 'NF-e XML inválido.');
fiscalBuilderAssert(preg_match('/^\d{44}$/', $result['key']) === 1, 'Chave NF-e inválida.');
fiscalBuilderAssert($dom->getElementsByTagName('det')->length === 1, 'Serviço não pode virar item da NF-e.');
fiscalBuilderAssert($dom->getElementsByTagName('vPag')->item(0)?->nodeValue === '100.00', 'Pagamento das peças deve ser limitado ao valor da NF-e.');
fiscalBuilderAssert($dom->getElementsByTagName('xProd')->item(0)?->nodeValue !== 'Instalacao', 'Serviço foi incluído como produto.');
fiscalBuilderAssert(str_contains((string) $dom->getElementsByTagName('infCpl')->item(0)?->nodeValue, 'NFS-e separada'), 'Vínculo com NFS-e ausente.');

$snapshot['payments'] = [['id'=>2,'valor'=>'50.00','forma_pagamento'=>'dinheiro','quantidade_parcelas'=>1]];
$snapshot['fiscal']['model'] = '65';
$snapshot['fiscal']['number'] = 2;
$snapshot['fiscal']['cnf'] = '76543210';
$document['modelo'] = '65';
$document['numero'] = 2;
$document['cnf'] = '76543210';
$document['snapshot_json'] = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$partial = (new FiscalDocumentXmlBuilder())->build($document);
$partialDom = new DOMDocument();
fiscalBuilderAssert($partialDom->loadXML($partial['xml'], LIBXML_NONET), 'NFC-e XML inválido.');
fiscalBuilderAssert($partialDom->getElementsByTagName('mod')->item(0)?->nodeValue === '65', 'Modelo NFC-e ausente.');
fiscalBuilderAssert($partialDom->getElementsByTagName('tPag')->item(0)?->nodeValue === '90', 'Pagamento parcial deve ficar sem pagamento no documento.');
fiscalBuilderAssert($partialDom->getElementsByTagName('vPag')->item(0)?->nodeValue === '0.00', 'Pagamento parcial não pode duplicar valor fiscal.');
echo "FiscalDocumentXmlBuilderTest: OK\n";
