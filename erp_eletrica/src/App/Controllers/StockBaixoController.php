<?php
namespace App\Controllers;

use App\Models\Product;

class StockBaixoController extends BaseController {
    public function index() {
        checkAuth(['admin', 'gerente']);
        
        $productModel = new Product();
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $targetFilial = !$isMatriz ? $filialId : null;
        
        // Filtros
        $q = $_GET['q'] ?? '';
        $categoria = $_GET['categoria'] ?? '';
        $status = $_GET['status'] ?? ''; // CRITICO, BAIXO, OK
        
        $stats = $productModel->getStockStats($targetFilial);
        
        // Base query para a listagem
        $sql = "SELECT * FROM produtos WHERE 1=1";
        $params = [];
        
        if ($targetFilial) {
            $sql .= " AND filial_id = ?";
            $params[] = $targetFilial;
        }
        
        if ($q) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        
        if ($categoria) {
            $sql .= " AND categoria = ?";
            $params[] = $categoria;
        }
        
        if ($status) {
            if ($status === 'CRITICO') {
                $sql .= " AND quantidade <= estoque_minimo AND estoque_minimo > 0";
            } elseif ($status === 'BAIXO') {
                $sql .= " AND quantidade > estoque_minimo AND quantidade <= (estoque_minimo * 1.5) AND estoque_minimo > 0";
            } elseif ($status === 'OK') {
                $sql .= " AND (quantidade > (estoque_minimo * 1.5) OR estoque_minimo = 0)";
            }
        }
        
        $sql .= " ORDER BY (quantidade - estoque_minimo) ASC";
        
        $products = $productModel->query($sql, $params)->fetchAll();
        $categories = $productModel->getCategories();
        
        $this->render('stock_baixo', [
            'products' => $products,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => [
                'q' => $q,
                'categoria' => $categoria,
                'status' => $status
            ]
        ]);
    }
}
