<?php
$urls = [
    'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx?wsdl',
    'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx?wsdl'
];
foreach($urls as $url) {
    echo "WSDL: $url\n";
    $wsdl = @file_get_contents($url);
    if($wsdl) {
        preg_match_all('/soapAction="([^"]+)"/', $wsdl, $matches);
        echo "Actions: \n";
        print_r(array_unique($matches[1]));
        preg_match_all('/targetNamespace="([^"]+)"/', $wsdl, $matches);
        echo "Namespaces: \n";
        print_r(array_unique($matches[1]));
    } else {
        echo "Could not fetch.\n";
    }
    echo "=================================\n";
}
