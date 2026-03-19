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
        
        $filters = [
            'q' => $_GET['q'] ?? '',
            'categoria' => $_GET['categoria'] ?? '',
            'status' => $_GET['status'] ?? ''
        ];
        
        $stats = $productModel->getStockStats($targetFilial);
        $products = $productModel->searchStockAlarms($filters, $targetFilial);
        $categories = $productModel->getCategories();
        
        $this->render('stock_baixo', [
            'products' => $products,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => $filters
        ]);
    }
}
