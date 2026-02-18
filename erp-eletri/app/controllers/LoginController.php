<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class LoginController extends Controller
{
    public function index()
    {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $data = [
            'error' => ''
        ];

        // Process view rendering
        // We will create a simple login view file compatible with our new Controller::view method
        $this->view('login/index', $data); 
    }

    public function auth()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        $userModel = new User();
        $user = $userModel->login($email, $password);

        if ($user) {
            // Configure Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_nivel'] = $user['nivel'];
            $_SESSION['user_filial_id'] = $user['filial_id'];

            $this->redirect('/dashboard');
        } else {
            // In a real app we'd flash session data, for now passing error via view might be tricky with redirect.
            // Let's just include the view with error
             $data = [
                'error' => 'Credenciais inválidas ou usuário inativo.'
            ];
            $this->view('login/index', $data);
        }
    }

    public function logout()
    {
        session_destroy();
        $this->redirect('/login');
    }
}
