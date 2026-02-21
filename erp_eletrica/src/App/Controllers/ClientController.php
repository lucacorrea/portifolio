<?php
namespace App\Controllers;

use App\Models\Client;

class ClientController extends BaseController {
    public function index() {
        $model = new Client();
        
        $searchTerm = $_GET['search'] ?? '';
        if ($searchTerm) {
            $clients = $model->search($searchTerm);
        } else {
            $clients = $model->all();
        }

        ob_start();
        $data = ['clients' => $clients, 'searchTerm' => $searchTerm];
        extract($data);
        require __DIR__ . "/../../../views/clients.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Clientes',
            'pageTitle' => 'Base de Clientes Corporativos',
            'content' => $content
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Client();
            $model->create($_POST);
            $this->redirect('clientes.php?msg=Cliente salvo com sucesso');
        }
    }
}
