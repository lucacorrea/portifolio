<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class FiscalController extends BaseController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index() {
        $sql = "
            SELECT nf.*, v.valor_total, c.nome as cliente_nome 
            FROM notas_fiscais nf
            JOIN vendas v ON nf.venda_id = v.id
            LEFT JOIN clientes c ON v.cliente_id = c.id
        ";
        
        $params = [];
        if (!($_SESSION['is_matriz'] ?? false)) {
            $sql .= " WHERE v.filial_id = ?";
            $params[] = $_SESSION['filial_id'];
        }
        
        $sql .= " ORDER BY nf.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $notes = $stmt->fetchAll();

        $this->render('fiscal/history', [
            'notes' => $notes,
            'title' => 'Gestão Fiscal & XMLs',
            'pageTitle' => 'Notas Fiscais (NF-e / NFC-e)'
        ]);
    }

    public function download_xml() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit('ID não fornecido');

        $stmt = $this->db->prepare("SELECT * FROM notas_fiscais WHERE id = ?");
        $stmt->execute([$id]);
        $nf = $stmt->fetch();

        if ($nf) {
            header('Content-Type: text/xml');
            header('Content-Disposition: attachment; filename="nf_' . $nf['chave_acesso'] . '.xml"');
            // In a real scenario, we would read the file from xml_path
            echo "<?xml version='1.0' encoding='UTF-8'?><!-- XML Simulado para Chave: " . $nf['chave_acesso'] . " --><NFe xmlns='http://www.portalfiscal.inf.br/nfe'><infNFe versao='4.00' Id='NFe" . $nf['chave_acesso'] . "'></infNFe></NFe>";
        }
        exit;
    }

    public function settings() {
        $sql = "SELECT * FROM filiais";
        $params = [];
        
        if (!($_SESSION['is_matriz'] ?? false)) {
            $sql .= " WHERE id = ?";
            $params[] = $_SESSION['filial_id'];
        }
        
        $sql .= " ORDER BY principal DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $branches = $stmt->fetchAll();

        // Get Global Config
        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $globalConfig = $stmt->fetch();

        $this->render('fiscal/settings', [
            'branches' => $branches,
            'globalConfig' => $globalConfig,
            'title' => 'Configurações SEFAZ',
            'pageTitle' => 'Central de Conectividade Fiscal'
        ]);
    }

    public function test_connection() {
        ob_start();
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            ob_get_clean();
            exit(json_encode(['success' => false, 'error' => 'ID da filial não fornecido']));
        }

        // Security check
        if (!($_SESSION['is_matriz'] ?? false) && $id != $_SESSION['filial_id']) {
            ob_get_clean();
            exit(json_encode(['success' => false, 'error' => 'Acesso negado para esta unidade']));
        }

        try {
            $service = new \App\Services\FiscalService();
            $result = $service->testConnection($id);
            ob_get_clean(); // Discard any warnings/garbage
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            @file_put_contents(dirname(__DIR__, 3) . '/storage/last_connection_test_error.txt', $msg . "\n" . $e->getTraceAsString());
            ob_get_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
        }
        exit;
    }

    public function diagnostic() {
        $branchId = $_GET['id'] ?? ($_SESSION['is_matriz'] ? null : $_SESSION['filial_id']);
        if (!$branchId && !$_SESSION['is_matriz']) {
            die("Acesso negado.");
        }

        // Fetch Branch limits if not matriz
        $branches = [];
        if ($_SESSION['is_matriz']) {
            $stmt = $this->db->query("SELECT id, nome, cnpj, inscricao_estadual, certificado_pfx FROM filiais");
            $branches = $stmt->fetchAll();
            if (!$branchId && count($branches) > 0) $branchId = $branches[0]['id'];
        } else {
            $stmt = $this->db->prepare("SELECT id, nome, cnpj, inscricao_estadual, certificado_pfx FROM filiais WHERE id = ?");
            $stmt->execute([$branchId]);
            $branches = $stmt->fetchAll();
        }

        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$branchId]);
        $branchInfo = $stmt->fetch();

        // Fetch Global Cert Config
        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $globalConfig = $stmt->fetch();

        // 1. Environment Tests
        $env = [
            'php_version' => phpversion(),
            'curl_loaded' => extension_loaded('curl'),
            'openssl_loaded' => extension_loaded('openssl'),
            'soap_loaded' => extension_loaded('soap'),
            'dom_loaded' => extension_loaded('dom'),
            'simplexml_loaded' => extension_loaded('simplexml')
        ];

        // 2. Storage Tests
        $storageDir = dirname(__DIR__, 3) . '/storage';
        $certDir = $storageDir . '/certificados';
        $storage = [
            'storage_exists' => is_dir($storageDir),
            'storage_writable' => is_writable($storageDir),
            'cert_dir_exists' => is_dir($certDir),
            'cert_dir_writable' => is_writable($certDir)
        ];

        // 3. Database Validation for Branch
        $dbStatus = [
            'has_cnpj' => !empty($branchInfo['cnpj']),
            'valid_cnpj' => preg_match('/^\d{14}$/', preg_replace('/\D/', '', $branchInfo['cnpj'])),
            'has_ie' => !empty($branchInfo['inscricao_estadual']),
            'has_cep' => !empty($branchInfo['cep']) && strlen(preg_replace('/\D/', '', $branchInfo['cep'])) === 8,
            'has_uf' => !empty($branchInfo['uf']) && strlen($branchInfo['uf']) === 2,
            'has_ibge' => !empty($branchInfo['codigo_municipio']) && strlen($branchInfo['codigo_municipio']) === 7
        ];

        $this->render('fiscal/diagnostic', [
            'title' => 'Diagnóstico Profundo SEFAZ',
            'pageTitle' => 'Diagnóstico do Ambiente Fiscal',
            'env' => $env,
            'storage' => $storage,
            'dbStatus' => $dbStatus,
            'branch' => $branchInfo,
            'branches' => $branches,
            'globalConfig' => $globalConfig,
            'selectedBranchId' => $branchId
        ]);
    }
}
