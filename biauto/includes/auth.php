<?php

declare(strict_types=1);

function usuario_logado(): ?array
{
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
}

function usuario_esta_logado(): bool
{
    return !empty($_SESSION['usuario']['id']);
}

function usuario_eh_admin(): bool
{
    return ($_SESSION['usuario']['nivel'] ?? '') === 'admin';
}

function exigir_login(PDO $pdo): void
{
    if (!usuario_esta_logado()) {
        redirect('login.php');
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $_SESSION['usuario']['id']]);
    $usuario = $stmt->fetch();

    if (!$usuario || (int) $usuario['ativo'] !== 1) {
        encerrar_sessao();
        redirect('login.php');
    }

    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'nivel' => $usuario['nivel'],
    ];
}

function iniciar_sessao_usuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'nivel' => $usuario['nivel'],
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function encerrar_sessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function ip_usuario(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_bloqueado(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_tentativas WHERE identificador = ? AND ip = ? AND sucesso = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$email, ip_usuario()]);
    return (int) $stmt->fetchColumn() >= 5;
}

function registrar_tentativa_login(PDO $pdo, string $email, bool $sucesso): void
{
    $stmt = $pdo->prepare('INSERT INTO login_tentativas (identificador, ip, sucesso, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $email,
        ip_usuario(),
        $sucesso ? 1 : 0,
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    if ($sucesso) {
        $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE identificador = ? AND ip = ? AND sucesso = 0');
        $stmt->execute([$email, ip_usuario()]);
    }
}
