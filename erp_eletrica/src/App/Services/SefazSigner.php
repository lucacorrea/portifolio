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

        // Find the node to sign (infNFe or infEvento)
        $tagToSign = strpos($xmlString, '<infNFe') !== false ? 'infNFe' : 'infEvento';
        $rootName = $tagToSign === 'infEvento' ? 'evento' : 'NFe';

        try {
            return \NFePHP\Common\Signer::sign(
                $certificate,
                $xmlString,
                $tagToSign,
                'Id',
                OPENSSL_ALGO_SHA1,
                \NFePHP\Common\Signer::CANONICAL,
                $rootName
            );
        } catch (\Exception $e) {
            throw new Exception("Falha na assinatura do XML (NFePHP Signer): " . $e->getMessage());
        }
    }
}
