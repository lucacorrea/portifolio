<?php
// app/controllers/EstoqueController.php

class EstoqueController extends Controller {
    
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function movimentacao() {
        echo "<h1>Movimentação de Estoque - Em Construção</h1>";
    }
}
