<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Produto;
use App\Middleware\AuthMiddleware;

class ProdutosController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $produtoModel = new Produto();
        $produtos = $produtoModel->getAll();

        $this->view('produtos/index', ['products' => $produtos]);
    }

    public function create()
    {
        $produtoModel = new Produto();
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
            
            // Image Upload
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('prod_') . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
                    $data['imagem'] = 'uploads/products/' . $filename;
                }
            }

            if ($produtoModel->create($data)) {
                 $this->redirect('/produtos');
            }
        }

        $this->view('produtos/form', ['categories' => $categories, 'action' => 'create']);
    }

    public function edit($id)
    {
        $produtoModel = new Produto();
        $produto = $produtoModel->getById($id);
        $categories = $produtoModel->getCategories();
        $estoque = $produtoModel->getEstoque($id);

        if (!$produto) {
            $this->redirect('/produtos');
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

            // Image Upload
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('prod_') . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
                    $data['imagem'] = 'uploads/products/' . $filename;
                }
            }

            if ($produtoModel->update($id, $data)) {
                 $this->redirect('/produtos');
            }
        }

        $this->view('produtos/form', [
            'product' => $produto, 
            'categories' => $categories, 
            'estoque' => $estoque,
            'action' => 'edit'
        ]);
    }
}
