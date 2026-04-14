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
        
        // Fallback para a Matriz (comportamento real do backend NfceService)
        if (empty($globalConfig['certificado_path'])) {
            $stmtMatriz = $this->db->query("SELECT certificado_pfx, ambiente, csc_id, csc_token as csc FROM filiais WHERE principal = 1 LIMIT 1");
            $matriz = $stmtMatriz->fetch();
            if ($matriz && !empty($matriz['certificado_pfx'])) {
                if (!$globalConfig) $globalConfig = [];
                $globalConfig['certificado_path'] = $matriz['certificado_pfx'];
                $globalConfig['ambiente'] = $matriz['ambiente'] == 1 ? 'producao' : 'homologacao';
                $globalConfig['csc'] = $matriz['csc'];
                $globalConfig['csc_id'] = $matriz['csc_id'];
            }
        }

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
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            @file_put_contents(dirname(__DIR__, 3) . '/storage/last_connection_test_error.txt', "Type: " . get_class($e) . "\nMessage: " . $msg . "\nLine: " . $e->getLine() . "\n" . $e->getTraceAsString());
            $ob = ob_get_clean();
            header('Content-Type: application/json');
            
            // Format for UI
            $safeMsg = "CRASH: {$msg} (Linha {$e->getLine()})";
            if (!empty(trim($ob))) {
                $safeMsg .= " | Output Sujo: " . substr(strip_tags($ob), 0, 100);
            }
            
            echo json_encode(['success' => false, 'error' => $safeMsg]);
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

        // Fallback para consistência da UI com o Service
        if (empty($globalConfig['certificado_path'])) {
            $stmtMatriz = $this->db->query("SELECT certificado_pfx FROM filiais WHERE principal = 1 LIMIT 1");
            $matriz = $stmtMatriz->fetch();
            if ($matriz && !empty($matriz['certificado_pfx'])) {
                if (!$globalConfig) $globalConfig = [];
                $globalConfig['certificado_path'] = $matriz['certificado_pfx'];
            }
        }

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

        // --- Açaidinhos Status Logic Equivalent ---
        $fiscalService = new \App\Services\FiscalService();
        $fiscalConfig = $fiscalService->getFiscalConfig($branchId);

        $tpAmb = $fiscalConfig['ambiente'];
        if ($tpAmb == 1) {
            $ambienteStatus = 'Produção';
            $ambienteClass  = 'bg-label-primary';
        } elseif ($tpAmb == 2) {
            $ambienteStatus = 'Homologação';
            $ambienteClass  = 'bg-label-info';
        } else {
            $ambienteStatus = 'Não configurado';
            $ambienteClass  = 'bg-label-secondary';
        }

        $certificadoStatus = 'Não informado';
        $certificadoClass  = 'bg-label-secondary';
        $pfxPathDisplay = null;

        if (!empty($fiscalConfig['certificado_pfx'])) {
            $pfxAbs = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscalConfig['certificado_pfx'];
            $pfxPathDisplay = "storage/certificados/" . $fiscalConfig['certificado_pfx'];
            if (is_file($pfxAbs)) {
                if (!empty($fiscalConfig['certificado_senha'])) {
                    require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                    try {
                        $certContent = file_get_contents($pfxAbs);
                        $cert = \NFePHP\Common\Certificate::readPfx($certContent, $fiscalConfig['certificado_senha']);
                        $certificadoStatus = 'Válido';
                        $certificadoClass  = 'bg-label-success';
                    } catch (\Exception $e) {
                        $certificadoStatus = 'Arquivo encontrado (senha inválida)';
                        $certificadoClass  = 'bg-label-danger';
                    }
                } else {
                    $certificadoStatus = 'Arquivo encontrado (sem senha)';
                    $certificadoClass  = 'bg-label-warning';
                }
            } else {
                $certificadoStatus = 'Arquivo não encontrado';
                $certificadoClass  = 'bg-label-danger';
            }
        }

        $isConfigurado = (
            in_array($tpAmb, [1, 2], true) &&
            !empty($fiscalConfig['cnpj']) &&
            !empty($branchInfo['csc_id']) &&
            !empty($branchInfo['csc_token']) &&
            $certificadoStatus === 'Válido'
        );

        $fonte = ($globalConfig && !empty($globalConfig['certificado_path'])) ? 'Configuração Global' : 'Filial Database';

        $this->render('fiscal/diagnostic', [
            'title' => 'Diagnóstico & Status SEFAZ',
            'pageTitle' => 'Status da Integração',
            'env' => $env,
            'storage' => $storage,
            'dbStatus' => $dbStatus,
            'branch' => $branchInfo,
            'branches' => $branches,
            'globalConfig' => $globalConfig,
            'selectedBranchId' => $branchId,
            'isConfigurado' => $isConfigurado,
            'certificadoClass' => $certificadoClass,
            'certificadoStatus' => $certificadoStatus,
            'pfxPathDisplay' => $pfxPathDisplay,
            'ambienteClass' => $ambienteClass,
            'ambienteStatus' => $ambienteStatus,
            'cnpjExibe' => $fiscalConfig['cnpj'],
            'razao' => $branchInfo['nome'] ?? '',
            'csc' => $branchInfo['csc_token'] ?? '',
            'idTokenExibe' => str_pad($branchInfo['csc_id'] ?? '', 6, '0', STR_PAD_LEFT),
            'fonte' => $fonte
        ]);
    }
}
