<?php
/**
 * Script de diagnóstico para visualizar o XML exato de manifestação
 * Gera e assina o XML SEM enviar à SEFAZ
 */
require_once dirname(__DIR__) . '/config.php';
checkAuth();

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE MANIFESTAÇÃO SEFAZ ===\n\n";

try {
    // Pegar uma nota real do banco
    $db = \App\Config\Database::getInstance()->getConnection();
    $filialId = $_SESSION['filial_id'] ?? 1;
    
    $stmt = $db->prepare("SELECT id, chave_acesso, fornecedor_nome FROM nfe_importadas WHERE filial_id = ? AND chave_acesso IS NOT NULL LIMIT 1");
    $stmt->execute([$filialId]);
    $nota = $stmt->fetch();
    
    if (!$nota) die("Nenhuma nota encontrada no banco.");
    
    echo "Nota: {$nota['fornecedor_nome']}\n";
    echo "Chave: {$nota['chave_acesso']}\n";
    echo "Chave length: " . strlen($nota['chave_acesso']) . "\n\n";

    // Pegar CNPJ da filial
    $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
    $stmt->execute([$filialId]);
    $cnpjFilial = $stmt->fetchColumn();
    echo "CNPJ Filial: $cnpjFilial\n\n";

    // Instanciar serviço (ele carrega config sozinho)
    $service = new \App\Services\SefazConsultaService();
    
    // Usar reflection para chamar o método privado de geração do XML
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('gerarXmlEventoManifesto');
    $method->setAccessible(true);
    
    $xmlTemplate = $method->invoke($service, $cnpjFilial, $nota['chave_acesso'], '210210');
    
    echo "=== 1. XML TEMPLATE (antes de assinar) ===\n";
    echo $xmlTemplate . "\n\n";
    
    // Carregar config para obter certificado
    $configProp = $reflection->getProperty('config');
    $configProp->setAccessible(true);
    $config = $configProp->getValue($service);
    
    echo "Ambiente: " . $config['ambiente'] . "\n";
    echo "Certificado: " . $config['certificado_path'] . "\n\n";

    // Assinar
    $signer = new \App\Services\SefazSigner();
    $pfxPath = dirname(__DIR__) . "/storage/certificados/" . $config['certificado_path'];
    $signedXml = $signer->signXML($xmlTemplate, $pfxPath, $config['certificado_senha_raw']);
    
    echo "=== 2. XML ASSINADO (após SefazSigner) ===\n";
    echo $signedXml . "\n\n";

    // Simular o que o SoapClient faz
    $xmlBody = preg_replace('/^<\?xml[^>]*\?>/i', '', trim($signedXml));
    
    echo "=== 3. XML BODY (sem declaração XML, vai dentro de nfeDadosMsg) ===\n";
    echo $xmlBody . "\n\n";

    $ns = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4';
    $content = "<nfeDadosMsg xmlns=\"{$ns}\">{$xmlBody}</nfeDadosMsg>";

    $soapEnvelope = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap12:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap12=\"http://www.w3.org/2003/05/soap-envelope\">
    <soap12:Body>
        {$content}
    </soap12:Body>
</soap12:Envelope>";

    echo "=== 4. ENVELOPE SOAP COMPLETO (o que é enviado via cURL) ===\n";
    echo $soapEnvelope . "\n\n";

    // Mostrar último log de debug
    $debugFile = dirname(__DIR__) . '/last_sefaz_debug.txt';
    if (file_exists($debugFile)) {
        echo "=== 5. ÚLTIMA RESPOSTA DA SEFAZ ===\n";
        echo file_get_contents($debugFile) . "\n";
    }
    
    // Mostrar último request enviado
    $reqFile = dirname(__DIR__) . '/storage/last_sefaz_request.xml';
    if (file_exists($reqFile)) {
        echo "\n=== 6. ÚLTIMO REQUEST ENVIADO ===\n";
        echo file_get_contents($reqFile) . "\n";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
