<?php
class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            header('Location: ' . Auth::homeForNivel(Auth::user()['nivel']));
            exit;
        }

        $this->view('auth/login');
    }

    public function processarLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        if (!Csrf::validate($_POST['_csrf'] ?? '')) {
            flash_set('error', 'Sessão expirada. Tente novamente.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = (string) ($_POST['senha'] ?? '');

        if (!$email || $senha === '') {
            flash_set('error', 'Informe e-mail e senha.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        $model = new Usuario();
        $usuario = $model->buscarAtivoPorEmail($email);

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            flash_set('error', 'E-mail ou senha inválidos.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        Auth::login($usuario);
        $model->registrarUltimoLogin((int) $usuario['id']);

        header('Location: ' . Auth::homeForNivel($usuario['nivel']));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }
}
