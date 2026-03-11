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

        if ($global && !empty($global['certificado_path'])) {
            return [
                'cnpj' => $branch['cnpj'],
                'certificado_pfx' => $global['certificado_path'],
                'certificado_senha' => base64_decode($global['certificado_senha']), // Decrypt
                'ambiente' => $global['ambiente'] == 'producao' ? 1 : 2,
                'nome' => $branch['nome'],
                'codigo_uf' => $branch['codigo_uf'] ?? $global['codigo_uf'] ?? '35',
                'codigo_municipio' => $branch['codigo_municipio'] ?? $global['codigo_municipio'] ?? '3550308',
                'uf' => $branch['uf'] ?? $global['uf'] ?? 'SP',
                'cidade' => $branch['cidade'] ?? $global['cidade'] ?? 'SAO PAULO',
                'bairro' => $branch['bairro'] ?? $global['bairro'] ?? '',
                'cep' => $branch['cep'] ?? $global['cep'] ?? '',
                'inscricao_estadual' => $branch['inscricao_estadual'] ?? $global['inscricao_estadual'] ?? ''
            ];
        }

        return [
            'cnpj' => $branch['cnpj'],
            'certificado_pfx' => $branch['certificado_pfx'] ?? null,
            'certificado_senha' => !empty($branch['certificado_senha']) ? base64_decode($branch['certificado_senha']) : '',
            'ambiente' => $branch['ambiente'] ?? 2,
            'nome' => $branch['nome'],
            'codigo_uf' => $branch['codigo_uf'] ?? '35',
            'codigo_municipio' => $branch['codigo_municipio'] ?? '3550308',
            'uf' => $branch['uf'] ?? 'SP',
            'cidade' => $branch['cidade'] ?? 'SAO PAULO',
            'bairro' => $branch['bairro'] ?? '',
            'cep' => $branch['cep'] ?? '',
            'inscricao_estadual' => $branch['inscricao_estadual'] ?? ''
        ];
    }

    public function issueNFCe($vendaId) {
        try {
            $sale = $this->getSaleData($vendaId);
            $fiscal = $this->getFiscalConfig($sale['filial_id']);

            if (empty($fiscal['cnpj']) || empty($fiscal['certificado_pfx'])) {
                throw new Exception("Configuração fiscal incompleta (CNPJ ou Certificado ausente).");
            }

            $xml = $this->generateXML($sale, $fiscal, 'nfce');
            $signedXml = $this->signXML($xml, $fiscal);
            $response = $this->transmitToSEFAZ($signedXml, $fiscal, 'nfce');
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
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        $xml = simplexml_load_string($cleanXml);
        
        $ret = $xml->xpath('//retEnviNFe');
        if (empty($ret)) {
             throw new Exception("Resposta da SEFAZ em formato desconhecido.");
        }
        
        $data = $ret[0];
        $cStat = (string)$data->cStat;
        $xMotivo = (string)$data->xMotivo;

        if ($cStat != '103' && $cStat != '104') {
            throw new Exception("SEFAZ Rejeitou: [$cStat] $xMotivo");
        }

        return [
            'status' => 'autorizada',
            'protocolo' => (string)$data->infRec->nRec ?? 'N/A',
            'chave' => (string)$data->protNFe->infProt->chNFe ?? 'N/A',
            'xml_path' => 'storage/nfe/xml/' . date('Y-m') . '/nfe_' . time() . '.xml'
        ];
    }

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

        try {
            $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $branch['certificado_pfx'];
            if (!file_exists($pfxPath)) throw new Exception("Arquivo do certificado não encontrado no servidor.");
            
            $pfxContent = file_get_contents($pfxPath);
            $certs = [];
            $password = base64_decode($branch['certificado_senha']);
            
            if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
                throw new Exception("Falha ao ler o certificado digital. Verifique a senha.");
            }

            $dataToSign = "test-signature-" . time();
            $signature = '';
            if (!openssl_sign($dataToSign, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256)) {
                throw new Exception("O certificado foi aberto, mas falhou ao assinar dados (Chave privada inválida?).");
            }

            $soapClient = new SefazSoapClient();
            $fiscal = $this->getFiscalConfig($branchId);
            
            $uf = "35";
            if (isset($branch['uf'])) {
                $estados = ['RO'=>'11','AC'=>'12','AM'=>'13','RR'=>'14','PA'=>'15','AP'=>'16','TO'=>'17','MA'=>'21','PI'=>'22','CE'=>'23','RN'=>'24','PB'=>'25','PE'=>'26','AL'=>'27','SE'=>'28','BA'=>'29','MG'=>'31','ES'=>'32','RJ'=>'33','SP'=>'35','PR'=>'41','SC'=>'42','RS'=>'43','MS'=>'50','MT'=>'51','GO'=>'52','DF'=>'53'];
                $uf = $estados[$branch['uf']] ?? '35';
            }

            // Exato XML NfePHP-org consStatServ: não usa namespaces redundantes se não necessário e envelopa limpo
            $xml = '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>' . $fiscal['ambiente'] . '</tpAmb><cUF>' . $uf . '</cUF><xServ>STATUS</xServ></consStatServ>';
            
            try {
                $responseXml = $soapClient->call('sefaz_status', $xml, $fiscal);
                
                $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseXml);
                $res = @simplexml_load_string($cleanXml);
                
                if (!$res) {
                     throw new Exception("Falha ao ler XML de resposta da SEFAZ. RAW XML:\n\n" . htmlspecialchars(substr($responseXml, 0, 1000)));
                }

                $nodes = $res->xpath('//retConsStatServ');
                if (empty($nodes)) {
                    $nodes = $res->xpath('//*[local-name()="retConsStatServ"]');
                }
                
                $retStatus = !empty($nodes) ? $nodes[0] : null;

                if (!$retStatus) {
                     throw new Exception("Estrutura retConsStatServ não encontrada.\n\nRAW XML (DIAGNÓSTICO V5):\n" . htmlspecialchars(substr($responseXml, 0, 1500)));
                }

                return [
                    'success' => true,
                    'status' => (string)$retStatus->cStat ?: '???',
                    'motivo' => (string)$retStatus->xMotivo ?: 'Resposta sem motivo',
                    'ambiente' => ($fiscal['ambiente'] == 1) ? 'Produção' : 'Homologação',
                    'verAplic' => (string)$retStatus->verAplic ?: '---',
                    'timestamp' => (string)$retStatus->dhRecbto ?: date('d/m/Y H:i:s'),
                    'cert_info' => [
                        'subject' => isset($certs['cert']) && openssl_x509_parse($certs['cert']) ? (openssl_x509_parse($certs['cert'])['subject']['CN'] ?? 'Desconhecido') : 'Desconhecido',
                        'validTo' => isset($certs['cert']) && openssl_x509_parse($certs['cert']) ? date('d/m/Y', openssl_x509_parse($certs['cert'])['validTo_time_t'] ?? time()) : '---'
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
