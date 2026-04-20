<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Manaus');

require_once dirname(__DIR__) . '/../../src/App/Config/Database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('A variável $pdo não foi carregada a partir de conexao.php.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function app_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_is_post(): bool
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function app_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_normalize_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

function app_base_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function app_rp_id(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

    return $host;
}

function app_webauthn_available(): bool
{
    return is_file(dirname(__DIR__) . '/vendor/autoload.php');
}

function app_webauthn_instance(): lbuchs\WebAuthn\WebAuthn
{
    if (!app_webauthn_available()) {
        throw new RuntimeException('A biblioteca WebAuthn não foi instalada. Execute: composer require lbuchs/webauthn');
    }

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    return new lbuchs\WebAuthn\WebAuthn('ERP Elétrica', app_rp_id());
}

function app_get_challenge_binary(mixed $challenge): string
{
    if (is_object($challenge) && method_exists($challenge, 'getBinaryString')) {
        return (string)$challenge->getBinaryString();
    }

    return (string)$challenge;
}

function app_admin_levels(): array
{
    return ['admin', 'master'];
}

function app_temp_levels_all(): array
{
    return ['vendedor', 'tecnico', 'gerente', 'admin', 'master'];
}

function app_allowed_temp_levels(string $creatorLevel): array
{
    if ($creatorLevel === 'master') {
        return app_temp_levels_all();
    }

    return ['vendedor', 'tecnico', 'gerente', 'admin'];
}

function app_is_admin_level(?string $level): bool
{
    return in_array((string)$level, app_admin_levels(), true);
}

function app_find_admin_by_email(PDO $pdo, string $email): ?array
{
    $sql = "
        SELECT *
        FROM usuarios
        WHERE email = :email
          AND ativo = 1
          AND nivel IN ('admin', 'master')
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', app_normalize_email($email));
    $stmt->execute();

    $user = $stmt->fetch();
    return $user ?: null;
}

function app_password_matches(PDO $pdo, array $user, string $plainPassword): bool
{
    $stored = (string)($user['senha'] ?? '');

    if ($stored === '') {
        return false;
    }

    if (password_verify($plainPassword, $stored)) {
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id LIMIT 1');
            $stmt->execute([
                ':senha' => $newHash,
                ':id' => (int)$user['id'],
            ]);
        }

        return true;
    }

    if (hash_equals($stored, $plainPassword)) {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id LIMIT 1');
        $stmt->execute([
            ':senha' => $newHash,
            ':id' => (int)$user['id'],
        ]);

        return true;
    }

    return false;
}

function app_set_admin_session(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['admin_auth'] = [
        'id' => (int)$user['id'],
        'filial_id' => isset($user['filial_id']) ? (int)$user['filial_id'] : null,
        'nome' => (string)($user['nome'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'nivel' => (string)($user['nivel'] ?? ''),
        'avatar' => (string)($user['avatar'] ?? ''),
    ];
}

function app_current_admin(): ?array
{
    $admin = $_SESSION['admin_auth'] ?? null;

    return is_array($admin) ? $admin : null;
}

function app_require_admin(): array
{
    $admin = app_current_admin();

    if (!$admin || !app_is_admin_level($admin['nivel'] ?? null)) {
        header('Location: admin_login.php');
        exit;
    }

    return $admin;
}

function app_update_last_login(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE usuarios SET last_login = NOW() WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
}

function app_filial_label(?int $filialId): string
{
    if (!$filialId) {
        return 'Sem filial definida';
    }

    return 'Filial #' . $filialId;
}

function app_user_handle(int $userId): string
{
    return 'admin:' . $userId;
}

function app_passkey_exists(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM user_passkeys WHERE usuario_id = :usuario_id LIMIT 1');
    $stmt->execute([':usuario_id' => $userId]);

    return (bool)$stmt->fetchColumn();
}

function app_generate_temp_code(): string
{
    return 'TMP-' . strtoupper(bin2hex(random_bytes(3)));
}

function app_format_dt(?string $dateTime): string
{
    if (!$dateTime) {
        return '-';
    }

    try {
        return (new DateTimeImmutable($dateTime))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string)$dateTime;
    }
}

function app_remaining_minutes(?string $validUntil): int
{
    if (!$validUntil) {
        return 0;
    }

    try {
        $end = new DateTimeImmutable($validUntil);
        $now = new DateTimeImmutable('now');
        $diff = $end->getTimestamp() - $now->getTimestamp();

        return $diff > 0 ? (int)ceil($diff / 60) : 0;
    } catch (Throwable) {
        return 0;
    }
}
