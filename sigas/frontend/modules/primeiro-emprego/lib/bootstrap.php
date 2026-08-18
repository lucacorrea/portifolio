<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function pe_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pe_db(): PDO
{
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $GLOBALS['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['pdo']->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $GLOBALS['pdo'];
    }

    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require dirname(__DIR__) . '/config/database.php';
    if (empty($config['name']) || empty($config['user'])) {
        throw new RuntimeException('Banco de dados do módulo não configurado.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function pe_csrf_token(): string
{
    if (empty($_SESSION['pe_csrf'])) {
        $_SESSION['pe_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['pe_csrf'];
}

function pe_csrf_field(): string
{
    return '<input type="hidden" name="pe_csrf" value="' . pe_h(pe_csrf_token()) . '">';
}

function pe_verify_csrf(): void
{
    $sent = isset($_POST['pe_csrf']) ? (string) $_POST['pe_csrf'] : '';
    if ($sent === '' || !hash_equals(pe_csrf_token(), $sent)) {
        throw new RuntimeException('Sessão expirada ou formulário inválido. Recarregue a página.');
    }
}

function pe_digits($value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?: '';
}

function pe_normalize_cpf($value, bool $padLeft = false): string
{
    $digits = pe_digits($value);
    if ($padLeft && strlen($digits) > 0 && strlen($digits) < 11) {
        $digits = str_pad($digits, 11, '0', STR_PAD_LEFT);
    }
    return $digits;
}

function pe_validate_cpf(string $cpf): bool
{
    $cpf = pe_digits($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
        }
        $digit = (10 * $sum) % 11;
        if ($digit === 10) {
            $digit = 0;
        }
        if ((int) $cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

function pe_nullable($value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function pe_date_or_null($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function pe_age(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    try {
        return (new DateTime($date))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

function pe_json_list($value): string
{
    if (!is_array($value)) {
        return '[]';
    }
    $clean = [];
    foreach ($value as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $clean[] = function_exists('mb_substr') ? mb_substr($item, 0, 120, 'UTF-8') : substr($item, 0, 120);
        }
    }
    return json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function pe_flash(string $type, string $message): void
{
    $_SESSION['pe_flash'] = ['type' => $type, 'message' => $message];
}

function pe_take_flash(): ?array
{
    if (empty($_SESSION['pe_flash']) || !is_array($_SESSION['pe_flash'])) {
        return null;
    }
    $flash = $_SESSION['pe_flash'];
    unset($_SESSION['pe_flash']);
    return $flash;
}

function pe_db_ready(): bool
{
    try {
        pe_db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function pe_db_notice(): string
{
    return '<div class="alert alert-warning mb-3"><strong>Banco não conectado.</strong> Configure <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> e <code>DB_PASS</code>, ou disponibilize um <code>$pdo</code> global no sistema principal. Depois execute <code>database/001_primeiro_emprego.sql</code>.</div>';
}
