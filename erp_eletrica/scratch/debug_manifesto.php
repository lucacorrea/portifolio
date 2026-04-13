<?php
/**
 * Script de diagnóstico para visualizar o XML exato de manifestação
 * Gera e assina o XML SEM enviar à SEFAZ
 */
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE MANIFESTAÇÃO SEFAZ ===\n\n";

// 1. Carregar configuração
try {
    $nfceService = new \App\Services\NfceService();
    $filialId = $_SESSION['filial_id'] ?? 1;
    $matrizId = (new \App\Models\Filial())->getMatrizId($filialId);
    $config = $nfceService->getConfig($matrizId);
    $config['certificado_senha_raw'] = $config['certificado_senha'] ?? '';
    
    echo "Ambiente: " . $config['ambiente'] . "\n";
    echo "Certificado: " . $config['certificado_path'] . "\n";
    echo "CNPJ: " . $config['cnpj'] . "\n\n";
} catch (Exception $e) {
    die("Erro ao carregar config: " . $e->getMessage());
}

// 2. Gerar o XML cru (template)
$chave = '13260205579869000178550010000975981399611780'; // exemplo - use uma real
$tpEvento = '210210'; // Ciência
$cnpj = $config['cnpj'];

$descEvento = 'Ciencia da Operacao';
$tpAmb = ($config['ambiente'] == 'producao' ? '1' : '2');
$dhEvento = date('Y-m-d\TH:i:sP');
$cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
$id = 'ID' . $tpEvento . $chave . '01';

echo "=== DADOS ===\n";
echo "chNFe: $chave\n";
echo "tpEvento: $tpEvento\n";
echo "CNPJ limpo: $cnpjLimpo (len=" . strlen($cnpjLimpo) . ")\n";
echo "ID: $id (len=" . strlen($id) . ")\n";
echo "dhEvento: $dhEvento\n";
echo "tpAmb: $tpAmb\n\n";

$xmlTemplate = '<?xml version="1.0" encoding="UTF-8"?><envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote><evento versao="1.00"><infEvento Id="' . $id . '"><cOrgao>91</cOrgao><tpAmb>' . $tpAmb . '</tpAmb><CNPJ>' . $cnpjLimpo . '</CNPJ><chNFe>' . $chave . '</chNFe><dhEvento>' . $dhEvento . '</dhEvento><tpEvento>' . $tpEvento . '</tpEvento><nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento><detEvento versao="1.00"><descEvento>' . $descEvento . '</descEvento></detEvento></infEvento></evento></envEvento>';

echo "=== XML TEMPLATE (antes de assinar) ===\n";
echo $xmlTemplate . "\n\n";

// 3. Assinar
try {
    $signer = new \App\Services\SefazSigner();
    $pfxPath = dirname(__DIR__) . "/storage/certificados/" . $config['certificado_path'];
    $signedXml = $signer->signXML($xmlTemplate, $pfxPath, $config['certificado_senha_raw']);
    
    echo "=== XML ASSINADO ===\n";
    echo $signedXml . "\n\n";
} catch (Exception $e) {
    die("Erro ao assinar: " . $e->getMessage());
}

// 4. Remover declaração XML e montar SOAP
$xmlBody = preg_replace('/^<\?xml[^>]*\?>/i', '', trim($signedXml));

echo "=== XML BODY (sem declaração XML) ===\n";
echo $xmlBody . "\n\n";

$ns = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4';
$content = "<nfeDadosMsg xmlns=\"{$ns}\">{$xmlBody}</nfeDadosMsg>";

$soapEnvelope = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap12:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap12=\"http://www.w3.org/2003/05/soap-envelope\">
    <soap12:Body>
        {$content}
    </soap12:Body>
</soap12:Envelope>";

echo "=== ENVELOPE SOAP COMPLETO ===\n";
echo $soapEnvelope . "\n\n";

// 5. Tentar ler último log de debug
$debugFile = dirname(__DIR__) . '/last_sefaz_debug.txt';
if (file_exists($debugFile)) {
    echo "=== ÚLTIMO LOG DE RESPOSTA SEFAZ ===\n";
    echo file_get_contents($debugFile) . "\n";
} else {
    echo "Arquivo de debug não encontrado: $debugFile\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
