<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

use App\Fiscal\Service\ServiceOrderFiscalOrchestrator;
use App\Fiscal\Service\FiscalPaymentAllocator;
use App\Catalog\DTO\ServiceFormData;
use App\Nfse\Contract\NfseTransportInterface;
use App\Nfse\Provider\BethaFlyNfseProvider;
use App\Nfse\Service\DpsXmlBuilder;
use App\Nfse\Service\DpsSchemaValidator;

function nfseAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$serviceBase = [
    'tipo'=>'servico','descricao'=>'Limpeza técnica','subtotal'=>'300.00',
    'codigo_tributacao_nacional'=>'140101','nbs'=>'120013100',
    'municipio_incidencia_ibge'=>'1301209','tributacao_iss'=>'1','aliquota_iss'=>'2.0000',
    'iss_retido'=>0,'cst_ibs_cbs'=>'000','classificacao_tributaria_ibs_cbs'=>'000001',
    'cindop'=>'050102','finalidade_nfse'=>'0','tipo_operacao'=>'2',
];
$form = ServiceFormData::fromArray([
    'name'=>'Limpeza técnica','duration_minutes'=>60,'value'=>'300.00','status'=>'ativo',
    'tax_code'=>'140101','nbs'=>'120013100','municipality_code'=>'1301209',
    'iss_rate'=>'2,0000','iss_withheld'=>'1','ibs_cbs_cst'=>'000',
    'ibs_cbs_classification'=>'000001','operation_indicator'=>'050102',
    'nfse_purpose'=>'0','operation_type'=>'2',
]);
nfseAssert($form->fiscal()['nbs'] === '120013100', 'NBS do ServiceFormData não persistido.');
nfseAssert($form->fiscal()['iss_rate'] === '2.0000', 'Alíquota ISS não normalizada.');
nfseAssert($form->fiscal()['iss_withheld'] === 1, 'Retenção ISS não normalizada.');
$analysis = (new ServiceOrderFiscalOrchestrator())->analyze([
    ['tipo'=>'produto','descricao'=>'Peça A','subtotal'=>'500.00','ncm'=>'84159090','cfop_padrao'=>'5102','cst_ibs_cbs'=>'000','classificacao_tributaria_ibs_cbs'=>'000001'],
    $serviceBase,
]);
nfseAssert($analysis['product_total'] === '500.00', 'Total de produtos da OS mista incorreto.');
nfseAssert($analysis['service_total'] === '300.00', 'Total de serviços da OS mista incorreto.');
nfseAssert(count($analysis['available_service_documents']) === 1, 'Perfil fiscal NFS-e não agrupado.');
$mixedPayments = [['id'=>1,'valor'=>'800.00','forma_pagamento'=>'pix']];
$allocator = new FiscalPaymentAllocator();
$productPayments = $allocator->allocate($mixedPayments, 0, 50000);
$servicePayments = $allocator->allocate($mixedPayments, 50000, 30000);
nfseAssert($productPayments[0]['valor'] === '500.00', 'Alocação do pagamento para produtos incorreta.');
nfseAssert($servicePayments[0]['valor'] === '300.00', 'Alocação do pagamento para serviços incorreta.');

$snapshot = [
    'company'=>[
        'documento'=>'14.171.052/0001-35','telefone'=>'(92) 99999-9999','email'=>'fiscal@example.test',
        'opcao_simples_nacional'=>'3','regime_especial_tributacao'=>'0',
    ],
    'customer'=>[
        'cliente_documento'=>'123.456.789-09','cliente_nome'=>'CLIENTE TESTE',
        'cliente_codigo_municipio'=>'1301209','cep'=>'69.460-000','endereco'=>'Rua Teste',
        'cliente_numero'=>'10','complemento'=>null,'bairro'=>'Centro','telefone'=>'(92) 3333-0000',
        'cliente_email'=>'cliente@example.test',
    ],
    'items'=>[$serviceBase],
    'fiscal'=>[
        'schema_version'=>'1.01','environment'=>'homologacao','provider_version'=>'Betha-1.9',
        'dps_id'=>'DPS130120914171052000135000012026000000000001',
        'issued_at'=>'2026-08-17T10:00:00-04:00','series'=>'1','number'=>'202600000000001',
        'competence_date'=>'2026-08-17','municipality'=>'1301209',
    ],
];
$xml = (new DpsXmlBuilder())->build($snapshot);
$dom = new DOMDocument();
nfseAssert($dom->loadXML($xml, LIBXML_NONET), 'DPS XML inválida.');
nfseAssert($dom->getElementsByTagName('serv')->length === 1, 'DPS deve conter um perfil serv.');
nfseAssert($dom->getElementsByTagName('vServ')->item(0)?->nodeValue === '300.00', 'Valor DPS incorreto.');
nfseAssert($dom->getElementsByTagName('IBSCBS')->length === 1, 'Grupo IBS/CBS da DPS ausente na vigência obrigatória.');
nfseAssert($dom->getElementsByTagName('indDest')->item(0)?->nodeValue === '0', 'Indicador de destinatário da DPS incorreto.');
nfseAssert($dom->getElementsByTagName('CST')->item(0)?->nodeValue === '000', 'CST IBS/CBS da DPS ausente.');
nfseAssert($dom->getElementsByTagName('cClassTrib')->item(0)?->nodeValue === '000001', 'cClassTrib da DPS ausente.');
$officialXsd = getenv('NFSE_BETHA_XSD_PATH');
if (is_string($officialXsd) && trim($officialXsd) !== '') {
    (new DpsSchemaValidator())->validate($xml, $officialXsd);
}

$transport = new class implements NfseTransportInterface {
    public int $calls = 0;
    public function send(string $operation, string $soapXml): string
    {
        $this->calls++;
        if ($operation === 'RecepcionarDps') {
            return '<Envelope><Body><RecepcionarDpsResposta><protocolo>PROTO-1</protocolo><status>Aguardando validação do ambiente nacional</status></RecepcionarDpsResposta></Body></Envelope>';
        }
        return '<Envelope><Body><ConsultarStatusDpsEmissaoResposta><statusProcessamento>Processado com sucesso</statusProcessamento><protocolo>PROTO-1</protocolo><idDps>DPS-1</idDps><chaveAcesso>12345678901234567890123456789012345678901234567890</chaveAcesso><numeroNotaFiscal>456</numeroNotaFiscal><linkPdf>https://nota.example.test/456.pdf</linkPdf></ConsultarStatusDpsEmissaoResposta></Body></Envelope>';
    }
};
$provider = new BethaFlyNfseProvider($transport);
$submitted = $provider->submit($xml);
nfseAssert($submitted->status === 'aguardando_validacao' && $submitted->protocol === 'PROTO-1', 'Recepção assíncrona Betha incorreta.');
$queried = $provider->query(['environment'=>'homologacao','municipality'=>'1301209','provider_document'=>'14171052000135','protocol'=>'PROTO-1']);
nfseAssert($queried->status === 'autorizado' && $queried->invoiceNumber === '456', 'Consulta Betha autorizada incorreta.');

foreach (['cancel','substitute'] as $method) {
    try {
        $provider->{$method}('<evento/>');
        throw new RuntimeException('Operação Betha indisponível não foi bloqueada.');
    } catch (InvalidArgumentException $exception) {
        nfseAssert(str_contains($exception->getMessage(), $method === 'cancel' ? 'E900' : 'E901'), 'Código de bloqueio Betha incorreto.');
    }
}

echo "NfseFiscalFlowTest: OK\n";
