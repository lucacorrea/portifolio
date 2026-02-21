<?php
namespace App\Controllers;

use App\Models\Product;
use App\Models\StockMovement;

class InventoryController extends BaseController {
    public function index() {
        $productModel = new Product();
        $movementModel = new StockMovement();

        $stats = [
            'total_itens' => $this->sum('produtos', 'quantidade'),
            'valor_custo' => $this->sum('produtos', 'preco_custo * quantidade'),
            'itens_criticos' => count($productModel->getCriticalStock()),
            'mov_mes' => $this->count('movimentacao_estoque', "MONTH(data_movimento) = MONTH(CURRENT_DATE)")
        ];

        $products = $productModel->all("categoria ASC, nome ASC");
        $movements = $movementModel->getHistory(null, 20);
        $categories = $productModel->getCategories();

        ob_start();
        $data = [
            'stats' => $stats,
            'products' => $products,
            'movements' => $movements,
            'categories' => $categories
        ];
        extract($data);
        require __DIR__ . "/../../../views/inventory.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Materiais e Estoque',
            'pageTitle' => 'Catálogo de Produtos e Inventário',
            'content' => $content
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Product();
            $data = $_POST;

            // Handle Image Upload
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $dir = __DIR__ . "/../../../public/uploads/produtos/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . "." . $ext;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $filename)) {
                    $data['imagens'] = $filename;
                }
            }

            $model->save($data);
            $this->redirect('estoque.php?msg=Produto salvo com sucesso');
        }
    }

    public function move() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $productModel = new Product();
            $movementModel = new StockMovement();

            $id = $_POST['produto_id'];
            $qty = $_POST['quantidade'];
            $type = $_POST['tipo'];
            $reason = $_POST['motivo'];
            $filialId = $_POST['deposito_id'] ?? 1;

            $productModel->updateStock($id, $qty, $type);
            $movementModel->record($id, $filialId, $qty, $type, $reason, $_SESSION['usuario_id']);

            $this->redirect('estoque.php?msg=Movimentação realizada');
        }
    }

    private function sum($table, $expression) {
        $db = \App\Config\Database::getInstance()->getConnection();
        return $db->query("SELECT SUM($expression) FROM $table")->fetchColumn() ?: 0;
    }

    private function count($table, $condition = "1=1") {
        $db = \App\Config\Database::getInstance()->getConnection();
        return $db->query("SELECT COUNT(*) FROM $table WHERE $condition")->fetchColumn() ?: 0;
    }
}
