<?php
// app/controllers/ProdutosController.php

class ProdutosController extends Controller {
    
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login');
        }
    }
    
    public function index() {
        $produtoModel = $this->model('Produto');
        $produtos = $produtoModel->getAll();
        
        $this->view('produtos/index', ['view' => 'produtos/index', 'products' => $produtos]);
    }

    public function create() {
        $produtoModel = $this->model('Produto');
        $categories = $produtoModel->getCategories();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'categoria_id' => $_POST['categoria_id'],
                'nome' => $_POST['nome'],
                'codigo_interno' => $_POST['codigo_interno'],
                'codigo_barras' => $_POST['codigo_barras'],
                'ncm' => $_POST['ncm'],
                'unidade' => $_POST['unidade'],
                'preco_custo' => str_replace(',', '.', $_POST['preco_custo']),
                'preco_venda' => str_replace(',', '.', $_POST['preco_venda']),
                'preco_prefeitura' => str_replace(',', '.', $_POST['preco_prefeitura']),
                'preco_avista' => str_replace(',', '.', $_POST['preco_avista'])
            ];

            if ($produtoModel->create($data)) {
                 $this->redirect('produtos/index');
            }
        }
        
        $this->view('produtos/form', ['view' => 'produtos/form', 'categories' => $categories, 'action' => 'create']);
    }

    public function edit($id) {
        $produtoModel = $this->model('Produto');
        $produto = $produtoModel->getById($id);
        $categories = $produtoModel->getCategories();
        $estoque = $produtoModel->getEstoque($id);

        if (!$produto) {
            $this->redirect('produtos/index');
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
             $data = [
                'categoria_id' => $_POST['categoria_id'],
                'nome' => $_POST['nome'],
                'codigo_interno' => $_POST['codigo_interno'],
                'codigo_barras' => $_POST['codigo_barras'],
                'ncm' => $_POST['ncm'],
                'unidade' => $_POST['unidade'],
                'preco_custo' => str_replace(',', '.', $_POST['preco_custo']),
                'preco_venda' => str_replace(',', '.', $_POST['preco_venda']),
                'preco_prefeitura' => str_replace(',', '.', $_POST['preco_prefeitura']),
                'preco_avista' => str_replace(',', '.', $_POST['preco_avista'])
            ];

            if ($produtoModel->update($id, $data)) {
                 $this->redirect('produtos/index');
            }
        }

        $this->view('produtos/form', [
            'view' => 'produtos/form', 
            'product' => $produto, 
            'categories' => $categories, 
            'estoque' => $estoque,
            'action' => 'edit'
        ]);
    }
}
