<?php
namespace App\Modules\Auth\Controllers;

class LoginController {
    public function index(){
        return view('auth/login');
    }

    public function authenticate(){
        if($_POST['email']=='admin@saas.com' && $_POST['senha']=='123'){
            $_SESSION['auth']=true;
            header('Location: dashboard');
            exit;
        }
        echo "Login inválido";
    }
}
