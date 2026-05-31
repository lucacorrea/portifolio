<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/session.php';

startSecureSession();

function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'] ?? '',
        'email' => $_SESSION['usuario_email'] ?? '',
        'nivel' => $_SESSION['usuario_nivel'] ?? '',
        'empresa_id' => $_SESSION['empresa_id'] ?? null,
        'empresa_nome' => $_SESSION['empresa_nome'] ?? '',
    ];
}

function loginUrl(): string
{
    $base = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pages/') ? '../login.php' : 'login.php';
    $next = $_SERVER['REQUEST_URI'] ?? '';
    return $base . '?next=' . urlencode($next);
}

function requireLogin(): void
{
    if (isLoggedIn()) {
        return;
    }

    header('Location: ' . loginUrl());
    exit;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function auditLogin(?int $usuarioId, string $email, bool $sucesso, string $motivo): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO login_auditoria (usuario_id, email, ip, user_agent, sucesso, motivo)
             VALUES (:usuario_id, :email, :ip, :user_agent, :sucesso, :motivo)'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':email' => mb_strtolower(trim($email)),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':sucesso' => $sucesso ? 1 : 0,
            ':motivo' => $motivo,
        ]);
    } catch (Throwable $e) {
        // Nunca derrube o login por erro de auditoria.
    }
}

function tooManyLoginAttempts(): bool
{
    $now = time();
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'] ?? [], fn ($time) => ($now - (int)$time) < 900);

    return count($_SESSION['login_attempts']) >= 5;
}

function registerFailedLoginAttempt(): void
{
    $_SESSION['login_attempts'][] = time();
}

function attemptLogin(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));

    if (tooManyLoginAttempts()) {
        auditLogin(null, $email, false, 'Muitas tentativas');
        return [false, 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        registerFailedLoginAttempt();
        auditLogin(null, $email, false, 'Dados inválidos');
        return [false, 'E-mail ou senha inválidos.'];
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.empresa_id, u.nome, u.email, u.senha_hash, u.nivel, u.ativo,
                e.nome AS empresa_nome, e.ativo AS empresa_ativa
         FROM usuarios u
         INNER JOIN empresas e ON e.id = u.empresa_id
         WHERE u.email = :email
         LIMIT 1'
    );

    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['senha_hash'])) {
        registerFailedLoginAttempt();
        auditLogin($user['id'] ?? null, $email, false, 'Credenciais inválidas');
        return [false, 'E-mail ou senha inválidos.'];
    }

    if ((int)$user['ativo'] !== 1 || (int)$user['empresa_ativa'] !== 1) {
        auditLogin((int)$user['id'], $email, false, 'Usuário ou empresa inativa');
        return [false, 'Usuário sem permissão de acesso.'];
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int)$user['id'];
    $_SESSION['empresa_id'] = (int)$user['empresa_id'];
    $_SESSION['usuario_nome'] = $user['nome'];
    $_SESSION['usuario_email'] = $user['email'];
    $_SESSION['usuario_nivel'] = $user['nivel'];
    $_SESSION['empresa_nome'] = $user['empresa_nome'];
    $_SESSION['login_attempts'] = [];

    db()->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id')
        ->execute([':id' => (int)$user['id']]);

    auditLogin((int)$user['id'], $email, true, 'Login realizado');

    return [true, 'Login realizado com sucesso.'];
}
