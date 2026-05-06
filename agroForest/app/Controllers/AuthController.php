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
            $usuario = $model->buscarPorIdentificacao($identificacao);
        } catch (PDOException $exception) {
            AppLogger::error('Login database failed: ' . Database::activeContext(), $exception);
            flash_set('error', $this->databaseMessage($exception));
            header('Location: ' . route_url('auth', 'login'));
            exit;
        } catch (Throwable $exception) {
            AppLogger::error('Login query failed: ' . Database::activeContext(), $exception);
            flash_set('error', 'Erro interno no login. Verifique o arquivo storage/logs/app.log.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        if (!$usuario) {
            flash_set('error', 'Nome, e-mail ou senha inválidos.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        if ((int) ($usuario['ativo'] ?? 0) !== 1) {
            flash_set('error', 'Usuário inativo. Solicite a liberação do acesso.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        if (!Auth::verifyPassword($senha, (string) $usuario['senha'])) {
            AppLogger::info('Login denied for user id=' . (int) $usuario['id']);
            flash_set('error', 'Nome, e-mail ou senha inválidos.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }

        Auth::login($usuario);

        try {
            $model->registrarUltimoLogin((int) $usuario['id']);
        } catch (Throwable $exception) {
            // O login não deve falhar só porque o registro de auditoria não foi salvo.
            AppLogger::error('Failed to update ultimo_login for user id=' . (int) $usuario['id'], $exception);
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

    private function databaseMessage(PDOException $exception): string
    {
        $driverCode = $this->mysqlErrorCode($exception);
        $message = $exception->getMessage();

        if (str_contains($message, 'could not find driver')) {
            return 'A extensão pdo_mysql do PHP não está habilitada no servidor.';
        }

        if ($driverCode === 1045 || $driverCode === 1044 || str_contains($message, 'Access denied')) {
            return 'Usuário ou senha do banco MySQL estão incorretos.';
        }

        if ($driverCode === 1049 || str_contains($message, 'Unknown database')) {
            return 'O banco MySQL configurado não existe ou não está vinculado ao site.';
        }

        if (
            in_array($driverCode, [2002, 2003, 2005], true)
            || str_contains($message, 'No such file')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'php_network_getaddresses')
        ) {
            return 'O host do MySQL não respondeu. Confira se o host é localhost e a porta é 3306.';
        }

        if (str_contains($message, "Base table or view not found") || str_contains($message, "doesn't exist")) {
            return 'A tabela usuarios não existe. Rode o installAuth.php ou o SQL de criação no phpMyAdmin.';
        }

        if (str_contains($message, 'Unknown column')) {
            return 'A tabela usuarios existe, mas está com colunas faltando. Rode o SQL de atualização.';
        }

        return 'Falha no banco MySQL: ' . $message . '. Verifique o arquivo storage/logs/app.log.';
    }

    private function mysqlErrorCode(PDOException $exception): int
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($driverCode > 0) {
            return $driverCode;
        }

        $code = $exception->getCode();
        return is_numeric($code) ? (int) $code : 0;
    }
}
