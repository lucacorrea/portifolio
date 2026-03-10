<?php
$url = 'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx?wsdl';
echo "Fetching WSDL from $url...\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$wsdl = curl_exec($ch);
curl_close($ch);

if ($wsdl) {
    preg_match_all('/soapAction="([^"]+)"/', $wsdl, $matches);
    echo "Found SOAP Actions:\n";
    print_r(array_unique($matches[1]));
} else {
    echo "Failed to fetch WSDL via CURL in PHP.\n";
}
?>
