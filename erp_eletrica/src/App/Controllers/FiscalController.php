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
        $stmt = $this->db->query("
            SELECT nf.*, v.valor_total, c.nome as cliente_nome 
            FROM notas_fiscais nf
            JOIN vendas v ON nf.venda_id = v.id
            LEFT JOIN clientes c ON v.cliente_id = c.id
            ORDER BY nf.created_at DESC
        ");
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
        $stmt = $this->db->query("SELECT * FROM filiais ORDER BY principal DESC");
        $branches = $stmt->fetchAll();

        $this->render('fiscal/settings', [
            'branches' => $branches,
            'title' => 'Configurações SEFAZ',
            'pageTitle' => 'Central de Conectividade Fiscal'
        ]);
    }

    public function test_connection() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit(json_encode(['success' => false, 'error' => 'ID da filial não fornecido']));

        try {
            $service = new \App\Services\FiscalService();
            $result = $service->testConnection($id);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
