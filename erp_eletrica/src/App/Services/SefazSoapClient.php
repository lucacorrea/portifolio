<?php
namespace App\Services;

use Exception;

class SefazSoapClient extends BaseService {
    
    // SP Endpoints (NFe & NFC-e)
    private $endpoints = [
        'homologacao' => [
            'nfce_autorizacao' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfceautorizacao4.asmx',
            'nfce_retorno' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfceretautorizacao4.asmx',
            'sefaz_status' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfestatusservico4.asmx',
            'nfe_distribuicao' => 'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'nfe_evento' => 'https://homologacao.nfe.fazenda.sp.gov.br/ws/nfeerecepcaoevento4.asmx'
        ],
        'producao' => [
            'nfce_autorizacao' => 'https://nfce.fazenda.sp.gov.br/ws/nfceautorizacao4.asmx',
            'nfce_retorno' => 'https://nfce.fazenda.sp.gov.br/ws/nfceretautorizacao4.asmx',
            'sefaz_status' => 'https://nfce.fazenda.sp.gov.br/ws/nfestatusservico4.asmx',
            'nfe_distribuicao' => 'https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'nfe_evento' => 'https://nfe.fazenda.sp.gov.br/ws/nfeerecepcaoevento4.asmx'
        ]
    ];

    private $serviceMapping = [
        'nfe_distribuicao' => [
            'service' => 'NFeDistribuicaoDFe', 
            'method' => 'nfeDistribuicaoDFe',
            'action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe' // Não tem suffix de método
        ],
        'nfe_evento' => [
            'service' => 'NFeRecepcaoEvento4', 
            'method' => 'nfeRecepcaoEvento',
            'action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4/nfeRecepcaoEvento'
        ],
        'nfce_autorizacao' => [
            'service' => 'NFeAutorizacao4', 
            'method' => 'nfeAutorizacaoLote',
            'action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4/nfeAutorizacaoLote'
        ],
        'sefaz_status' => [
            'service' => 'NFeStatusServico4', 
            'method' => 'nfeStatusServicoNF',
            'action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4/nfeStatusServicoNF'
        ]
    ];

    public function call($method, $xml, $fiscal) {
        if (!extension_loaded('curl')) throw new Exception("Extensão CURL não está carregada no PHP.");

        $ambiente = ($fiscal['ambiente'] == 1 || $fiscal['ambiente'] == 'producao') ? 'producao' : 'homologacao';
        $url = $this->endpoints[$ambiente][$method] ?? null;
        if (!$url) throw new Exception("Endpoint SEFAZ não encontrado para o método $method no ambiente $ambiente.");

        $mapping = $this->serviceMapping[$method] ?? ['service' => $method, 'method' => $method, 'action' => "http://www.portalfiscal.inf.br/nfe/wsdl/$method/$method"];
        $serviceName = $mapping['service'];
        $methodName = $mapping['method'];
        $actionUrl = $mapping['action'];

        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscal['certificado_pfx'];
        $password = $fiscal['certificado_senha']; 

        // Prepare temporary PEM files for CURL
        $pemCert = $this->extractPem($pfxPath, $password);
        
        // SEFAZ 4.00: O conteúdo de nfeDadosMsg NÃO deve ter a declaração XML
        $xmlBody = preg_replace('/^<\?xml[^>]*\?>/i', '', trim($xml));
        
        $soapXml = $this->wrapSoap($xmlBody, $serviceName, $methodName);

        // DEBUG: Gravar último XML enviado para inspeção
        if (defined('DEBUG') && DEBUG) {
            $logPath = dirname(__DIR__, 3) . '/storage/last_sefaz_request.xml';
            @file_put_contents($logPath, $soapXml);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapXml);
        
        // Use explicitly defined action for each method
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/soap+xml; charset=utf-8; action=\"$actionUrl\"",
            "Content-Length: " . strlen($soapXml)
        ]);
        
        // mTLS Authentication
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCert['file']);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemCert['file']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Cleanup temp file
        @unlink($pemCert['file']);

        if ($error) {
            throw new Exception("Erro de conexão SEFAZ CURL: $error");
        }
        
        // DEBUG OVERRIDE: Forçar log de todos os retornos para identificar malformação XML
        $logPath = dirname(__DIR__, 3) . '/storage/last_sefaz_response.xml';
        @file_put_contents($logPath, "HTTP CODE: $httpCode\n\n=== RESPONSE ===\n$response");

        if ($httpCode >= 400 || empty($response)) {
             $motivo = $this->extractSoapFault($response);
             if ($motivo) {
                 throw new Exception("Rejeição SEFAZ: $motivo");
             }
             throw new Exception("Erro HTTP $httpCode. O servidor da SEFAZ rejeitou a requisição. O certificado e a senha estão corretos, mas o conteúdo ou o Mapeamento da SEFAZ pode estar inválido.");
        }

        return $response;
    }

    private function wrapSoap($xml, $serviceName, $methodName) {
        // SEFAZ 4.00 requires the namespace to be on nfeDadosMsg, and the method name to be namespace-free or prefixed with the exact matching ns
        return '<?xml version="1.0" encoding="utf-8"?>
<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <' . $methodName . '>
      <nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/' . $serviceName . '">' . $xml . '</nfeDadosMsg>
    </' . $methodName . '>
  </soap12:Body>
</soap12:Envelope>';
    }

    private function extractSoapFault($response) {
        if (empty($response)) return null;
        try {
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = @simplexml_load_string($cleanXml);
            if ($xml && isset($xml->Body->Fault)) {
                return (string)($xml->Body->Fault->Reason->Text ?? $xml->Body->Fault->faultstring);
            }
        } catch (Exception $e) {}
        return null;
    }

    private function extractPem($pfxPath, $password) {
        $pfxContent = file_get_contents($pfxPath);
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new Exception("Falha ao extrair certificado para comunicação SOAP.");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'SEFAZ');
        file_put_contents($tmpFile, $certs['cert'] . "\n" . $certs['pkey']);
        
        return ['file' => $tmpFile];
    }
}
