<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/../../auth/auth.php';
if (function_exists('auth_require')) {
    auth_require('../../../index.php');
}

require_once __DIR__ . '/../../conexao.php';

$pdo = db();

/* =========================
   HELPERS LOCAIS
========================= */
function users_redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function users_flash_set(string $key, string $msg): void
{
    $_SESSION[$key] = $msg;
}

function users_post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $default;
    return is_numeric($value) ? (int)$value : $default;
}

function users_csrf_ok(): bool
{
    $posted = (string)($_POST['csrf'] ?? '');
    $sess   = (string)($_SESSION['_csrf'] ?? '');
    return $posted !== '' && $sess !== '' && hash_equals($sess, $posted);
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    users_redirect_to('../../../usuarios.php');
}

if (!users_csrf_ok()) {
    users_flash_set('flash_err', 'CSRF inválido. Atualize a página e tente novamente.');
    users_redirect_to('../../../usuarios.php');
}

$id = users_post_int('id', 0);
if ($id <= 0) {
    users_flash_set('flash_err', 'Usuário inválido.');
    users_redirect_to('../../../usuarios.php');
}

if ((int)($_SESSION['usuario_id'] ?? 0) === $id) {
    users_flash_set('flash_err', 'Você não pode excluir o usuário que está logado.');
    users_redirect_to('../../../usuarios.php');
}

try {
    $st = $pdo->prepare("DELETE FROM usuarios WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);

    users_flash_set('flash_ok', 'Usuário excluído com sucesso.');
    users_redirect_to('../../../usuarios.php');
} catch (Throwable $e) {
    users_flash_set('flash_err', 'Erro ao excluir usuário: ' . $e->getMessage());
    users_redirect_to('../../../usuarios.php');
}

?>