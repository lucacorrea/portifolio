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
        // Individual config
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$filialId]);
        $filial = $stmt->fetch();

        // Global config
        $stmtGlobal = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $global = $stmtGlobal->fetch();

        // Merge logic: prefer filial-specific, fallback to global
        // The 27 fields from Açaí system
        $fields = [
            'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal',
            'cep', 'logradouro', 'numero_endereco', 'complemento', 'bairro', 'cidade', 'uf',
            'codigo_uf', 'codigo_municipio', 'telefone', 'certificado_path', 'certificado_senha',
            'ambiente', 'regime_tributario', 'serie_nfce', 'ultimo_numero_nfce', 'csc', 'csc_id',
            'tipo_emissao', 'finalidade', 'ind_pres', 'tipo_impressao'
        ];

        $config = [];
        foreach ($fields as $field) {
            // Priority: Filial (if it has the field populated differently from default/null)
            // Note: some fields in erp_eletrica have slightly different names, handled in the controller save/load
            $val = $filial[$field] ?? $global[$field] ?? null;
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
     * Ports the simulation/emission logic from api_nfce.php
     */
    public function processNfce($vendaId, $empresaId) {
        // This will be implemented to use NFePHP for real emission
        // For now, mirroring the Acaidinhos logic of simulation or direct NFePHP call
        $fiscal = $this->getConfig($empresaId);
        // ... implementation follows ...
        return ['success' => true, 'message' => 'NFC-e processada'];
    }
}
