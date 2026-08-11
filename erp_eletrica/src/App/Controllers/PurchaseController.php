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

        $this->render('purchases', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $products,
            'title' => 'Entrada de Mercadorias',
            'pageTitle' => 'Gestao de Compras e Reposicao'
        ]);
    }

    public function process() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $db = \App\Config\Database::getInstance()->getConnection();
            $productModel = new Product();
            $moveModel = new StockMovement();

            try {
                $db->beginTransaction();

                $items = [];
                $totalCompra = 0.0;
                foreach (($data['items'] ?? []) as $item) {
                    $produtoId = (int)($item['id'] ?? 0);
                    $quantidade = (float)($item['qty'] ?? 0);
                    $custo = (float)($item['cost'] ?? 0);

                    if ($produtoId <= 0 || $quantidade <= 0 || $custo < 0) {
                        continue;
                    }

                    $items[] = [
                        'id' => $produtoId,
                        'qty' => $quantidade,
                        'cost' => $custo,
                    ];
                    $totalCompra += round($quantidade * $custo, 2);
                }

                if (empty($items)) {
                    throw new \Exception("Informe ao menos um item valido para a compra.");
                }

                $filialId = (int)($_SESSION['filial_id'] ?? 1);

                $purchaseId = $db->prepare("INSERT INTO compras (fornecedor_id, usuario_id, valor_total) VALUES (?, ?, ?)");
                $purchaseId->execute([$data['fornecedor_id'] ?? null, $_SESSION['usuario_id'], round($totalCompra, 2)]);
                $cId = $db->lastInsertId();

                foreach ($items as $item) {
                    $db->prepare("INSERT INTO compra_itens (compra_id, produto_id, quantidade, preco_custo) VALUES (?, ?, ?, ?)")
                       ->execute([$cId, $item['id'], $item['qty'], $item['cost']]);

                    $productModel->updateStock($item['id'], $item['qty'], 'entrada', $filialId);

                    $db->prepare("UPDATE produtos SET preco_custo = ? WHERE id = ?")
                       ->execute([$item['cost'], $item['id']]);

                    $moveModel->record($item['id'], $filialId, $item['qty'], 'entrada', "Compra #$cId", $_SESSION['usuario_id']);
                }

                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
