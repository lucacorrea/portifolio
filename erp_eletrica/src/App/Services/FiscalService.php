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

    public function getFiscalConfig($branchId) {
        // 1. Load Branch Info
        $branch = $this->getBranchData($branchId);
        if (!$branch) {
             throw new Exception("Filial ID $branchId não encontrada no sistema.");
        }

        // 2. Load Global Config
        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $global = $stmt->fetch();

        // Unificação: Prioriza Configuração Global da Matriz
        if ($global && !empty($global['certificado_path'])) {
            return [
                'cnpj' => $branch['cnpj'],
                'certificado_pfx' => $global['certificado_path'],
                'certificado_senha' => $global['certificado_senha'],
                'ambiente' => $global['ambiente'] == 'producao' ? 1 : 2,
                'csc_id' => $global['csc_id'] ?: ($branch['csc_id'] ?? ''),
                'csc_token' => $global['csc'] ?: ($branch['csc_token'] ?? ''),
                'nome' => $branch['nome']
            ];
        }

        // Fallback para campos da filial (legado/específico)
        return [
            'cnpj' => $branch['cnpj'],
            'certificado_pfx' => $branch['certificado_pfx'] ?? null,
            'certificado_senha' => $branch['certificado_senha'] ?? '',
            'ambiente' => $branch['ambiente'] ?? 2,
            'csc_id' => $branch['csc_id'] ?? '',
            'csc_token' => $branch['csc_token'] ?? '',
            'nome' => $branch['nome']
        ];
    }

    /**
     * Generates and transmits an NFC-e (Consumer Invoice)
     */
    public function issueNFCe($vendaId, $isContingency = false, $justification = 'Falha na conexao com SEFAZ') {
        try {
            // 1. Fetch sale data with items and branch info
            $sale = $this->getSaleData($vendaId);
            $fiscal = $this->getFiscalConfig($sale['filial_id']);

            if (empty($fiscal['cnpj']) || empty($fiscal['certificado_pfx'])) {
                throw new Exception("Configuração fiscal incompleta (CNPJ ou Certificado ausente).");
            }

            // 2. Definir Tipo de Emissão (1-Normal, 9-Contingência)
            $tpEmis = $isContingency ? 9 : 1;

            // 3. Generate XML
            $xml = $this->generateXML($sale, $fiscal, 'nfce', $tpEmis, $justification);

            // 4. Sign XML
            $signedXml = $this->signXML($xml, $fiscal);

            if ($tpEmis == 1) {
                // 5a. Transmit to SEFAZ (Normal mode)
                try {
                    $response = $this->transmitToSEFAZ($signedXml, $fiscal, 'nfce');
                    $this->saveFiscalRecord($vendaId, 'nfce', $response, 1);
                } catch (Exception $e) {
                    // SE FALHAR A CONEXÃO, TENTA AUTOMATICAMENTE A CONTINGÊNCIA
                    if (strpos($e->getMessage(), 'comunicação') !== false || strpos($e->getMessage(), 'Timeout') !== false) {
                        return $this->issueNFCe($vendaId, true, $e->getMessage());
                    }
                    throw $e;
                }
            } else {
                // 5b. Contingency Mode: Save signed XML without transmitting
                $response = [
                    'status' => 'contingencia',
                    'protocolo' => 'PENDENTE',
                    'chave' => $this->extractChaveFromXml($signedXml),
                    'xml_path' => 'storage/nfe/contingencia/' . date('Y-m-d') . '/nfe_' . $vendaId . '.xml',
                    'tpEmis' => 9,
                    'dhCont' => date('Y-m-d H:i:s'),
                    'xJust' => $justification
                ];
                $this->saveFiscalRecord($vendaId, 'nfce', $response, 9);
            }

            return [
                'success' => true,
                'status' => $response['status'],
                'protocolo' => $response['protocolo'],
                'chave' => $response['chave'],
                'tpEmis' => $tpEmis
            ];

        } catch (Exception $e) {
            $this->logAction('fiscal_error', 'vendas', $vendaId, null, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function extractChaveFromXml($xml) {
        if (preg_match('/Id="NFe(\d{44})"/', $xml, $matches)) {
            return $matches[1];
        }
        return 'N/A';
    }

    private function getSaleData($vendaId) {
        $sale = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $sale->execute([$vendaId]);
        $data = $sale->fetch();

        $items = $this->db->prepare("
            SELECT vi.*, p.nome, p.ncm, p.cest, p.unidade, p.origem, p.csosn, p.cfop_interno, p.aliquota_icms
            FROM vendas_itens vi
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
     * Tests SEFAZ connectivity using NFePHP Tools - exactly like Açaidinhos does.
     */
    public function testConnection($branchId) {
        $branch   = $this->getBranchData($branchId);
        $fiscal   = $this->getFiscalConfig($branchId);

        if (empty($branch['cnpj'])) {
            throw new Exception("CNPJ não configurado para esta filial.");
        }
        if (empty($fiscal['certificado_pfx'])) {
            throw new Exception("Certificado não configurado (nem globalmente nem na filial).");
        }
        if (empty($fiscal['certificado_senha'])) {
            throw new Exception("Senha do certificado não configurada.");
        }

        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscal['certificado_pfx'];
        if (!file_exists($pfxPath)) {
            throw new Exception("Arquivo do certificado não encontrado no servidor.");
        }

        require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';

        // Step 1: Load certificate – exactly like Açaidinhos
        $pfxContent  = file_get_contents($pfxPath);
        $password    = $fiscal['certificado_senha'];

        try {
            $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, $password);
        } catch (\Exception $e) {
            throw new Exception("Falha ao ler o certificado digital: " . $e->getMessage());
        }

        if ($certificate->isExpired()) {
            throw new Exception("O certificado digital está expirado em: " . $certificate->getValidTo()->format('d/m/Y H:i:s'));
        }

        // Step 2: Build Config JSON for NFePHP Tools – exactly like Açaidinhos' nfce_config.php
        $siglaUF   = $branch['uf'] ?? 'SP';
        $tpAmb     = (int)($fiscal['ambiente'] ?? 2);
        $configArray = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb'       => $tpAmb,
            'razaosocial' => $branch['nome'] ?? 'ERP',
            'siglaUF'     => $siglaUF,
            'cnpj'        => preg_replace('/\D/', '', $branch['cnpj']),
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'urlChave'    => '',
            'urlQRCode'   => '',
            'CSC'         => $fiscal['csc_token'] ?? '',
            'CSCid'       => str_pad($fiscal['csc_id'] ?? '', 6, '0', STR_PAD_LEFT),
            'proxyConf'   => ['proxyIp' => '', 'proxyPort' => '', 'proxyUser' => '', 'proxyPass' => ''],
        ];
        $configJson = json_encode($configArray, JSON_UNESCAPED_UNICODE);

        // Step 3: Use NFePHP Tools exactly like Açaidinhos status.php
        try {
            $tools = new \NFePHP\NFe\Tools($configJson, $certificate);
            $tools->model('65'); // NFC-e model

            $xml = $tools->sefazStatus();

            // Step 4: Standardize response – exactly like Açaidinhos
            $std = new \NFePHP\NFe\Common\Standardize();
            $std = $std->toStd($xml);

            return [
                'success'   => true,
                'status'    => (string)($std->cStat ?? '???'),
                'motivo'    => (string)($std->xMotivo ?? 'Resposta sem motivo'),
                'ambiente'  => $tpAmb == 1 ? 'Produção' : 'Homologação',
                'verAplic'  => (string)($std->verAplic ?? '---'),
                'timestamp' => (string)($std->dhRecbto ?? date('d/m/Y H:i:s')),
            ];

        } catch (\Exception $e) {
            throw new Exception("Erro no teste do certificado: " . $e->getMessage());
        }
    }

    private function saveFiscalRecord($vendaId, $type, $response, $tpEmis = 1) {
        $stmt = $this->db->prepare("
            INSERT INTO nfce_emitidas (
                venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem,
                tpEmis, dhCont, xJust, sync_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status_sefaz = VALUES(status_sefaz),
                protocolo = VALUES(protocolo),
                tpEmis = VALUES(tpEmis),
                dhCont = VALUES(dhCont),
                xJust = VALUES(xJust),
                sync_status = VALUES(sync_status)
        ");

        $stmt->execute([
            $vendaId,
            defined('TP_AMB') ? TP_AMB : 2,
            defined('NFC_SERIE') ? NFC_SERIE : 1,
            mt_rand(1000, 9999), // Provisório, o certo é pegar o sequencial do banco
            $response['chave'],
            $response['protocolo'],
            $response['status'] === 'autorizada' ? '100' : ($response['status'] === 'contingencia' ? '9' : '0'),
            $response['status'] === 'contingencia' ? 'Emitida em Contingência' : 'Nota processada',
            $tpEmis,
            $response['dhCont'] ?? null,
            $response['xJust'] ?? null,
            'pending'
        ]);
    }
}
