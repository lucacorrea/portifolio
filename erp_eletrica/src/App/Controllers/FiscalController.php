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
        // ... code remains same ...
    }

    public function emitir_nfce() {
        $vendaId = $_GET['venda_id'] ?? null;
        $empresaId = $_SESSION['filial_id'] ?? ($_GET['empresa_id'] ?? null);

        if (!$vendaId || !$empresaId) {
            $this->redirect('vendas.php?error=' . urlencode('Venda ou Empresa não identificada.'));
            return;
        }

        $service = new \App\Services\NfceService();
        $res = $service->processNfce($vendaId, $empresaId);

        if ($res['success']) {
            $this->redirect('danfe.php?chave=' . $res['chave']);
        } else {
            $this->redirect('vendas.php?error=' . urlencode($res['error']));
        }
    }

    public function adicionar_nfce() {
        $id = $_GET['id'] ?? null;
        $isGlobal = isset($_GET['global']);

        $service = new \App\Services\NfceService();
        $config = [];
        
        if ($id) {
            $config = $service->getConfig($id);
        } elseif ($isGlobal) {
            $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
            $config = $stmt->fetch() ?: [];
        }

        $this->render('fiscal/adicionar_nfce', [
            'config' => $config,
            'id' => $id,
            'isGlobal' => $isGlobal,
            'title' => 'Configuração Completa NFC-e',
            'pageTitle' => $isGlobal ? 'Configuração Global NFC-e' : 'Configuração de Filial NFC-e'
        ]);
    }

    public function salvar_nfce_config() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('fiscal.php');
            return;
        }

        $id = $_POST['id'] ?? null;
        $isGlobal = isset($_POST['global']) && $_POST['global'] == '1';
        
        $data = $_POST;
        unset($data['global']);
        
        // Handle Certificate Upload
        if (isset($_FILES['certificado_digital']) && $_FILES['certificado_digital']['error'] === UPLOAD_ERR_OK) {
            $dir = dirname(__DIR__, 3) . '/storage/certificados/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = 'cert_' . ($isGlobal ? 'global' : $id) . '_' . time() . '.pfx';
            if (move_uploaded_file($_FILES['certificado_digital']['tmp_name'], $dir . $filename)) {
                $data['certificado_path'] = $filename;
            }
        }
        unset($data['certificado_digital']);

        // Base64 encode password for consistency with existing erp_eletrica logic
        if (!empty($data['senha_certificado'])) {
            $data['certificado_senha'] = base64_encode($data['senha_certificado']);
            unset($data['senha_certificado']);
        }

        $service = new \App\Services\NfceService();
        try {
            $service->saveConfig($data, $isGlobal);
            $msg = "Configuração " . ($isGlobal ? "Global" : "da Filial") . " salva com sucesso!";
            $this->redirect('fiscal/settings?msg=' . urlencode($msg));
        } catch (\Exception $e) {
            $this->redirect('fiscal/settings?error=' . urlencode($e->getMessage()));
        }
    }

    public function danfe_nfce() {
        $chave = $_GET['chave'] ?? null;
        if (!$chave) die("Chave da Nota Fiscal não fornecida.");

        $stmt = $this->db->prepare("SELECT * FROM nfce_emitidas WHERE chave = ? LIMIT 1");
        $stmt->execute([$chave]);
        $nf = $stmt->fetch();

        if (!$nf) {
            // Tentativa fallback na tabela antiga se existir
            $stmt = $this->db->prepare("SELECT * FROM notas_fiscais WHERE chave_acesso = ? LIMIT 1");
            $stmt->execute([$chave]);
            $nf = $stmt->fetch();
            $xmlContent = $nf ? ($nf['xml_conteudo'] ?? null) : null;
        } else {
            $xmlContent = $nf['xml_nfeproc'];
        }

        if (!$xmlContent) die("XML da Nota Fiscal não encontrado.");

        $this->render('fiscal/danfe_nfce', [
            'xml' => $xmlContent,
            'title' => 'DANFE NFC-e - ' . $chave
        ], false); 
    }

    private function generateMockXmlForDanfe($nf) {
        return "<?xml version='1.0' encoding='UTF-8'?><nfeProc xmlns='http://www.portalfiscal.inf.br/nfe' versao='4.00'><NFe><infNFe Id='NFe" . $nf['chave_acesso'] . "' versao='4.00'><ide><cUF>35</cUF><nNF>123</nNF><serie>1</serie><dhEmi>" . $nf['created_at'] . "</dhEmi><tpAmb>2</tpAmb></ide><emit><CNPJ>00000000000100</CNPJ><xNome>EMPRESA TESTE</xNome><enderEmit><xLgr>RUA TESTE</xLgr><nro>100</nro><xBairro>CENTRO</xBairro><cMun>3550308</cMun><xMun>SAO PAULO</xMun><UF>SP</UF></enderEmit><IE>123456789</IE></emit><det nItem='1'><prod><cProd>1</cProd><xProd>PRODUTO TESTE</xProd><qCom>1.000</qCom><uCom>UN</uCom><vUnCom>100.00</vUnCom><vProd>100.00</vProd></prod></det><total><ICMSTot><vNF>100.00</vNF></ICMSTot></total></infNFe></NFe><infProt><nProt>" . $nf['protocolo'] . "</nProt></infProt></nfeProc>";
    }
}
