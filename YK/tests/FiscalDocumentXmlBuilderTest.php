<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Fiscal\Service\FiscalDocumentXmlBuilder;
use App\Fiscal\Tax\Decimal;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;

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
        'inscricao_estadual'=>'041234567','inscricao_municipal'=>'12345','crt'=>3,
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
        'origem_mercadoria'=>'0','cfop_padrao'=>'5102','csosn'=>null,'cst_icms'=>'00',
        'cst_pis'=>'07','cst_cofins'=>'07','aliquota_icms'=>'0.0000','aliquota_pis'=>'0.0000',
        'aliquota_cofins'=>'0.0000','codigo_barras'=>null,'gtin_tributavel'=>null,'unidade_tributavel'=>'UN',
        'cst_ibs_cbs'=>'000','classificacao_tributaria_ibs_cbs'=>'000001',
        'ibs_cbs_rule'=>[
            'calculation_mode'=>'standard','ibs_uf_rate'=>'0.1000',
            'ibs_city_rate'=>'0.0000','cbs_rate'=>'0.9000','source_version'=>'IT 2025.002 v1.50',
        ],
    ] ],
    'services'=>[['servico_id'=>1,'descricao'=>'Instalacao','subtotal'=>'50.00']],
    'payments'=>[['id'=>1,'valor'=>'150.00','forma_pagamento'=>'pix','quantidade_parcelas'=>1]],
    'totals'=>['products'=>'100.00','invoice'=>'100.00'],
    'fiscal'=>['environment'=>'homologacao','model'=>'55','series'=>1,'number'=>1,'cnf'=>'87654321','issued_at'=>date(DATE_ATOM)],
];
$document = [
    'id'=>1,'ambiente'=>'homologacao','modelo'=>'55','serie'=>1,'numero'=>1,'cnf'=>'87654321',
    'snapshot_json'=>json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
];
$result = (new FiscalDocumentXmlBuilder())->build($document);
$dom = new DOMDocument();
fiscalBuilderAssert($dom->loadXML($result['xml'], LIBXML_NONET), 'NF-e XML inválido.');
$xpath = new DOMXPath($dom);
fiscalBuilderAssert(preg_match('/^\d{44}$/', $result['key']) === 1, 'Chave NF-e inválida.');
fiscalBuilderAssert($dom->getElementsByTagName('det')->length === 1, 'Serviço não pode virar item da NF-e.');
fiscalBuilderAssert($dom->getElementsByTagName('vPag')->item(0)?->nodeValue === '100.00', 'Pagamento das peças deve ser limitado ao valor da NF-e.');
fiscalBuilderAssert($dom->getElementsByTagName('xProd')->item(0)?->nodeValue !== 'Instalacao', 'Serviço foi incluído como produto.');
fiscalBuilderAssert(str_contains((string) $dom->getElementsByTagName('infCpl')->item(0)?->nodeValue, 'NFS-e separada'), 'Vínculo com NFS-e ausente.');
fiscalBuilderAssert($dom->getElementsByTagName('IBSCBS')->length === 1, 'Grupo IBS/CBS do item ausente.');
fiscalBuilderAssert($xpath->query('//*[local-name()="IBSCBS"]/*[local-name()="CST"]')->item(0)?->nodeValue === '000', 'CST IBS/CBS incorreto.');
fiscalBuilderAssert($dom->getElementsByTagName('cClassTrib')->item(0)?->nodeValue === '000001', 'cClassTrib incorreto.');
fiscalBuilderAssert($dom->getElementsByTagName('vIBSUF')->item(0)?->nodeValue === '0.10', 'IBS UF incorreto.');
fiscalBuilderAssert($dom->getElementsByTagName('vIBSMun')->item(0)?->nodeValue === '0.00', 'IBS municipal incorreto.');
fiscalBuilderAssert($dom->getElementsByTagName('vCBS')->item(0)?->nodeValue === '0.90', 'CBS incorreta.');
fiscalBuilderAssert($dom->getElementsByTagName('IBSCBSTot')->length === 1, 'Total IBS/CBS ausente.');
fiscalBuilderAssert(Decimal::taxCents(1, 9000) === 0, 'Arredondamento de R$ 0,01 incorreto.');
fiscalBuilderAssert(Decimal::taxCents(1099, 9000) === 10, 'Arredondamento de R$ 10,99 incorreto.');
$factorySource = file_get_contents(dirname(__DIR__) . '/src/Fiscal/Service/FiscalToolsFactory.php');
$configurationSource = file_get_contents(dirname(__DIR__) . '/src/Fiscal/Repository/FiscalConfigurationRepository.php');
fiscalBuilderAssert(is_string($factorySource) && str_contains($factorySource, 'forceQRCodeVersion'), 'Factory deve aplicar a versão configurada do QR Code.');
fiscalBuilderAssert(is_string($configurationSource) && str_contains($configurationSource, 'cfg.qr_code_versao'), 'Perfil fiscal deve carregar a versão do QR Code.');

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
fiscalBuilderAssert($partialDom->getElementsByTagName('tPag')->item(0)?->nodeValue === '01', 'Parcela efetivamente recebida deve constar na NFC-e.');
fiscalBuilderAssert($partialDom->getElementsByTagName('vPag')->item(0)?->nodeValue === '50.00', 'Valor parcial recebido incorreto.');
fiscalBuilderAssert($partialDom->getElementsByTagName('tPag')->item(1)?->nodeValue === '90', 'Saldo ainda não recebido deve permanecer sem pagamento.');
fiscalBuilderAssert($partialDom->getElementsByTagName('vPag')->item(1)?->nodeValue === '0.00', 'Saldo pendente não pode ser inventado como pagamento.');
$opensslOptions = [
    'config'=>dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
    'private_key_bits'=>2048,
    'private_key_type'=>OPENSSL_KEYTYPE_RSA,
    'digest_alg'=>'sha256',
];
$privateKey = openssl_pkey_new($opensslOptions);
fiscalBuilderAssert($privateKey !== false, 'Fixture não gerou chave RSA.');
$request = openssl_csr_new(['commonName'=>'OSMais fiscal test'], $privateKey, $opensslOptions);
$certificateResource = $request === false ? false : openssl_csr_sign($request, null, $privateKey, 1, $opensslOptions);
$pfx = '';
fiscalBuilderAssert($certificateResource !== false && openssl_pkcs12_export($certificateResource, $pfx, $privateKey, 'fixture'), 'Fixture não gerou certificado PFX.');
$certificate = Certificate::readPfx($pfx, 'fixture');
$toolsConfig = json_encode([
    'atualizacao'=>date(DATE_ATOM),'tpAmb'=>2,'razaosocial'=>'OSMais fiscal test',
    'cnpj'=>'14171052000135','siglaUF'=>'AM','schemes'=>'PL_010_V1.30','versao'=>'4.00',
    'tokenIBPT'=>null,'CSC'=>null,'CSCid'=>null,'aProxyConf'=>null,
], JSON_THROW_ON_ERROR);
$nfeTools = new Tools($toolsConfig, $certificate);
$nfeTools->model(55);
$signedNfe = $nfeTools->signNFe($result['xml'], 1);
$signedNfeDom = new DOMDocument();
fiscalBuilderAssert($signedNfeDom->loadXML($signedNfe, LIBXML_NONET), 'NF-e assinada inválida.');
fiscalBuilderAssert($signedNfeDom->getElementsByTagName('Signature')->length === 1, 'Assinatura XML NF-e ausente.');
fiscalBuilderAssert($signedNfeDom->getElementsByTagName('infNFeSupl')->length === 0, 'NF-e 55 não deve conter suplemento NFC-e.');
$tools = new Tools($toolsConfig, $certificate);
$tools->model(65);
$tools->forceQRCodeVersion('300');
$signedNfce = $tools->signNFe($partial['xml'], 1);
$signedDom = new DOMDocument();
fiscalBuilderAssert($signedDom->loadXML($signedNfce, LIBXML_NONET), 'NFC-e assinada inválida.');
fiscalBuilderAssert($signedDom->getElementsByTagName('Signature')->length === 1, 'Assinatura XML NFC-e ausente.');
fiscalBuilderAssert($signedDom->getElementsByTagName('infNFeSupl')->length === 1, 'Suplemento NFC-e ausente apó assinatura.');
fiscalBuilderAssert($signedDom->getElementsByTagName('qrCode')->length === 1, 'QR Code NFC-e v3 ausente.');
fiscalBuilderAssert($signedDom->getElementsByTagName('urlChave')->length === 1, 'URL de consulta NFC-e ausente.');
echo "FiscalDocumentXmlBuilderTest: OK\n";
