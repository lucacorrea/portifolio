<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Venda;
use App\Models\Produto;
use App\Models\Cliente;
use App\Middleware\AuthMiddleware;

class VendasController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $vendaModel = new Venda();
        $vendas = $vendaModel->getAll();
        
        $this->view('vendas/index', ['vendas' => $vendas]);
    }

    public function create() // POS Interface (PDV)
    {
        // Load dependencies for POS
        // Products for search
        // Clients
        $clienteModel = new Cliente();
        $clientes = $clienteModel->getAll(500); // Limit to 500 for dropdown, or use AJAX search

        $this->view('vendas/pdv', ['clientes' => $clientes]);
    }
    
    // AJAX: Search Products
    public function searchProducts()
    {
        $term = $_GET['term'] ?? '';
        $produtoModel = new Produto();
        $results = $produtoModel->search($term);
        
        $this->json($results);
    }
    
    // AJAX: Store Sale
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid method']);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->json(['success' => false, 'message' => 'Invalid JSON']);
        }
        
        $vendaModel = new Venda();
        
        $data = [
            'filial_id' => $_SESSION['user_filial_id'] ?? 1,
            'cliente_id' => $input['cliente_id'],
            'vendedor_id' => $_SESSION['user_id'],
            'caixa_id' => $_SESSION['user_id'], // Assuming seller is cashier for now
            'total' => $input['total'],
            'forma_pagamento' => $input['forma_pagamento'],
            'desconto' => $input['desconto'] ?? 0,
            'acrescimo' => 0,
            'observacoes' => ''
        ];
        
        $saleId = $vendaModel->createSale($data, $input['items']);
        
        if ($saleId) {
            $this->json(['success' => true, 'sale_id' => $saleId]);
        } else {
            $this->json(['success' => false, 'message' => 'Erro ao salvar venda']);
        }
    }
}
