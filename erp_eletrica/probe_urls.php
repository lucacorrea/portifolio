<?php
$urls = [
    // SP NFe
    'https://homologacao.nfe.fazenda.sp.gov.br/ws/nfestatusservico4.asmx?wsdl',
    'https://nfe.fazenda.sp.gov.br/ws/nfestatusservico4.asmx?wsdl',
    // SP NFCe
    'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx?wsdl',
    'https://nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx?wsdl',
    // Alternative names
    'https://homologacao.nfe.fazenda.sp.gov.br/ws/nfestatusservico4.asmx',
    'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx'
];
$out = "";
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $out .= "URL: $url -> HTTP $code\n";
    curl_close($ch);
}
file_put_contents(__DIR__ . '/storage/probe_results.txt', $out);
echo "Done";
