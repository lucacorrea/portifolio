<?php
declare(strict_types=1);
/**
 * OBRAS360: diagnóstico local de login / MySQL.
 * Execute pelo terminal da hospedagem: php tools/diagnosticar_login.php
 * NÃO publique este arquivo na pasta public_html.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Indisponível.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$failures = [];
function check(string $name, bool $ok, string $detail = ''): void {
    global $failures;
    printf("[%s] %s%s\n", $ok ? 'OK' : 'FALHA', $name, $detail === '' ? '' : ' — ' . $detail);
    if (!$ok) $failures[] = $name;
}
function pdo_code(Throwable $e): string {
    if ($e instanceof PDOException) {
        $number = $e->errorInfo[1] ?? null;
        $state = $e->errorInfo[0] ?? $e->getCode();
        return 'SQLSTATE=' . (string)$state . ($number !== null ? ', erro=' . (string)$number : '');
    }
    return get_class($e);
}

echo "=== Diagnóstico de autenticação OBRAS360 ===\n";
echo "PHP: " . PHP_VERSION . "\n";
check('PHP 8.2 ou superior', PHP_VERSION_ID >= 80200);
check('Extensão PDO', extension_loaded('PDO'));
check('Extensão pdo_mysql', extension_loaded('pdo_mysql'));
check('Suporte Argon2id', defined('PASSWORD_ARGON2ID'));
check('Arquivo PHP privado obras360-private/conexao.php', obras_arquivo_conexao() !== null);
check('Configuração de banco', env_value('DB_NAME') !== '' && env_value('DB_USER') !== '' && env_value('DB_PASS') !== '' && !str_contains(env_value('DB_PASS'), 'COLE_SENHA'));

if (!extension_loaded('pdo_mysql') || env_value('DB_NAME') === '' || env_value('DB_USER') === '') {
    echo "\nCorrija as falhas acima e execute novamente.\n";
    exit(1);
}
try {
    $pdo = db();
    $pdo->query('SELECT 1')->fetchColumn();
    check('Conexão e autenticação do MySQL', true);
} catch (Throwable $e) {
    check('Conexão e autenticação do MySQL', false, pdo_code($e));
    echo "Confira os dados em obras360-private/conexao.php (não no .env).\n";
    exit(1);
}

$required = ['users', 'login_limits', 'sequences', 'records', 'milestones', 'stock_movements', 'protocol_forwards', 'attachments', 'audit_logs'];
try {
    $st = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
    $existing = array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($required, $existing));
    check('9 tabelas exigidas pelo sistema', count($missing) === 0, $missing ? 'Ausentes: ' . implode(', ', $missing) : 'todas presentes');
} catch (Throwable $e) {
    check('Consulta às tabelas', false, pdo_code($e));
    exit(1);
}

if (!in_array('users', $missing, true)) {
    try {
        $st = $pdo->prepare("SELECT id, role, active, must_change_password, password_hash, locked_until FROM users WHERE username = 'suporte' OR email = 'suporte@obras360.local' ORDER BY id LIMIT 1");
        $st->execute();
        $account = $st->fetch();
        check('Conta suporte instalada', (bool)$account);
        if ($account) {
            check('Perfil suporte', $account['role'] === 'suporte');
            check('Conta ativa', (int)$account['active'] === 1);
            check('Hash Argon2id', password_get_info($account['password_hash'])['algoName'] === 'argon2id');
            check('Conta não bloqueada', !$account['locked_until'] || strtotime($account['locked_until']) <= time());
            echo 'Troca de senha obrigatória: ' . ((int)$account['must_change_password'] === 1 ? 'SIM' : 'NÃO') . "\n";
        }
    } catch (Throwable $e) {
        check('Consulta de suporte', false, pdo_code($e));
    }
}

if (!in_array('login_limits', $missing, true) && !in_array('audit_logs', $missing, true)) {
    try {
        $pdo->beginTransaction();
        $key = hash('sha256', 'obras360-test-' . random_bytes(16));
        $st = $pdo->prepare('INSERT INTO login_limits (key_hash, attempts, window_start, blocked_until) VALUES (?,0,NOW(),NULL)');
        $st->execute([$key]);
        $st = $pdo->prepare("INSERT INTO audit_logs (actor_id, event, module, record_code, ip_hash, metadata) VALUES (NULL, 'system.diagnostic', NULL, NULL, NULL, NULL)");
        $st->execute();
        $pdo->rollBack();
        check('Permissão de gravação em login_limits e audit_logs', true);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        check('Permissão de gravação em login_limits e audit_logs', false, pdo_code($e));
    }
}

echo "\nResultado: " . (count($failures) === 0 ? 'PRONTO PARA TESTAR O LOGIN' : 'CORREÇÕES NECESSÁRIAS (' . count($failures) . ')') . "\n";
if ($failures) echo "Verifique database/01_estrutura_e_suporte.sql e o conexao.php privado.\n";
exit(count($failures) === 0 ? 0 : 1);
