<?php
// app/controllers/UsuariosController.php

class UsuariosController extends Controller {
    
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        // Using existing User model if available
        $userModel = $this->model('User'); 
        // $users = $userModel->getAll(); 
        
        echo "<h1>Gestão de Usuários - Em Construção</h1>";
    }
}
