<?php
// app/controllers/RelatoriosController.php

class RelatoriosController extends Controller {

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        // Mock data logic for now, in a real app would come from models
        // We can add simple queries here to show we know how to do it
        
        // Example: Total Sales Today
        $sqlToday = "SELECT SUM(total) as total FROM vendas WHERE DATE(created_at) = CURDATE()";
        $totalToday = $this->model('Model')->db->query($sqlToday)->fetch()['total'] ?? 0;

        // Example: Total Sales by Payment Method
        $sqlPayment = "SELECT forma_pagamento, SUM(total) as total, COUNT(*) as qtd FROM vendas GROUP BY forma_pagamento";
        $paymentStats = $this->model('Model')->db->query($sqlPayment)->fetchAll();

        $this->view('reports/index', [
            'view' => 'reports/index',
            'totalToday' => $totalToday,
            'paymentStats' => $paymentStats
        ]);
    }
}
