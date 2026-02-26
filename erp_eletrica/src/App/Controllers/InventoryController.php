<?php
namespace App\Controllers;

use App\Services\InventoryService;

class InventoryController extends BaseController {
    private $service;

    public function __construct() {
        $this->service = new InventoryService();
    }

    public function index() {
        $productModel = new \App\Models\Product();
        $movementModel = new \App\Models\StockMovement();

        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;

        $stats = [
            'total_itens' => $this->sum('produtos', 'quantidade'),
            'valor_custo' => $this->sum('produtos', 'preco_custo * quantidade'),
            'itens_criticos' => count($productModel->getCriticalStock(!$isMatriz ? $filialId : null)),
            'mov_mes' => $this->count('movimentacao_estoque', "MONTH(data_movimento) = MONTH(CURRENT_DATE)")
        ];

        $page = (int)($_GET['page'] ?? 1);
        $pagination = $productModel->paginate(6, $page, "categoria ASC, nome ASC");
        $products = $pagination['data'];
        $allProducts = $productModel->all("nome ASC");
        $movements = $movementModel->getHistory(null, 20);
        $categories = $productModel->getCategories();

        $this->render('inventory', [
            'stats' => $stats,
            'products' => $products,
            'allProducts' => $allProducts,
            'pagination' => $pagination,
            'movements' => $movements,
            'categories' => $categories
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $model = new \App\Models\Product();
            $data = $_POST;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $dir = dirname(__DIR__, 3) . "/public/uploads/produtos/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = uniqid() . "." . pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
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
            validateCsrf($_POST['csrf_token'] ?? '');
            $this->service->recordMovement($_POST);
            $this->redirect('estoque.php?msg=Movimentação realizada com sucesso');
        }
    }

    private function sum($table, $expression) {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $where = (!$isMatriz && $filialId) ? " WHERE filial_id = $filialId" : "";
        return $db->query("SELECT SUM($expression) FROM $table $where")->fetchColumn() ?: 0;
    }

    private function count($table, $condition = "1=1") {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $whereFilial = (!$isMatriz && $filialId) ? " AND filial_id = $filialId" : "";
        return $db->query("SELECT COUNT(*) FROM $table WHERE ($condition) $whereFilial")->fetchColumn() ?: 0;
    }
}
