<?php
// app/controllers/PrevendaController.php

class PrevendaController extends Controller {

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        $preSaleModel = $this->model('PreSale');
        $lastSales = $preSaleModel->getLastSales();
        
        $this->view('presale/index', [
            'view' => 'presale/index',
            'lastSales' => $lastSales
        ]);
    }
    
    // AJAX Methods
    public function searchProduct() {
        if (isset($_GET['term'])) {
            $productModel = $this->model('Produto');
            $results = $productModel->search($_GET['term']);
            echo json_encode($results);
        }
    }
    
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $preSaleData = [
                'filial_id' => $_SESSION['user_filial_id'] ?? 1, // Fallback to 1 if not set
                'cliente_id' => 1, // Default 'Cliente Balcao' for now
                'vendedor_id' => $_SESSION['user_id'],
                'total' => $data['total']
            ];
            
            $preSaleModel = $this->model('PreSale');
            $id = $preSaleModel->create($preSaleData, $data['items']);
            
            if ($id) {
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                echo json_encode(['success' => false]);
            }
        }
    }
}
