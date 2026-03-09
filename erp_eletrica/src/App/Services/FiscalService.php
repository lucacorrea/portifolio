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
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$branchId]);
        $branch = $stmt->fetch();

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
            'certificado_pfx' => $branch['certificado_pfx'],
            'certificado_senha' => !empty($branch['certificado_senha']) ? base64_decode($branch['certificado_senha']) : '',
            'ambiente' => $branch['ambiente'],
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
            
            if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
                throw new Exception("Falha ao ler o certificado digital. Verifique a senha.");
            }

            // Test signing a dummy string
            $dataToSign = "test-signature-" . time();
            $signature = '';
            if (!openssl_sign($dataToSign, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256)) {
                throw new Exception("O certificado foi aberto, mas falhou ao assinar dados (Chave privada inválida?).");
            }
            
            return [
                'success' => true,
                'status' => '107',
                'motivo' => 'Certificado Válido e Operacional',
                'ambiente' => ($branch['ambiente'] == 1 || $branch['ambiente'] == 'producao') ? 'Produção' : 'Homologação',
                'verAplic' => 'ERP_ELET_V1',
                'timestamp' => date('d/m/Y H:i:s'),
                'cert_info' => [
                    'subject' => openssl_x509_parse($certs['cert'])['subject']['CN'] ?? 'Desconhecido',
                    'validTo' => date('d/m/Y', openssl_x509_parse($certs['cert'])['validTo_time_t'])
                ]
            ];
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
