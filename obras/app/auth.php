<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function login_attempt(string $identifier, string $password): bool {
    $ident = strtolower(trim($identifier));
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $salt = audit_salt();
    $key = hash('sha256', $salt . "|login|" . $ip . '|' . $ident);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO login_limits (key_hash, attempts, window_start, blocked_until) VALUES (?,0,NOW(),NULL) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash)")->execute([$key]);
        $st = $pdo->prepare('SELECT attempts, window_start, blocked_until FROM login_limits WHERE key_hash = ? FOR UPDATE');
        $st->execute([$key]); $lim = $st->fetch();
        if ($lim['blocked_until'] !== null && strtotime($lim['blocked_until']) > time()) {
            $pdo->commit(); return false;
        }
        if ($lim['window_start'] && strtotime($lim['window_start']) < time() - 900) {
            $pdo->prepare('UPDATE login_limits SET attempts=0, window_start=NOW(), blocked_until=NULL WHERE key_hash=?')->execute([$key]);
            $lim['attempts'] = 0;
        }
        $st = $pdo->prepare('SELECT * FROM users WHERE username=? OR email=? LIMIT 1 FOR UPDATE');
        $st->execute([$ident, $ident]);
        $row = $st->fetch();
        $valid = $row && (int)$row['active'] === 1 && password_verify($password, $row['password_hash']);
        if ($valid && $row['locked_until'] && strtotime($row['locked_until']) > time()) $valid = false;
        if (!$valid) {
            $attempts = (int)$lim['attempts'] + 1;
            $until = $attempts >= 6 ? date('Y-m-d H:i:s', time() + 900) : null;
            $pdo->prepare('UPDATE login_limits SET attempts=?, blocked_until=? WHERE key_hash=?')->execute([$attempts, $until, $key]);
            if ($row) $pdo->prepare('UPDATE users SET failed_attempts=failed_attempts+1, locked_until=IF(failed_attempts+1>=8, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until) WHERE id=?')->execute([$row['id']]);
            $pdo->commit(); return false;
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>3,'threads'=>2])) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]),$row['id']]);
        }
        $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')->execute([$row['id']]);
        $pdo->prepare('DELETE FROM login_limits WHERE key_hash=?')->execute([$key]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int)$row['id'], 'csrf' => bin2hex(random_bytes(32)), 'auth_at' => time(), 'last_activity' => time()];
        audit_log('auth.login');
        return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function password_secure(string $pass): bool {
    return strlen($pass) >= 8 && strlen($pass) <= 128
        && preg_match('/[A-Z]/', $pass) && preg_match('/[a-z]/', $pass)
        && preg_match('/[0-9]/', $pass) && preg_match('/[^A-Za-z0-9]/', $pass);
}
function change_my_password(array $u, string $old, string $new, string $confirmation): void {
    if (!password_verify($old, $u['password_hash'])) throw new HttpError('Senha atual incorreta.');
    if (!hash_equals($new, $confirmation)) throw new HttpError('A confirmação da senha não confere.');
    if (!password_secure($new)) throw new HttpError('Use pelo menos 8 caracteres, com letras maiúsculas, minúsculas, número e símbolo.');
    if (hash_equals($old, $new)) throw new HttpError('A nova senha precisa ser diferente da anterior.');
    $hash = password_hash($new, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]);
    $st = db()->prepare('UPDATE users SET password_hash=?, must_change_password=0, password_changed_at=NOW(), failed_attempts=0, locked_until=NULL WHERE id=?');
    $st->execute([$hash, $u['id']]);
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    audit_log('auth.password.changed');
}

/**
 * Classifica problemas operacionais sem expor mensagens SQL ao navegador.
 * O código exato da exceção é mantido exclusivamente no log do PHP.
 */
function login_failure_code(Throwable $error): string {
    if ($error instanceof PDOException) {
        if (str_contains(strtolower($error->getMessage()), 'could not find driver')) return 'DB-DRIVER';
        $driver = (int)($error->errorInfo[1] ?? 0);
        return match ($driver) {
            1045, 1044 => 'DB-CREDENCIAIS',
            1049 => 'DB-NOME',
            2002, 2003, 2005, 2006, 2013 => 'DB-CONEXAO',
            1146 => 'DB-TABELA',
            1054 => 'DB-COLUNA',
            1048, 1062, 1452 => 'DB-DADOS',
            1205, 1213 => 'DB-TRANSACAO',
            default => 'DB-SQL',
        };
    }
    if ($error instanceof RuntimeException && str_contains($error->getMessage(), 'Driver pdo_mysql não instalado')) return 'DB-DRIVER';
    if ($error instanceof RuntimeException && str_contains($error->getMessage(), 'Configuração do banco de dados incompleta')) {
        return 'DB-CONFIGURACAO';
    }
    return 'LOGIN-SERVIDOR';
}
