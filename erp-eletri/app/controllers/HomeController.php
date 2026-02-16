<?php
// app/controllers/HomeController.php

class HomeController extends Controller {
    
    public function index() {
        // Verificar Autenticação (Simples por enquanto)
        if (!isset($_SESSION['user_id'])) {
            // Em produção, descomentar:
            // $this->redirect('login');
            // return;
        }

        $data = [
            'view' => 'home/index',
            'title' => 'Dashboard'
        ];

        $this->view('home/index', $data);
    }
}
