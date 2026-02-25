<?php
namespace App\Services;

use App\Repositories\UserRepository;

class AuthService extends BaseService {
    public function __construct() {
        parent::__construct(new UserRepository());
    }

    public function login(string $email, string $password) {
        $user = $this->repository->findByEmail($email);
        
        if ($user && password_verify($password, $user['senha'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_nivel'] = $user['nivel'];
            $_SESSION['filial_id'] = $user['filial_id'];
            
            $this->repository->updateLastLogin($user['id']);
            $this->logAction('login', 'usuarios', $user['id']);
            return true;
        }
        
        return false;
    }

    public static function check($niveis_permitidos = []) {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }
        
        if (!empty($niveis_permitidos) && !in_array($_SESSION['usuario_nivel'], $niveis_permitidos)) {
            header('Location: index.php?msg=Acesso negado');
            exit;
        }
    }

    public function logout() {
        if (isset($_SESSION['usuario_id'])) {
            $this->logAction('logout', 'usuarios', $_SESSION['usuario_id']);
        }
        session_unset();
        session_destroy();
    }
}
