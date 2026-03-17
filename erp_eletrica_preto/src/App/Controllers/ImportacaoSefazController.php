<?php
namespace App\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogService;

class ImportacaoSefazController extends BaseController {
    public function index() {
        $this->render('importacao_xml', [
            'title' => 'Importar NF-e (XML)',
            'pageTitle' => 'Entrada de Estoque via XML SEFAZ'
        ]);
    }

    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
            $file = $_FILES['xml_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo.']);
                exit;
            }

            $xml = simplexml_load_file($file['tmp_name']);
            if (!$xml) {
                echo json_encode(['success' => false, 'error' => 'Arquivo XML inválido.']);
                exit;
            }

            // Registrar namespaces para XPath
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            
            $produtos = [];
            $detalhes = $xml->xpath('//nfe:det');

            foreach ($detalhes as $det) {
                $prod = $det->prod;
                $imposto = $det->imposto;
                
                $produtos[] = [
                    'codigo' => (string)$prod->cProd,
                    'nome' => (string)$prod->xProd,
                    'ncm' => (string)$prod->NCM,
                    'cfop' => (string)$prod->CFOP,
                    'uCom' => (string)$prod->uCom,
                    'qCom' => (float)$prod->qCom,
                    'vUnCom' => (float)$prod->vUnCom,
                    'vProd' => (float)$prod->vProd
                ];
            }

            echo json_encode(['success' => true, 'produtos' => $produtos]);
            exit;
        }
    }

    public function confirmar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $items = $data['items'] ?? [];
            
            if (empty($items)) {
                echo json_encode(['success' => false, 'error' => 'Nenhum item para importar.']);
                exit;
            }

            $productModel = new Product();
            $stockModel = new StockMovement();
            $audit = new AuditLogService();
            $db = \App\Config\Database::getInstance()->getConnection();

            try {
                $db->beginTransaction();

                foreach ($items as $item) {
                    // Tentar encontrar produto pelo código do fornecedor ou nome (simplificação)
                    $stmt = $db->prepare("SELECT id FROM produtos WHERE nome = ? OR codigo = ?");
                    $stmt->execute([$item['nome'], $item['codigo']]);
                    $p = $stmt->fetch();

                    if ($p) {
                        $productId = $p['id'];
                        $productModel->updateStock($productId, $item['qCom'], 'entrada');
                    } else {
                        // Cadastro rápido
                        $productId = $productModel->create([
                            'nome' => $item['nome'],
                            'codigo' => $item['codigo'],
                            'ncm' => $item['ncm'],
                            'unidade' => $item['uCom'],
                            'preco_venda' => $item['vUnCom'] * 1.5, // Margem padrão 50%
                            'estoque_atual' => $item['qCom'],
                            'filial_id' => $_SESSION['filial_id'] ?? 1
                        ]);
                    }

                    $stockModel->create([
                        'produto_id' => $productId,
                        'quantidade' => $item['qCom'],
                        'tipo' => 'entrada',
                        'motivo' => 'Importação NF-e XML',
                        'usuario_id' => $_SESSION['usuario_id'],
                        'filial_id' => $_SESSION['filial_id'] ?? 1
                    ]);
                }

                $audit->record('Importação XML SEFAZ', 'produtos', null, null, json_encode($items));
                
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
