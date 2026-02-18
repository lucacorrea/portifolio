<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Produto;
use App\Models\Movimentacao;
use App\Middleware\AuthMiddleware;

class EstoqueController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $produtoModel = new Produto();
        // Here we might want a specific method to get stock overview, but getAll works for now
        $produtos = $produtoModel->getAll(); 
        
        $this->view('estoque/index', ['produtos' => $produtos]);
    }
    
    public function movimentacao()
    {
        $produtoModel = new Produto();
        $produtos = $produtoModel->getAll();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $movimentacao = new Movimentacao();
             
             $produtoId = $_POST['produto_id'];
             $filialId = $_SESSION['user_filial_id'] ?? 1; // Default to 1 if not set
             $usuarioId = $_SESSION['user_id'];
             $tipo = $_POST['tipo'];
             $quantidade = (int)$_POST['quantidade'];
             $motivo = $_POST['motivo'];
             
             if ($movimentacao->registrar($produtoId, $filialId, $usuarioId, $tipo, $quantidade, $motivo)) {
                 $this->redirect('/estoque');
             } else {
                 $error = "Erro ao registrar movimentação.";
             }
        }
        
        $this->view('estoque/movimentacao', ['produtos' => $produtos]);
    }
}
