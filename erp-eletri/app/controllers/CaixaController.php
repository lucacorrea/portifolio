<?php
// app/controllers/CaixaController.php

class CaixaController extends Controller {

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
        // In a real app, check permission for 'caixa' role here
    }
    
    public function index() {
        $data = ['view' => 'caixa/index'];
        $this->view('caixa/index', $data);
    }

    public function searchPreSale() {
        if (isset($_GET['id'])) {
            $saleModel = $this->model('Sale');
            $preSale = $saleModel->getPreSale($_GET['id']);
            
            if ($preSale) {
                $items = $saleModel->getPreSaleItems($_GET['id']);
                echo json_encode(['found' => true, 'header' => $preSale, 'items' => $items]);
            } else {
                echo json_encode(['found' => false]);
            }
        }
    }

    public function finalize() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $saleModel = $this->model('Sale');
            $saleId = $saleModel->finalize(
                $data['pre_sale_id'],
                $data['payment'],
                $_SESSION['user_id'],
                $_SESSION['user_filial_id']
            );

            if ($saleId) {
                echo json_encode(['success' => true, 'id' => $saleId]);
            } else {
                echo json_encode(['success' => false]);
            }
        }
    }
}
