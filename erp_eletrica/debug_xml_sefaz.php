<?php
require 'config.php';

try {
    // 1. Get first configured branch
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM filiais WHERE certificado_pfx IS NOT NULL LIMIT 1");
    $branch = $stmt->fetch();
    
    if(!$branch) { die("No branch with certificate."); }

    // 2. Mock Fiscal Config
    $fiscal = [
        'cnpj' => $branch['cnpj'],
        'certificado_pfx' => $branch['certificado_pfx'],
        'certificado_senha' => base64_decode($branch['certificado_senha']),
        'ambiente' => $branch['ambiente'] == 1 ? 1 : 2
    ];

    $uf = "35"; 
    $xml = '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>' . $fiscal['ambiente'] . '</tpAmb><cUF>' . $uf . '</cUF><xServ>STATUS</xServ></consStatServ>';
    
    // 3. Call SEFAZ
    $soapClient = new \App\Services\SefazSoapClient();
    $responseXml = $soapClient->call('sefaz_status', $xml, $fiscal);
    
    echo "<h3>RAW XML RESPONSE FROM SEFAZ SP</h3>";
    echo "<textarea style='width:100%; height:400px; font-family:monospace;'>" . htmlspecialchars($responseXml) . "</textarea>";

} catch (Exception $e) {
    echo "<h3>ERROR:</h3><p>" . $e->getMessage() . "</p>";
}
