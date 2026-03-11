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
            'action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe'
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
        
        // Remove declaração XML do miolo
        $xmlBody = preg_replace('/^<\?xml[^>]*\?>/i', '', trim($xml));
        
        // Exact NfePHP Replica of the wrapper
        $request = "<nfeDadosMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/{$serviceName}\">{$xmlBody}</nfeDadosMsg>";
        
        $soapXml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">'
            . '<env:Body>'
            . $request
            . '</env:Body>'
            . '</env:Envelope>';

        // DEBUG: Gravar último XML enviado para inspeção
        if (defined('DEBUG') && DEBUG) {
            $logPath = dirname(__DIR__, 3) . '/storage/last_sefaz_request.xml';
            @file_put_contents($logPath, $soapXml);
        }

        $msgSize = strlen($soapXml);
        $parameters = [
             "Content-Type: application/soap+xml;charset=utf-8;action=\"{$actionUrl}\"",
             "Content-length: {$msgSize}"
        ];

        // Exact NfePHP Replica of cURL params
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_HEADER, 0); // Modificado do NFePHP para nAo poluir o responsebody
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Igual no NFePHP para bypass
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Igual no NFePHP para bypass
        
        // SSL Version 6 = TLSv1.2 in modern PHP (CURL_SSLVERSION_TLSv1_2)
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        
        // Cert keys
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCert['file']);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemCert['file']);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapXml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $parameters);

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
             throw new Exception("Erro HTTP $httpCode. O servidor da SEFAZ rejeitou a requisição. XML recebido: " . htmlspecialchars(substr($response, 0, 500)));
        }

        return $response;
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
