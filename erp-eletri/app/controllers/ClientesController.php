<?php
// app/controllers/ClientesController.php

class ClientesController extends Controller {
    
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        // Placeholder for Clientes View
        // $this->view('clientes/index');
        
        // Temporary: show a simple message or create a temporary view
        echo "<h1>Módulo de Clientes - Em Construção</h1>";
    }
}
