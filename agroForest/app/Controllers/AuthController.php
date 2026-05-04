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

        $identificacao = trim((string) ($_POST['identificacao'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');

        if ($identificacao === '' || $senha === '') {
            flash_set('error', 'Informe nome ou e-mail e senha.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        $model = new Usuario();
        $usuario = $model->buscarAtivoPorIdentificacao($identificacao);

        if (!$usuario || !Auth::verifyPassword($senha, $usuario['senha'])) {
            flash_set('error', 'Nome, e-mail ou senha inválidos.');
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
