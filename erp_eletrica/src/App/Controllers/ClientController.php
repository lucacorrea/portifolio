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

        $this->render('clients', [
            'clients' => $clients,
            'searchTerm' => $searchTerm,
            'title' => 'Gestão de Clientes',
            'pageTitle' => 'Base de Clientes Corporativos'
        ]);
    }

    public function profile() {
        $id = $_GET['id'] ?? null;
        if (!$id) $this->redirect('clientes.php');

        $model = new Client();
        $client = $model->find($id);
        if (!$client) $this->redirect('clientes.php');

        $stats = $model->getStats($id);
        $history = $model->getPurchaseHistory($id);

        $this->render('clients/profile', [
            'client' => $client,
            'stats' => $stats,
            'history' => $history,
            'title' => 'Perfil CRM: ' . $client['nome'],
            'pageTitle' => 'Inteligência de Cliente'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Client();
            $data = $_POST;
            $data['id'] = $_POST['id'] ?? null;
            $model->save($data);
            $this->redirect('clientes.php?msg=Cliente salvo com sucesso');
        }
    }
}
