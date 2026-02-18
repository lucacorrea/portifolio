<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Venda;
use App\Models\Produto;
use App\Models\Cliente;

class DashboardController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $vendaModel = new Venda();
        $produtoModel = new Produto();
        $clienteModel = new Cliente();

        // Basic Stats
        // Use direct DB queries in Controller for specific dashboard stats or add methods to Models.
        // For cleaner code, let's keep it here or in a DashboardService. 
        // Given complexity, direct Model usage or simple queries via Model instance is fine.
        
        // We need to extend Model to support custom queries easily or add specific methods.
        // Let's add specific methods to Models if they don't exist, or just use getAll count for now (inefficient but works for small data).
        // Better: Custom queries.
        
        $stats = [
            'sales_today' => 0,
            'sales_month' => 0,
            'clients_count' => count($clienteModel->getAll(1000)), // Simple count
            'products_count' => count($produtoModel->getAll(10000)),
            'recent_sales' => $vendaModel->getAll(5)
        ];

        // Calculating Totals (Ideally inside Model)
        // Let's rely on the View or a Service for complex logic, but for now we pass simple lists/counts.
        
        $this->view('dashboard/index', ['stats' => $stats]);
    }
}
