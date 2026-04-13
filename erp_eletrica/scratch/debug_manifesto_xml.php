<?php
require_once 'erp_eletrica/config.php';

$cnpj = "00000000000000"; // Dummy
$chave = "35240400000000000000550010000000011000000000"; // Dummy 35 (SP)
$tpEvento = '210210'; // Ciencia

try {
    $service = new \App\Services\SefazConsultaService();
    
    // We need to bypass the real signing and calling for inspection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('gerarXmlEventoManifesto');
    $method->setAccessible(true);
    
    $xml = $method->invoke($service, $cnpj, $chave, $tpEvento);
    
    echo "=== RAW XML ===\n";
    echo $xml . "\n\n";
    
    // Sign it (dry run)
    $signer = new \App\Services\SefazSigner();
    
    // We need a dummy certificate or just bypass it
    // Let's just inspect the XML before signing
    
    // Actually, I'll try to sign it if I can find a certificate, but I shouldn't need to.
    // The schema error 225 is often about the structure of the XML tags.
    
    echo "=== STRUCTURE CHECK ===\n";
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    echo "Root: " . $dom->documentElement->nodeName . "\n";
    echo "Namespace: " . $dom->documentElement->namespaceURI . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
