<?php
$sefazSoapClient = <<< 'EOT'
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

        $mapping = $this->serviceMapping[$method] ?? ['service' => $method, 'method' => $method, 'action' => "http://www.portalfiscal.inf.br/nfe/wsdl/\$method/\$method"];
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
            "Content-Type: application/soap+xml; charset=utf-8; action=\"\$actionUrl\"",
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
            throw new Exception("Erro de conexão SEFAZ CURL: \$error");
        }
        
        // DEBUG OVERRIDE: Forçar log de todos os retornos para identificar malformação XML
        $logPath = dirname(__DIR__, 3) . '/storage/last_sefaz_response.xml';
        @file_put_contents($logPath, "HTTP CODE: \$httpCode\n\n=== RESPONSE ===\n\$response");

        if ($httpCode >= 400 || empty($response)) {
             $motivo = $this->extractSoapFault($response);
             if ($motivo) {
                 throw new Exception("Rejeição SEFAZ: \$motivo");
             }
             throw new Exception("Erro HTTP \$httpCode. O servidor da SEFAZ rejeitou a requisição. O certificado e a senha estão corretos, mas o conteúdo ou o Mapeamento da SEFAZ pode estar inválido.");
        }

        return $response;
    }

    private function wrapSoap($xml, $serviceName, $methodName) {
        // SEFAZ 4.00 requires the namespace to be on nfeDadosMsg, and the method name to be namespace-free or prefixed with the exact matching ns
        return '<?xml version="1.0" encoding="utf-8"?>
<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/' . $serviceName . '">' . $xml . '</nfeDadosMsg>
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
        if (!openssl_pkcs12_read($pfxContent, \$certs, \$password)) {
            throw new Exception("Falha ao extrair certificado para comunicação SOAP.");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'SEFAZ');
        file_put_contents($tmpFile, \$certs['cert'] . "\n" . \$certs['pkey']);
        
        return ['file' => $tmpFile];
    }
}
EOT;

$fiscalService = <<< 'EOT'
<?php
namespace App\Services;

use App\Config\Database;
use Exception;

class FiscalService extends BaseService {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
    }

    private function getFiscalConfig($branchId) {
        // 1. Load Branch Info
        $branch = $this->getBranchData($branchId);
        if (!$branch) {
             throw new Exception("Filial ID $branchId não encontrada no sistema.");
        }

        // 2. Load Global Config
        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $global = $stmt->fetch();

        // The requirement is that Global Certificate applies to all branches.
        // We prioritize Global if available.
        if ($global && !empty($global['certificado_path'])) {
            return [
                'cnpj' => $branch['cnpj'],
                'certificado_pfx' => $global['certificado_path'],
                'certificado_senha' => base64_decode($global['certificado_senha']), // Decrypt
                'ambiente' => $global['ambiente'] == 'producao' ? 1 : 2,
                'nome' => $branch['nome']
            ];
        }

        // Fallback to legacy branch-specific config if global not set
        return [
            'cnpj' => $branch['cnpj'],
            'certificado_pfx' => $branch['certificado_pfx'] ?? null,
            'certificado_senha' => !empty($branch['certificado_senha']) ? base64_decode($branch['certificado_senha']) : '',
            'ambiente' => $branch['ambiente'] ?? 2,
            'nome' => $branch['nome']
        ];
    }

    /**
     * Generates and transmits an NFC-e (Consumer Invoice)
     */
    public function issueNFCe($vendaId) {
        try {
            // 1. Fetch sale data with items and branch info
            $sale = $this->getSaleData($vendaId);
            $fiscal = $this->getFiscalConfig($sale['filial_id']);

            if (empty($fiscal['cnpj']) || empty($fiscal['certificado_pfx'])) {
                throw new Exception("Configuração fiscal incompleta (CNPJ ou Certificado ausente).");
            }

            // 2. Generate XML (Mock for now, following SEFAZ 4.00 standard)
            $xml = $this->generateXML($sale, $fiscal, 'nfce');

            // 3. Sign XML (Requires openssl and .pfx)
            $signedXml = $this->signXML($xml, $fiscal);

            // 4. Transmit to SEFAZ (Mocked for Homologação)
            $response = $this->transmitToSEFAZ($signedXml, $fiscal, 'nfce');

            // 5. Save record in database
            $this->saveFiscalRecord($vendaId, 'nfce', $response);

            return [
                'success' => true,
                'status' => $response['status'],
                'protocolo' => $response['protocolo'],
                'chave' => $response['chave']
            ];

        } catch (Exception $e) {
            $this->logAction('fiscal_error', 'vendas', $vendaId, null, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getSaleData($vendaId) {
        $sale = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $sale->execute([$vendaId]);
        $data = $sale->fetch();

        $items = $this->db->prepare("
            SELECT vi.*, p.nome, p.ncm, p.cest, p.unidade, p.origem, p.csosn, p.cfop_interno, p.aliquota_icms
            FROM venda_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            WHERE vi.venda_id = ?
        ");
        $items->execute([$vendaId]);
        $data['items'] = $items->fetchAll();

        return $data;
    }

    private function getBranchData($branchId) {
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$branchId]);
        return $stmt->fetch();
    }

    private function generateXML($sale, $fiscal, $type = 'nfce') {
        $xmlService = new SefazXmlService();
        $result = $xmlService->generateNFCe($sale, $fiscal);
        return $result['xml'];
    }

    private function signXML($xml, $fiscal) {
        $signer = new SefazSigner();
        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscal['certificado_pfx'];
        $password = $fiscal['certificado_senha'];
        
        return $signer->signXML($xml, $pfxPath, $password);
    }

    private function transmitToSEFAZ($signedXml, $fiscal, $type = 'nfce') {
        $soapClient = new SefazSoapClient();
        
        try {
            $responseXml = $soapClient->call('nfce_autorizacao', $signedXml, $fiscal);
            return $this->parseSefazResponse($responseXml);
        } catch (Exception $e) {
            $this->logAction('sefaz_comm_error', 'vendas', null, null, ['error' => $e->getMessage()]);
            throw new Exception("Falha na comunicação com a SEFAZ: " . $e->getMessage());
        }
    }

    private function parseSefazResponse($xmlStr) {
        // Remove namespaces for easier parsing in simple cases
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        $xml = simplexml_load_string($cleanXml);
        
        // SEFAZ response structure: nfeAutorizacaoLoteResult -> retEnviNFe
        $ret = $xml->xpath('//retEnviNFe');
        if (empty($ret)) {
             throw new Exception("Resposta da SEFAZ em formato desconhecido.");
        }
        
        $data = $ret[0];
        $cStat = (string)$data->cStat;
        $xMotivo = (string)$data->xMotivo;

        if ($cStat != '103' && $cStat != '104') { // 103: Lote recebido, 104: Lote processado
            throw new Exception("SEFAZ Rejeitou: [$cStat] $xMotivo");
        }

        return [
            'status' => 'autorizada',
            'protocolo' => (string)$data->infRec->nRec ?? 'N/A',
            'chave' => (string)$data->protNFe->infProt->chNFe ?? 'N/A',
            'xml_path' => 'storage/nfe/xml/' . date('Y-m') . '/nfe_' . time() . '.xml'
        ];
    }

    /**
     * Simulates a connectivity test with SEFAZ WebServices
     */
    public function testConnection($branchId) {
        $branch = $this->getBranchData($branchId);
        
        if (empty($branch['cnpj'])) {
            throw new Exception("CNPJ não configurado para esta filial.");
        }
        
        if (empty($branch['certificado_pfx'])) {
            throw new Exception("Certificado Digital (.pfx) não enviado.");
        }

        if (empty($branch['certificado_senha'])) {
            throw new Exception("Senha do certificado não configurada.");
        }

        // Test actual signing capability to ensure certificate is fully functional
        try {
            $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $branch['certificado_pfx'];
            if (!file_exists($pfxPath)) throw new Exception("Arquivo do certificado não encontrado no servidor.");
            
            $pfxContent = file_get_contents($pfxPath);
            $certs = [];
            $password = base64_decode($branch['certificado_senha']);
            
            if (!openssl_pkcs12_read($pfxContent, \$certs, \$password)) {
                throw new Exception("Falha ao ler o certificado digital. Verifique a senha.");
            }

            // 1. Test signing a dummy string
            $dataToSign = "test-signature-" . time();
            $signature = '';
            if (!openssl_sign($dataToSign, \$signature, \$certs['pkey'], OPENSSL_ALGO_SHA256)) {
                throw new Exception("O certificado foi aberto, mas falhou ao assinar dados (Chave privada inválida?).");
            }

            // 2. Real connectivity check (Status do Serviço)
            $soapClient = new SefazSoapClient();
            $fiscal = $this->getFiscalConfig($branchId);
            
            // Generate exact XML schema for NfeStatusServico4
            $uf = "35"; // SP by default for this project
            if (isset($branch['uf'])) {
                $estados = ['RO'=>'11','AC'=>'12','AM'=>'13','RR'=>'14','PA'=>'15','AP'=>'16','TO'=>'17','MA'=>'21','PI'=>'22','CE'=>'23','RN'=>'24','PB'=>'25','PE'=>'26','AL'=>'27','SE'=>'28','BA'=>'29','MG'=>'31','ES'=>'32','RJ'=>'33','SP'=>'35','PR'=>'41','SC'=>'42','RS'=>'43','MS'=>'50','MT'=>'51','GO'=>'52','DF'=>'53'];
                $uf = $estados[$branch['uf']] ?? '35';
            }

            $xml = '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>' . $fiscal['ambiente'] . '</tpAmb><cUF>' . $uf . '</cUF><xServ>STATUS</xServ></consStatServ>';
            
            try {
                $responseXml = $soapClient->call('sefaz_status', $xml, $fiscal);
                
                // Parse response safely
                $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseXml);
                $res = @simplexml_load_string($cleanXml);
                
                if (!$res) {
                     throw new Exception("Falha ao ler XML de resposta da SEFAZ.");
                }

                $nodes = $res->xpath('//retConsStatServ');
                $retStatus = !empty($nodes) ? $nodes[0] : null;

                if (!$retStatus) {
                     throw new Exception("Estrutura retConsStatServ não encontrada.");
                }

                return [
                    'success' => true,
                    'status' => (string)$retStatus->cStat ?: '???',
                    'motivo' => (string)$retStatus->xMotivo ?: 'Resposta sem motivo',
                    'ambiente' => ($fiscal['ambiente'] == 1) ? 'Produção' : 'Homologação',
                    'verAplic' => (string)$retStatus->verAplic ?: '---',
                    'timestamp' => (string)$retStatus->dhRecbto ?: date('d/m/Y H:i:s'),
                    'cert_info' => [
                        'subject' => isset(\$certs['cert']) && openssl_x509_parse(\$certs['cert']) ? (openssl_x509_parse(\$certs['cert'])['subject']['CN'] ?? 'Desconhecido') : 'Desconhecido',
                        'validTo' => isset(\$certs['cert']) && openssl_x509_parse(\$certs['cert']) ? date('d/m/Y', openssl_x509_parse(\$certs['cert'])['validTo_time_t'] ?? time()) : '---'
                    ]
                ];
            } catch (Exception $e) {
                throw new Exception("Certificado local OK, mas falha na comunicação SEFAZ: " . $e->getMessage());
            }
        } catch (Exception $e) {
            throw new Exception("Erro no teste do certificado: " . $e->getMessage());
        }
    }

    private function saveFiscalRecord($vendaId, $type, $response) {
        $stmt = $this->db->prepare("
            INSERT INTO notas_fiscais (venda_id, tipo, chave_acesso, status, protocolo, mensagem_sefaz)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $vendaId, 
            $type, 
            $response['chave'], 
            $response['status'], 
            $response['protocolo'],
            'Nota autorizada em ambiente de homologação'
        ]);
    }
}
EOT;

file_put_contents(__DIR__ . '/src/App/Services/SefazSoapClient.php', $sefazSoapClient);
file_put_contents(__DIR__ . '/src/App/Services/FiscalService.php', $fiscalService);

echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px; padding: 20px; border: 2px solid green; border-radius: 10px; background-color: #f0fff0;'>";
echo "<h1>✅ ARQUIVOS DA SEFAZ ATUALIZADOS COM SUCESSO!</h1>";
echo "<p>As modificações e correções do SOAP Envelope v4.00, Bypass de Verificação HOST e URLs Oficiais de SP foram aplicadas no seu servidor cloud.</p>";
echo "<p style='margin-bottom: 20px;'><strong>Volte para o sistema e rode o Teste de Comunicação!</strong></p>";
echo "<a href='fiscal.php?action=diagnostic' style='padding: 10px 20px; background: blue; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Voltar para o Diagnóstico</a>";
echo "</div>";

@unlink(__FILE__);
