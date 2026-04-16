<?php
namespace App\Controllers;

use App\Models\Client;

class ClientController extends BaseController {
    public function index() {
        $model = new Client();
        
        $searchTerm = $_GET['search'] ?? '';
        $page = (int)($_GET['page'] ?? 1);

        if ($searchTerm) {
            $clients = $model->search($searchTerm);
            $pagination = null;
        } else {
            $pagination = $model->paginate(6, $page);
            $clients = $pagination['data'];
        }

        $this->render('clients', [
            'clients' => $clients,
            'pagination' => $pagination,
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

    public function quickSave() {
        header('Content-Type: application/json');
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['nome'])) {
                throw new \Exception("Nome é obrigatório.");
            }

            $model = new Client();
            $clientId = $model->create([
                'nome' => $data['nome'],
                'cpf_cnpj' => $data['cpf_cnpj'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'endereco' => $data['endereco'] ?? null,
                'filial_id' => $_SESSION['filial_id'] ?? 1
            ]);

            echo json_encode(['success' => true, 'client_id' => $clientId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
