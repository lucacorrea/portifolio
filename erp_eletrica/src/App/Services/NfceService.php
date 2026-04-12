<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use NFePHP\Common\Certificate;

class NfceService extends BaseService {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Gets NFC-e configuration for a specific filial, with global fallback.
     */
    public function getConfig($filialId) {
        // 1. Individual branch data
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$filialId]);
        $filial = $stmt->fetch();

        // 2. Global Sefaz Config (Digital Certificate & CSC)
        $stmtGlobal = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $global = $stmtGlobal->fetch();

        // 3. Matriz Info (for CNPJ, Name, and address fallback)
        $stmtMatriz = $this->db->query("SELECT * FROM filiais WHERE principal = 1 LIMIT 1");
        $matriz = $stmtMatriz->fetch();

        // Define which fields MUST be global (Centralized Fiscal)
        $globalFields = [
            'certificado_path', 'certificado_senha', 'ambiente', 
            'csc', 'csc_id', 'cnpj', 'razao_social', 'inscricao_estadual'
        ];

        $fields = [
            'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf',
            'codigo_uf', 'codigo_municipio', 'telefone', 'certificado_path', 'certificado_senha',
            'ambiente', 'regime_tributario', 'serie_nfce', 'ultimo_numero_nfce', 'csc', 'csc_id',
            'tipo_emissao', 'finalidade', 'ind_pres', 'tipo_impressao'
        ];

        $config = [];
        foreach ($fields as $field) {
            $filialKey = $field;
            if ($field === 'certificado_path') $filialKey = 'certificado_pfx';
            if ($field === 'csc') $filialKey = 'csc_token';
            if ($field === 'nome_fantasia') $filialKey = 'nome';
            
            // Hard Override for Global Fields: Only Global or Matriz, NEVER Filial
            if (in_array($field, $globalFields)) {
                // Priority: sefaz_config (Global Card) -> Matriz Record (Corporate Identity)
                $val = ($global[$field] ?? null);
                if (empty($val)) {
                    $val = ($matriz[$filialKey] ?? null);
                }
            } else {
                // Shared fields: Filial has priority (Address/Phones), fallback to Matriz
                $val = (!empty($filial[$filialKey])) ? $filial[$filialKey] : ($matriz[$filialKey] ?? null);
            }

            $config[$field] = $val;
        }

        return $config;
    }

    /**
     * Saves configuration to either global (sefaz_config) or specific branch (filiais)
     */
    public function saveConfig($data, $isGlobal = false) {
        $table = $isGlobal ? 'sefaz_config' : 'filiais';
        $where = $isGlobal ? "WHERE id = (SELECT id FROM sefaz_config LIMIT 1)" : "WHERE id = :id";
        
        // Ensure sefaz_config has at least one record if global
        if ($isGlobal) {
            $check = $this->db->query("SELECT id FROM sefaz_config LIMIT 1")->fetch();
            if (!$check) {
                $this->db->query("INSERT INTO sefaz_config (ambiente) VALUES ('homologacao')");
            }
        }

        $fieldsToUpdate = [];
        $params = [];

        foreach ($data as $key => $value) {
            if ($key === 'id' && !$isGlobal) {
                $params[':id'] = $value;
                continue;
            }
            if ($key === 'certificado_senha' && empty($value)) continue; // Don't overwrite if empty
            
            $fieldsToUpdate[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }

        if (empty($fieldsToUpdate)) return true;

        $sql = "UPDATE $table SET " . implode(', ', $fieldsToUpdate) . " $where";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Ports the simulation/emission logic from acainhadinhos
     */
    public function processNfce($vendaId, $empresaId) {
        try {
            // 1. Load Fiscal Config
            $fiscal = $this->getConfig($empresaId);
            
            // 2. Load Sale Data
            $stV = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
            $stV->execute([$vendaId]);
            $venda = $stV->fetch();
            if (!$venda) throw new Exception("Venda não encontrada: $vendaId");

            $stI = $this->db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
            $stI->execute([$vendaId]);
            $itens = $stI->fetchAll();
            if (empty($itens)) throw new Exception("Venda sem itens: $vendaId");

            // 3. Setup NFePHP Tools
            $config = [
                'atualizacao' => date('Y-m-d H:i:s'),
                'tpAmb'       => (int)($fiscal['ambiente'] ?? 2),
                'razaosocial' => $fiscal['razao_social'] ?? '',
                'siglaUF'     => $fiscal['uf'] ?? 'RN',
                'cnpj'        => preg_replace('/\D/', '', $fiscal['cnpj'] ?? ''),
                'schemes'     => 'PL_009_V4',
                'versao'      => '4.00',
                'urlChave'    => '',
                'urlQRCode'   => '',
                'CSC'         => $fiscal['csc'] ?? '',
                'CSCid'       => $fiscal['csc_id'] ?? '',
            ];
            $configJson = json_encode($config);

            $pfxPath = dirname(__DIR__, 3) . '/storage/certificados/' . ($fiscal['certificado_path'] ?? $fiscal['certificado_pfx']);
            if (!file_exists($pfxPath)) throw new Exception("Certificado não encontrado em: $pfxPath");
            
            $pfx = file_get_contents($pfxPath);
            $cert = Certificate::readPfx($pfx, $fiscal['certificado_senha']);
            
            $tools = new \NFePHP\NFe\Tools($configJson, $cert);
            $tools->model('65');

            // 4. Generate XML using SefazXmlService
            $xmlService = new SefazXmlService();
            // Map items for SefazXmlService
            $mappedItems = [];
            foreach ($itens as $it) {
                $mappedItems[] = [
                    'produto_id' => $it['produto_id'],
                    'nome' => $it['produto_nome'],
                    'quantidade' => $it['quantidade'],
                    'preco_unitario' => $it['preco_unitario'],
                    'unidade' => $it['unidade'] ?? 'UN',
                    'ncm' => $it['ncm'] ?? '21069090',
                    'cfop_interno' => $it['cfop'] ?? '5102',
                    'origem' => $it['origem'] ?? '0'
                ];
            }
            
            $saleData = [
                'id'                  => $vendaId,
                'items'               => $mappedItems,
                'valor_total'         => $venda['valor_total'],
                'desconto_total'      => $venda['desconto_total'] ?? 0,
                'forma_pagamento'     => $venda['forma_pagamento'],
                'cliente_id'          => $venda['cliente_id'],
                'nome_cliente_avulso' => $venda['nome_cliente_avulso'],
                'cpf_cnpj'            => null,
                'cliente_nome'        => null
            ];

            // Fetch customer data if exists
            if (!empty($venda['cliente_id'])) {
                $stC = $this->db->prepare("SELECT nome, cpf_cnpj FROM clientes WHERE id = ?");
                $stC->execute([$venda['cliente_id']]);
                $cl = $stC->fetch();
                if ($cl) {
                    $saleData['cliente_nome'] = $cl['nome'];
                    $saleData['cpf_cnpj']     = $cl['cpf_cnpj'];
                }
            } elseif (!empty($venda['nome_cliente_avulso'])) {
                $saleData['cliente_nome'] = $venda['nome_cliente_avulso'];
            }

            // Real XML generation
            $resXml = $xmlService->generateNFCe($saleData, $fiscal);
            $nfeAss = $tools->signNFe($resXml['xml']);
            
            // 5. Send to SEFAZ
            $respEnv = $tools->sefazEnviaLote([$nfeAss], mt_rand(1, 999999), 1);
            $stEnv = new \NFePHP\NFe\Common\Standardize();
            $stdEnv = $stEnv->toStd($respEnv);

            if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 104) {
                 if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $respEnv, $mProt)) {
                    $proc = '<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">' . preg_replace('/<\?xml.*?\?>/','', $nfeAss) . $mProt[1] . '</nfeProc>';
                    
                    // Save to nfce_emitidas
                    $this->saveEmission($vendaId, $empresaId, $fiscal, $resXml['chave'], $stdEnv, $proc, $nfeAss, $respEnv, $venda);
                    
                    return ['success' => true, 'chave' => $resXml['chave'], 'protocolo' => $stdEnv->protNFe->infProt->nProt ?? null];
                 }
            }

            throw new Exception("Falha na autorização: " . ($stdEnv->xMotivo ?? 'Erro desconhecido'));

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function testConnection($empresaId) {
        try {
            $fiscal = $this->getConfig($empresaId);
            
            $config = [
                'atualizacao' => date('Y-m-d H:i:s'),
                'tpAmb'       => (int)($fiscal['ambiente'] ?? 2),
                'razaosocial' => $fiscal['razao_social'] ?? '',
                'siglaUF'     => $fiscal['uf'] ?? 'RN',
                'cnpj'        => preg_replace('/\D/', '', $fiscal['cnpj'] ?? ''),
                'schemes'     => 'PL_009_V4',
                'versao'      => '4.00',
                'urlChave'    => '',
                'urlQRCode'   => '',
                'CSC'         => $fiscal['csc'] ?? '',
                'CSCid'       => $fiscal['csc_id'] ?? '',
            ];
            $configJson = json_encode($config);

            $pfxPath = dirname(__DIR__, 3) . '/storage/certificados/' . ($fiscal['certificado_path'] ?? $fiscal['certificado_pfx']);
            if (!file_exists($pfxPath)) throw new Exception("Certificado não encontrado em: $pfxPath");
            
            $pfx = file_get_contents($pfxPath);
            $cert = Certificate::readPfx($pfx, $fiscal['certificado_senha']);
            
            $tools = new \NFePHP\NFe\Tools($configJson, $cert);
            $tools->model('65');

            $xml = $tools->sefazStatus();
            $std = new \NFePHP\NFe\Common\Standardize();
            $res = $std->toStd($xml);

            return [
                'success' => true,
                'status' => $res->cStat,
                'motivo' => $res->xMotivo,
                'ambiente' => ($fiscal['ambiente'] == 1) ? 'Produção' : 'Homologação',
                'verAplic' => $res->verAplic ?? '---',
                'timestamp' => $res->dhRecbto ?? date('d/m/Y H:i:s')
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function saveEmission($vendaId, $empresaId, $fiscal, $chave, $std, $xmlProc, $xmlEnv, $xmlRet, $venda) {
        $st = $this->db->prepare("
            INSERT INTO nfce_emitidas
              (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem,
               xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco)
            VALUES
              (:emp, :venda, :amb, :serie, :num, :chave, :prot, :stat, :msg, :proc, :env, :ret, :total, :troco)
            ON DUPLICATE KEY UPDATE 
              protocolo = VALUES(protocolo), status_sefaz = VALUES(status_sefaz), mensagem = VALUES(mensagem),
              xml_nfeproc = VALUES(xml_nfeproc), created_at = NOW()
        ");
        
        $st->execute([
            ':emp' => $empresaId,
            ':venda' => $vendaId,
            ':amb' => $fiscal['ambiente'] ?? 2,
            ':serie' => $fiscal['serie_nfce'] ?? 1,
            ':num' => $vendaId, // Simplification: using vendaId as number for now
            ':chave' => $chave,
            ':prot' => $std->protNFe->infProt->nProt ?? null,
            ':stat' => $std->cStat,
            ':msg' => $std->xMotivo,
            ':proc' => $xmlProc,
            ':env' => $xmlEnv,
            ':ret' => $xmlRet,
            ':total' => $venda['valor_total'],
            ':troco' => $venda['troco'] ?? 0
        ]);
        
        // Update sales table
        $up = $this->db->prepare("UPDATE vendas SET chave_nfce = ?, status_nfce = 'autorizada' WHERE id = ?");
        $up->execute([$chave, $vendaId]);
    }
}
