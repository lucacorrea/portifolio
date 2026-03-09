<?php
namespace App\Services;

use Exception;
use DOMDocument;

class SefazSigner extends BaseService {
    
    public function signXML($xmlString, $pfxPath, $password) {
        if (!file_exists($pfxPath)) throw new Exception("Arquivo de certificado não encontrado: $pfxPath");
        
        $pfxContent = file_get_contents($pfxPath);
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new Exception("Falha ao ler o certificado digital para assinatura.");
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xmlString);

        $node = $dom->getElementsByTagName('infNFe')->item(0);
        $id = $node->getAttribute('Id');

        // C14N - Canonicalization
        $canonInfNFe = $node->C14N(false, false);
        $digestValue = base64_encode(hash('sha1', $canonInfNFe, true));

        // Create Signature Node
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        $dom->documentElement->appendChild($signature);

        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        $cm = $dom->createElement('CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($cm);

        $sm = $dom->createElement('SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sm);

        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $id);
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $t1 = $dom->createElement('Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);

        $t2 = $dom->createElement('Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t2);

        $dm = $dom->createElement('DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($dm);

        $dv = $dom->createElement('DigestValue', $digestValue);
        $reference->appendChild($dv);

        // Sign SignedInfo
        $canonSignedInfo = $signedInfo->C14N(false, false);
        $signatureValue = '';
        openssl_sign($canonSignedInfo, $signatureValue, $certs['pkey'], OPENSSL_ALGO_SHA1);
        
        $sv = $dom->createElement('SignatureValue', base64_encode($signatureValue));
        $signature->appendChild($sv);

        $keyInfo = $dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);

        // Clean certificate string
        $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certs['cert']);
        $x509Cert = $dom->createElement('X509Certificate', $cleanCert);
        $x509Data->appendChild($x509Cert);

        return $dom->saveXML();
    }
}
