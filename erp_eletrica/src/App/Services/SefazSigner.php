<?php
namespace App\Services;

use Exception;
use DOMDocument;

class SefazSigner extends BaseService {
    
    public function signXML($xmlString, $pfxPath, $password) {
        if (!file_exists($pfxPath)) throw new Exception("Arquivo de certificado não encontrado: $pfxPath");
        
        $pfxContent = file_get_contents($pfxPath);
        
        require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
        
        try {
            $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, $password);
            $certs = [
                'cert' => $certificate->certificate,
                'pkey' => $certificate->privateKey
            ];
        } catch (\Exception $e) {
            throw new Exception("Falha ao ler o certificado digital NFePHP: " . $e->getMessage());
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xmlString);

        // Find the node to sign (infNFe or infEvento)
        $node = null;
        $tagsToSign = ['infNFe', 'infEvento'];
        foreach ($tagsToSign as $tag) {
            $node = $dom->getElementsByTagName($tag)->item(0);
            if ($node) break;
        }

        if (!$node) throw new Exception("Nenhum nó passível de assinatura encontrado no XML.");
        $id = $node->getAttribute('Id');

        // C14N - Canonicalization
        $canonInfNFe = $node->C14N(false, false);
        $digestValue = base64_encode(hash('sha256', $canonInfNFe, true));

        // Create Signature Node
        $dsigNS = 'http://www.w3.org/2000/09/xmldsig#';
        $signature = $dom->createElementNS($dsigNS, 'Signature');
        
        // CORREÇÃO: Anexar no nó pai do elemento assinado (Ex: nó 'evento' ou 'NFe')
        $node->parentNode->appendChild($signature);

        $signedInfo = $dom->createElementNS($dsigNS, 'SignedInfo');
        $signature->appendChild($signedInfo);

        $cm = $dom->createElementNS($dsigNS, 'CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($cm);

        $sm = $dom->createElementNS($dsigNS, 'SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sm);

        $reference = $dom->createElementNS($dsigNS, 'Reference');
        $reference->setAttribute('URI', '#' . $id);
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElementNS($dsigNS, 'Transforms');
        $reference->appendChild($transforms);

        $t1 = $dom->createElementNS($dsigNS, 'Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);

        $t2 = $dom->createElementNS($dsigNS, 'Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t2);

        $dm = $dom->createElementNS($dsigNS, 'DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($dm);

        $dv = $dom->createElementNS($dsigNS, 'DigestValue', $digestValue);
        $reference->appendChild($dv);

        // Sign SignedInfo
        $canonSignedInfo = $signedInfo->C14N(false, false);
        $signatureValue = '';
        openssl_sign($canonSignedInfo, $signatureValue, $certs['pkey'], OPENSSL_ALGO_SHA256);
        
        $sv = $dom->createElementNS($dsigNS, 'SignatureValue', base64_encode($signatureValue));
        $signature->appendChild($sv);

        $keyInfo = $dom->createElementNS($dsigNS, 'KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $dom->createElementNS($dsigNS, 'X509Data');
        $keyInfo->appendChild($x509Data);

        // Clean certificate string
        $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certs['cert']);
        $x509Cert = $dom->createElementNS($dsigNS, 'X509Certificate', $cleanCert);
        $x509Data->appendChild($x509Cert);

        return $dom->saveXML();
    }
}
