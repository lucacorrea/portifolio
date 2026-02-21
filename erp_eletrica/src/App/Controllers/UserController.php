<?php
namespace App\Controllers;

use App\Models\User;

class UserController extends BaseController {
    public function index() {
        $model = new User();
        $users = $model->all();

        ob_start();
        $data = ['users' => $users];
        extract($data);
        require __DIR__ . "/../../../views/users.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Colaboradores',
            'pageTitle' => 'Controle de Acesso e Identidade (IAM)',
            'content' => $content
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
