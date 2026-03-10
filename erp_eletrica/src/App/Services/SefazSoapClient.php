<?php
namespace App\Services;

use Exception;

class SefazSoapClient extends BaseService {
    
    // SP Endpoints (NFe & NFC-e)
    private $endpoints = [
        'homologacao' => [
            'nfce_autorizacao' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfceautorizacao4.asmx',
            'nfce_retorno' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfceretautorizacao4.asmx',
            'sefaz_status' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx',
            'nfe_distribuicao' => 'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'nfe_evento' => 'https://homologacao.nfe.fazenda.sp.gov.br/ws/nfeerecepcaoevento4.asmx'
        ],
        'producao' => [
            'nfce_autorizacao' => 'https://nfce.fazenda.sp.gov.br/ws/nfceautorizacao4.asmx',
            'nfce_retorno' => 'https://nfce.fazenda.sp.gov.br/ws/nfceretautorizacao4.asmx',
            'sefaz_status' => 'https://nfce.fazenda.sp.gov.br/ws/nfcestatusservico4.asmx',
            'nfe_distribuicao' => 'https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'nfe_evento' => 'https://nfe.fazenda.sp.gov.br/ws/nfeerecepcaoevento4.asmx'
        ]
    ];

    private $methodMapping = [
        'nfe_distribuicao' => 'nfeDistribuicaoDFe',
        'nfe_evento' => 'nfeRecepcaoEvento4',
        'nfce_autorizacao' => 'nfeAutorizacaoLote',
        'sefaz_status' => 'nfeStatusServico4'
    ];

    public function call($method, $xml, $fiscal) {
        $ambiente = ($fiscal['ambiente'] == 1 || $fiscal['ambiente'] == 'producao') ? 'producao' : 'homologacao';
        $url = $this->endpoints[$ambiente][$method] ?? null;
        if (!$url) throw new Exception("Endpoint SEFAZ não encontrado para o método $method no ambiente $ambiente.");

        $wsdlMethod = $this->methodMapping[$method] ?? $method;

        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscal['certificado_pfx'];
        $password = $fiscal['certificado_senha']; 

        // Prepare temporary PEM files for CURL
        $pemCert = $this->extractPem($pfxPath, $password);
        
        $soapXml = $this->wrapSoap($xml, $wsdlMethod);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapXml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/soap+xml; charset=utf-8; action=\"http://www.portalfiscal.inf.br/nfe/wsdl/$wsdlMethod\"",
            "Content-Length: " . strlen($soapXml)
        ]);
        
        // mTLS Authentication
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCert['file']);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemCert['file']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Cleanup temp file
        @unlink($pemCert['file']);

        if ($error) throw new Exception("Erro de conexão SEFAZ (CURL): $error");
        if ($httpCode >= 400) throw new Exception("Erro HTTP SEFAZ: $httpCode. Verifique a conectividade e o certificado.");
        if (empty($response)) throw new Exception("Resposta vazia da SEFAZ. O servidor pode estar indisponível ou recusou a conexão.");

        return $response;
    }

    private function wrapSoap($xml, $wsdlMethod) {
        // Simple SOAP 1.2 wrapper for SEFAZ 4.0
        return '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <' . $wsdlMethod . ' xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/' . $wsdlMethod . '">
              <nfeDadosMsg>' . $xml . '</nfeDadosMsg>
            </' . $wsdlMethod . '>
          </soap12:Body>
        </soap12:Envelope>';
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
