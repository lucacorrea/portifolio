<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Service/SefazResponseParser.php';

use App\Fiscal\Service\SefazResponseParser;

function parserAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <nfeResultMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4">
      <retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
        <tpAmb>2</tpAmb>
        <verAplic>AM3.10-4.00</verAplic>
        <cStat>104</cStat>
        <xMotivo>Lote processado</xMotivo>
        <cUF>13</cUF>
        <protNFe versao="4.00">
          <infProt>
            <tpAmb>2</tpAmb>
            <verAplic>AM3.10-4.00</verAplic>
            <chNFe>13000000000000000000000000000000000000000000</chNFe>
            <cStat>1115</cStat>
            <xMotivo>Rejeicao: IBS/CBS nao informado</xMotivo>
          </infProt>
        </protNFe>
      </retEnviNFe>
    </nfeResultMsg>
  </soapenv:Body>
</soapenv:Envelope>
XML;

$result = (new SefazResponseParser())->authorization($xml);

parserAssert($result['cstat'] === '1115', 'cStat 1115 deve ser aceito.');
parserAssert($result['authorized'] === false, '1115 não pode autorizar.');
parserAssert($result['pending'] === false, '1115 é rejeição conclusiva.');
parserAssert(str_contains($result['reason'], 'IBS/CBS'), 'xMotivo deve ser preservado.');

echo "SefazResponseParser1115Test: OK\n";
