<?php
// app/controllers/FiliaisController.php

class FiliaisController extends Controller {
    
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        echo "<h1>Gestão de Filiais - Em Construção</h1>";
    }
}
