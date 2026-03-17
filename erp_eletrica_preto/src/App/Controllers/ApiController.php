<?php
namespace App\Controllers;

use App\Services\JwtService;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;

class ApiController extends BaseController {
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($password, $user['senha'])) {
            $token = JwtService::generate([
                'user_id' => $user['id'],
                'nivel' => $user['nivel'],
                'filial_id' => $user['filial_id']
            ]);
            $this->json(['success' => true, 'token' => $token]);
        } else {
            $this->json(['success' => false, 'error' => 'Credenciais inválidas'], 401);
        }
    }

    protected function authenticate() {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            $payload = JwtService::validate($matches[1]);
            if ($payload) {
                $_SESSION['usuario_id'] = $payload['user_id'];
                $_SESSION['usuario_nivel'] = $payload['nivel'];
                $_SESSION['filial_id'] = $payload['filial_id'];
                return true;
            }
        }
        $this->json(['error' => 'Não autorizado'], 401);
        exit;
    }

    public function products() {
        $this->authenticate();
        $model = new Product();
        $this->json($model->all());
    }

    public function stock() {
        $this->authenticate();
        $id = $_GET['id'] ?? null;
        if (!$id) $this->json(['error' => 'ID do produto requerido'], 400);
        
        $model = new Product();
        $product = $model->find($id);
        $this->json(['id' => $id, 'estoque' => $product['estoque'] ?? 0]);
    }

    private function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
