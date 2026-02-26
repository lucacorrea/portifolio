<?php
namespace App\Controllers;

use App\Models\OS;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;

class OSController extends BaseController {
    public function index() {
        $model = new OS();
        $osList = $model->getActive();
        
        $this->render('os', [
            'osList' => $osList,
            'title' => 'Gestão de Ordens de Serviço',
            'pageTitle' => 'Operações Técnicas e Manutenção'
        ]);
    }

    public function view($id) {
        if (!$id) $this->redirect('os.php');
        
        $model = new OS();
        $os = $model->findWithDetails($id);
        
        if (!$os) $this->redirect('os.php?msg=OS não encontrada');

        $userModel = new User();
        $tecnicos = $userModel->all("nome ASC"); // Filtered by level in view or model if needed

        $this->render('os/details', [
            'os' => $os,
            'tecnicos' => $tecnicos,
            'title' => 'Detalhes da OS #' . $os['numero_os'],
            'pageTitle' => 'Prontuário Técnico'
        ]);
    }

    public function new() {
        $clientModel = new Client();
        $productModel = new Product();
        $userModel = new User();

        $this->render('os/new', [
            'clientes' => $clientModel->all(),
            'produtos' => $productModel->all(),
            'tecnicos' => $userModel->all(),
            'title' => 'Nova Ordem de Serviço',
            'pageTitle' => 'Abertura de Chamado Técnico'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validatePost();
            $model = new OS();
            $data = $_POST;
            
            try {
                if (isset($data['id']) && !empty($data['id'])) {
                    $id = $data['id'];
                    unset($data['id']);
                    $model->update($id, $data);
                    $this->redirect("os.php?action=view&id=$id&msg=OS atualizada");
                } else {
                    $data['usuario_id'] = $_SESSION['usuario_id'];
                    $id = $model->create($data);
                    $this->redirect("os.php?action=view&id=$id&msg=OS criada com sucesso");
                }
            } catch (\Exception $e) {
                $this->redirect("os.php?msg=Erro ao salvar: " . $e->getMessage());
            }
        }
    }
}
