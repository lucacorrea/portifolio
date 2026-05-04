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

        try {
            $model = new Usuario();
            $usuario = $model->buscarAtivoPorIdentificacao($identificacao);
        } catch (Throwable $exception) {
            $this->logLoginError($exception);
            flash_set('error', 'Não foi possível conectar ao banco de dados. Confira as configurações e tente novamente.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        if (!$usuario || !Auth::verifyPassword($senha, $usuario['senha'])) {
            flash_set('error', 'Nome, e-mail ou senha inválidos.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        Auth::login($usuario);

        try {
            $model->registrarUltimoLogin((int) $usuario['id']);
        } catch (Throwable $exception) {
            // O login não deve falhar só porque o registro de auditoria não foi salvo.
        }

        header('Location: ' . Auth::homeForNivel($usuario['nivel']));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    private function logLoginError(Throwable $exception): void
    {
        if (!defined('BASE_PATH')) {
            return;
        }

        $diretorio = BASE_PATH . '/storage/logs';
        if (!is_dir($diretorio)) {
            return;
        }

        $linha = sprintf(
            "[%s] Login DB error: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        @file_put_contents($diretorio . '/app.log', $linha, FILE_APPEND);
    }
}
