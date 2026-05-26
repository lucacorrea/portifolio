<?php
namespace App\Controllers;

use App\Models\User;

class UserController extends BaseController {
    public function index() {
        $userModel = new \App\Models\User();
        $filialModel = new \App\Models\Filial();
        
        $page = (int)($_GET['page'] ?? 1);
        
        // Obter filtros da requisição
        $filters = [
            'q' => $_GET['q'] ?? '',
            'nivel' => $_GET['nivel'] ?? '',
            'filial_id' => $_GET['filial_id'] ?? ''
        ];
        
        // Ordem por hierarquia (admin -> gerente -> vendedor) e depois por ordem alfabética de nome
        $order = "CASE WHEN u.nivel = 'admin' THEN 1 WHEN u.nivel = 'gerente' THEN 2 WHEN u.nivel = 'vendedor' THEN 3 ELSE 4 END ASC, u.nome ASC";
        
        $pagination = $userModel->paginate(6, $page, $order, $filters);
        $users = $pagination['data'];
        $branches = $filialModel->all();

        $this->render('users', [
            'users' => $users,
            'pagination' => $pagination,
            'branches' => $branches,
            'filters' => $filters,
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
            $data['auth_pin'] = $_POST['auth_pin'] ?? null;
            $data['auth_type'] = $_POST['auth_type'] ?? 'password';
            
            $userModel->save($data);
            $this->redirect('usuarios.php?msg=Usuário salvo com sucesso');
        }
    }

    public function toggle_status() {
        $id = (int)($_GET['id'] ?? 0);
        $status = (int)($_GET['status'] ?? 0);
        if ($id > 0) {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $msg = $status ? 'Usuário desbloqueado com sucesso' : 'Usuário bloqueado com sucesso';
            $this->redirect('usuarios.php?msg=' . urlencode($msg));
        }
    }

    public function delete() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $userModel = new \App\Models\User();
            $userModel->delete($id);
            $this->redirect('usuarios.php?msg=' . urlencode('Usuário apagado com sucesso'));
        }
    }
}
