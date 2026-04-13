<?php

$xml = '<?xml version="1.0" encoding="UTF-8"?><envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote><evento versao="1.00"><infEvento Id="ID21021035240400000000000055001000000001100000000001"><cOrgao>35</cOrgao><tpAmb>2</tpAmb><CNPJ>12345678901234</CNPJ><chNFe>35240400000000000000550010000000011000000000</chNFe><dhEvento>2026-04-13T10:00:00-03:00</dhEvento><tpEvento>210210</tpEvento><nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento><detEvento versao="1.00"><descEvento>Ciencia da Operacao</descEvento></detEvento></infEvento></evento></envEvento>';

$dom = new DOMDocument();
$dom->loadXML($xml);

// Carregar o schema XSD
$xsdPath = __DIR__ . '/src/App/Services/vendor/nfephp-org/sped-nfe/schemes/PL_009_V4/envEvento_v1.00.xsd';

libxml_use_internal_errors(true);
if ($dom->schemaValidate($xsdPath)) {
    echo "VALID!\n";
} else {
    echo "INVALID:\n";
    foreach (libxml_get_errors() as $error) {
        echo "- " . $error->message . "\n";
    }
}
