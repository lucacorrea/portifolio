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
            'mov_mes' => $this->count('movimentacao_estoque', "MONTH(data_movimento) = MONTH(CURRENT_DATE)", 'deposito_id')
        ];

        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'categoria' => $_GET['categoria'] ?? ''
        ];
        
        $pagination = $productModel->paginate(15, $page, "categoria ASC, nome ASC", $filters);
        $products = $pagination['data'];
        $allProducts = $productModel->all("nome ASC");
        $movements = $movementModel->getHistory(null, 20);
        $categories = $productModel->getCategories();
        
        $supplierModel = new \App\Models\Supplier();
        $suppliers = $supplierModel->all("nome_fantasia ASC");

        $this->render('inventory', [
            'stats' => $stats,
            'products' => $products,
            'allProducts' => $allProducts,
            'pagination' => $pagination,
            'movements' => $movements,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'filters' => $filters
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $model = new \App\Models\Product();
            $data = $_POST;
            
            // Fix: ensure product is linked to the correct filial
            if (empty($data['filial_id'])) {
                $data['filial_id'] = $_SESSION['filial_id'] ?? 1;
            }

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

    public function delete() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $model = new \App\Models\Product();
            $model->delete($id);
            $this->redirect('estoque.php?msg=Material excluído com sucesso');
        }
    }

    public function movimentacoes() {
        $movementModel = new \App\Models\StockMovement();
        $productModel = new \App\Models\Product();

        $filters = [
            'desde' => $_GET['desde'] ?? date('Y-m-01'),
            'ate' => $_GET['ate'] ?? date('Y-m-d'),
            'produto_id' => $_GET['produto_id'] ?? ''
        ];

        $movements = $movementModel->getHistory($filters, 100);
        $products = $productModel->all("nome ASC");

        $this->render('inventory_movements', [
            'movements' => $movements,
            'products' => $products,
            'filters' => $filters,
            'title' => 'Histórico de Movimentações',
            'pageTitle' => 'Movimentações de Estoque'
        ]);
    }

    private function sum($table, $expression, $filialCol = 'filial_id') {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        if ($table === 'produtos') {
            $expr = str_replace('quantidade', 'COALESCE(ef.quantidade, 0)', $expression);
            $sql = "SELECT SUM($expr) FROM produtos p LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$filialId]);
            return $stmt->fetchColumn() ?: 0;
        }

        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $where = (!$isMatriz && $filialId) ? " WHERE $filialCol = $filialId" : "";
        return $db->query("SELECT SUM($expression) FROM $table $where")->fetchColumn() ?: 0;
    }

    private function count($table, $condition = "1=1", $filialCol = 'filial_id') {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        if ($table === 'produtos') {
             // Se for produtos, geralmente queremos todos os produtos do catálogo (se centralizado)
             // ou apenas os que têm estoque (se for filtro de estoque). 
             // Mas aqui 'count' costuma ser usado para estatísticas gerais.
        }

        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $whereFilial = (!$isMatriz && $filialId) ? " AND $filialCol = $filialId" : "";
        return $db->query("SELECT COUNT(*) FROM $table WHERE ($condition) $whereFilial")->fetchColumn() ?: 0;
    }
}
