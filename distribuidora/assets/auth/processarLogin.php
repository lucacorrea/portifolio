<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================
   INCLUDES
========================= */
$helpers = __DIR__ . '/../dados/_helpers.php';
if (is_file($helpers)) {
    require_once $helpers;
}

require_once __DIR__ . '/../conexao.php';

/* =========================
   FALLBACKS
========================= */
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('old_set')) {
    function old_set(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
}

if (!function_exists('old_clear')) {
    function old_clear(): void
    {
        unset($_SESSION['_old']);
    }
}

if (!function_exists('post_str')) {
    function post_str(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('post_raw')) {
    function post_raw(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') return false;
        if (!is_string($token) || $token === '') return false;
        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('normalize_redirect_url')) {
    function normalize_redirect_url(string $url, string $fallback = '../../index.php'): string
    {
        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        if (preg_match('~[\r\n]~', $url)) {
            return $fallback;
        }

        if (preg_match('~^https?://~i', $url)) {
            return $fallback;
        }

        return $url;
    }
}

if (!function_exists('verificar_senha_sha256')) {
    function verificar_senha_sha256(string $senhaDigitada, string $salt, string $hashSalvo): bool
    {
        $hashAtual = hash('sha256', $salt . '|' . $senhaDigitada);
        return hash_equals($hashSalvo, $hashAtual);
    }
}

if (!function_exists('auth_login')) {
    function auth_login(array $usuario): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id']      = (int)($usuario['id'] ?? 0);
        $_SESSION['usuario_nome']    = (string)($usuario['nome'] ?? '');
        $_SESSION['usuario_email']   = (string)($usuario['email'] ?? '');
        $_SESSION['usuario_status']  = (string)($usuario['status'] ?? 'ATIVO');
        $_SESSION['usuario_logado']  = true;
    }
}

/* =========================
   HELPERS LOCAIS
========================= */
function lower_email(string $email): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($email, 'UTF-8')
        : strtolower($email);
}

function set_email_cookie(string $email, bool $remember): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    if ($remember) {
        setcookie(
            'plhb_login_email',
            $email,
            [
                'expires'  => time() + (60 * 60 * 24 * 30),
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        return;
    }

    setcookie(
        'plhb_login_email',
        '',
        [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function voltar_com_erro(string $redirectBack, string $mensagem, array $old = []): void
{
    if ($old !== []) {
        old_set($old);
    } else {
        old_clear();
    }

    flash_set('auth_erro', $mensagem);
    redirect($redirectBack);
}

/* =========================
   PROCESSAMENTO
========================= */
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('../../index.php');
}

$redirectBack = normalize_redirect_url(post_str('redirect_back', '../../index.php'), '../../index.php');

$csrf = post_str('_csrf');
if (!csrf_validate($csrf)) {
    voltar_com_erro($redirectBack, 'Sessão expirada. Atualize a página e tente novamente.');
}

$email    = lower_email(post_str('email'));
$senha    = post_raw('senha');
$lembrar  = isset($_POST['lembrar']) && (string)$_POST['lembrar'] === '1';

$old = [
    'email' => $email,
];

/* =========================
   VALIDAÇÕES
========================= */
if ($email === '') {
    voltar_com_erro($redirectBack, 'Informe o e-mail.', $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    voltar_com_erro($redirectBack, 'Informe um e-mail válido.', $old);
}

if ($senha === '') {
    voltar_com_erro($redirectBack, 'Informe a senha.', $old);
}

/* =========================
   CONSULTA NO BANCO
========================= */
try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $sql = "SELECT id, nome, email, senha_hash, senha_salt, status
            FROM usuarios
            WHERE email = :email
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);

    $usuario = $stmt->fetch();

    if (!$usuario) {
        voltar_com_erro($redirectBack, 'E-mail não encontrado.', $old);
    }

    $status = (string)($usuario['status'] ?? 'ATIVO');
    if ($status !== 'ATIVO') {
        voltar_com_erro($redirectBack, 'Seu acesso está inativo. Procure o administrador.', $old);
    }

    $senhaSalt = (string)($usuario['senha_salt'] ?? '');
    $senhaHash = (string)($usuario['senha_hash'] ?? '');

    if ($senhaSalt === '' || $senhaHash === '') {
        voltar_com_erro($redirectBack, 'Usuário sem credenciais válidas no sistema.', $old);
    }

    if (!verificar_senha_sha256($senha, $senhaSalt, $senhaHash)) {
        voltar_com_erro($redirectBack, 'Senha incorreta.', $old);
    }

    old_clear();
    auth_login($usuario);
    set_email_cookie($email, $lembrar);

    flash_set('auth_ok', 'Login realizado com sucesso.');
    redirect('../../dashboard.php');
} catch (Throwable $e) {
    $logFile = __DIR__ . '/../debug_errors.log';
    @file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] LOGIN ERROR: " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    voltar_com_erro($redirectBack, 'Não foi possível validar suas credenciais agora. Tente novamente.', $old);
}
?>