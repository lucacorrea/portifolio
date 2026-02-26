<?php
namespace App\Controllers;

use App\Models\User;

class UserController extends BaseController {
    public function index() {
        $userModel = new \App\Models\User();
        $filialModel = new \App\Models\Filial();
        
        $page = (int)($_GET['page'] ?? 1);
        $pagination = $userModel->paginate(6, $page);
        $users = $pagination['data'];
        $branches = $filialModel->all();

        $this->render('users', [
            'users' => $users,
            'pagination' => $pagination,
            'branches' => $branches,
            'title' => 'Gestão de Usuários',
            'pageTitle' => 'Operadores do Sistema'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $userModel = new \App\Models\User();
            $data = $_POST;
            $data['id'] = $_POST['usuario_id'] ?? null;
            $data['ativo'] = isset($_POST['ativo']) ? 1 : 0;
            
            $userModel->save($data);
            $this->redirect('usuarios.php?msg=Usuário salvo com sucesso');
        }
    }
}
