<?php
// app/controllers/LoginController.php

class LoginController extends Controller {
    
    public function index() {
        // Se já estiver logado, redireciona para dashboard
        if (isset($_SESSION['user_id'])) {
            $this->redirect('home/index');
        }

        $data = [
            'view' => 'login/index',
            'error' => ''
        ];

        // Processar formulário de login via POST
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'];

            $userModel = $this->model('User');
            $user = $userModel->login($email, $password);

            if ($user) {
                // Configurar Sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_nivel'] = $user['nivel'];
                $_SESSION['user_filial_id'] = $user['filial_id'];

                $this->redirect('home/index');
            } else {
                $data['error'] = 'Credenciais inválidas ou usuário inativo.';
            }
        }

        // Carregar View de Login (sem header/footer padrão, layout limpo)
        require_once '../app/views/login/index.php';
    }

    public function logout() {
        session_destroy();
        $this->redirect('login');
    }
}
