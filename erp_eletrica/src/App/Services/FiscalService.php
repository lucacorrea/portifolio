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

    /**
     * Generates and transmits an NFC-e (Consumer Invoice)
     */
    public function issueNFCe($vendaId) {
        try {
            // 1. Fetch sale data with items and branch info
            $sale = $this->getSaleData($vendaId);
            $branch = $this->getBranchData($sale['filial_id']);

            if (empty($branch['cnpj']) || empty($branch['certificado_pfx'])) {
                throw new Exception("Configuração fiscal da filial incompleta (CNPJ ou Certificado ausente).");
            }

            // 2. Generate XML (Mock for now, following SEFAZ 4.00 standard)
            $xml = $this->generateXML($sale, $branch, 'nfce');

            // 3. Sign XML (Requires openssl and .pfx)
            $signedXml = $this->signXML($xml, $branch);

            // 4. Transmit to SEFAZ (Mocked for Homologação)
            $response = $this->transmitToSEFAZ($signedXml, $branch, 'nfce');

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

    private function generateXML($sale, $branch, $type) {
        // Here we would use a library or manual DOMDocument to build the NFe XML
        // Mocking a standard SEFAZ structure
        $now = date('Y-m-d\TH:i:sP');
        return "<?xml version='1.0' encoding='UTF-8'?><NFe xmlns='http://www.portalfiscal.inf.br/nfe'><infNFe versao='4.00' Id='NFe35...'><ide><cUF>35</cUF><cNF>".rand(10000000,99999999)."</cNF>...</ide></infNFe></NFe>";
    }

    private function signXML($xml, $branch) {
        // Digital signing logic with OpenSSL
        // This requires the .pfx file to be read with openssl_pkcs12_read
        return $xml; // Returning original for mock
    }

    private function transmitToSEFAZ($signedXml, $branch, $type) {
        // In a real scenario, this uses cURL to SEFAZ Web Services
        // Mocking a successful response for Homologação
        return [
            'status' => 'autorizada',
            'protocolo' => '135' . rand(100000000, 999999999),
            'chave' => '35' . date('ym') . str_replace(['.', '/', '-'], '', $branch['cnpj']) . '55001' . str_pad($sale['id'] ?? rand(1,999), 9, '0', STR_PAD_LEFT) . '1' . rand(10000000, 99999999),
            'xml_path' => 'storage/nfe/xml/' . date('Y-m') . '/nfe_mock.xml'
        ];
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
