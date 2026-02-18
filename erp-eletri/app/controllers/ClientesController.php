<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cliente;
use App\Middleware\AuthMiddleware;

class ClientesController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $clienteModel = new Cliente();
        $clientes = $clienteModel->getAll();
        
        $this->view('clientes/index', ['clientes' => $clientes]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $clienteModel = new Cliente();
            $data = [
                'nome' => $_POST['nome'],
                'cpf_cnpj' => $_POST['cpf_cnpj'],
                'ie' => $_POST['ie'],
                'endereco' => $_POST['endereco'],
                'cidade' => $_POST['cidade'],
                'estado' => $_POST['estado'],
                'tipo' => $_POST['tipo'],
                'limite_credito' => str_replace(',', '.', $_POST['limite_credito'])
            ];

            if ($clienteModel->create($data)) {
                $this->redirect('/clientes');
            }
        }
        
        $this->view('clientes/form', ['action' => 'create']);
    }

    public function edit($id)
    {
        $clienteModel = new Cliente();
        $cliente = $clienteModel->getById($id);

        if (!$cliente) {
            $this->redirect('/clientes');
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
             $data = [
                'nome' => $_POST['nome'],
                'cpf_cnpj' => $_POST['cpf_cnpj'],
                'ie' => $_POST['ie'],
                'endereco' => $_POST['endereco'],
                'cidade' => $_POST['cidade'],
                'estado' => $_POST['estado'],
                'tipo' => $_POST['tipo'],
                'limite_credito' => str_replace(',', '.', $_POST['limite_credito'])
            ];
            
            // Should verify if model->update works with new $data array
            if ($clienteModel->update($id, $data)) {
                 $this->redirect('/clientes');
            }
        }

        $this->view('clientes/form', ['cliente' => $cliente, 'action' => 'edit']);
    }
}
