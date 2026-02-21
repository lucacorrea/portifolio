<?php
namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\StockMovement;

class PurchaseController extends BaseController {
    public function index() {
        $purchaseModel = new Purchase();
        $supplierModel = new Supplier();
        $productModel = new Product();
        
        $purchases = $purchaseModel->getRecent();
        $suppliers = $supplierModel->all();
        $products = $productModel->all();

        ob_start();
        $data = ['purchases' => $purchases, 'suppliers' => $suppliers, 'products' => $products];
        extract($data);
        require __DIR__ . "/../../../views/purchases.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Entrada de Mercadorias',
            'pageTitle' => 'Gestão de Compras e Reposição',
            'content' => $content
        ]);
    }

    public function process() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $db = \App\Config\Database::getInstance()->getConnection();
            $productModel = new Product();
            $moveModel = new StockMovement();

            try {
                $db->beginTransaction();
                
                $purchaseId = $db->prepare("INSERT INTO compras (fornecedor_id, usuario_id, valor_total) VALUES (?, ?, ?)");
                $purchaseId->execute([$data['fornecedor_id'], $_SESSION['usuario_id'], $data['total']]);
                $cId = $db->lastInsertId();

                foreach ($data['items'] as $item) {
                    $db->prepare("INSERT INTO compra_itens (compra_id, produto_id, quantidade, preco_custo) VALUES (?, ?, ?, ?)")
                       ->execute([$cId, $item['id'], $item['qty'], $item['cost']]);

                    // Atualiza estoque e preco de custo
                    $db->prepare("UPDATE produtos SET quantidade = quantidade + ?, preco_custo = ? WHERE id = ?")
                       ->execute([$item['qty'], $item['cost'], $item['id']]);

                    $moveModel->record($item['id'], 1, $item['qty'], 'entrada', "Compra #$cId", $_SESSION['usuario_id']);
                }

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
