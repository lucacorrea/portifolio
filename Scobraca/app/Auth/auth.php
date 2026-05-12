<?php

declare(strict_types=1);

function attempt_login(string $login, string $senha, array $allowedTypes = []): bool
{
    $login = trim($login);
    $documento = only_digits($login);
    $usarDocumento = $documento !== '' && usuario_documento_tipo($documento) !== null;

    if (!filter_var($login, FILTER_VALIDATE_EMAIL) && !$usarDocumento) {
        return false;
    }

    $stmt = db()->prepare(
        "SELECT u.*, e.nome AS empresa_nome, e.status AS empresa_status
         FROM usuarios u
         LEFT JOIN empresas e ON e.id = u.empresa_id
         WHERE " . ($usarDocumento ? 'u.documento = :login' : 'u.email = :login') . "
         LIMIT 1"
    );
    $stmt->execute([':login' => $usarDocumento ? $documento : $login]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return false;
    }

    if ((int) $usuario['ativo'] !== 1) {
        return false;
    }

    if (!password_verify($senha, (string) $usuario['senha'])) {
        return false;
    }

    if ($allowedTypes !== [] && !in_array((string) $usuario['tipo'], $allowedTypes, true)) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'empresa_id' => $usuario['empresa_id'] !== null ? (int) $usuario['empresa_id'] : null,
        'empresa_nome' => $usuario['empresa_nome'] ?? null,
        'empresa_status' => $usuario['empresa_status'] ?? null,
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'documento' => $usuario['documento'] ?? null,
        'documento_tipo' => $usuario['documento_tipo'] ?? null,
        'tipo' => $usuario['tipo'],
    ];

    db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id')
        ->execute([':id' => (int) $usuario['id']]);

    return true;
}

function logout_user(): never
{
    $redirectPath = (($_SESSION['usuario']['tipo'] ?? '') === 'platform_admin')
        ? '/admin/login.php'
        : '/login.php';

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    redirect($redirectPath);
}
