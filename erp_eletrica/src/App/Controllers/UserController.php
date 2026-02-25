<?php
namespace App\Controllers;

use App\Models\User;

class UserController extends BaseController {
    public function index() {
        $model = new User();
        $users = $model->all();

        $this->render('users', [
            'users' => $users,
            'title' => 'Gestão de Colaboradores',
            'pageTitle' => 'Controle de Acesso e Identidade (IAM)'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new User();
            $data = $_POST;
            $data['id'] = $_POST['usuario_id'] ?? null;
            $data['ativo'] = isset($_POST['ativo']) ? 1 : 0;
            
            $model->save($data);
            $this->redirect('usuarios.php?msg=Usuário processado com sucesso');
        }
    }
}
