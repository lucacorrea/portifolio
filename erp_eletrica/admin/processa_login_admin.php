<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    expire_temp_users();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método inválido.');
    }

    if (!csrf_check($_POST['csrf'] ?? null)) {
        throw new RuntimeException('Sessão inválida. Atualize a página.');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $salvarBiometria = isset($_POST['salvar_biometria']);

    if ($email === '' || $senha === '') {
        throw new RuntimeException('Preencha e-mail e senha.');
    }

    $user = find_user_by_email($email);

    if (!$user || (int)$user['ativo'] !== 1) {
        throw new RuntimeException('Usuário inválido ou inativo.');
    }

    if (!is_admin_level((string)$user['nivel'])) {
        throw new RuntimeException('Somente admin ou master podem entrar aqui.');
    }

    if ((int)$user['is_temp_admin'] === 1) {
        throw new RuntimeException('Usuário temporário não pode acessar esta área de geração.');
    }

    if (!password_verify($senha, (string)$user['senha'])) {
        throw new RuntimeException('Senha inválida.');
    }

    update_last_login((int)$user['id']);
    login_user($user, true);

    $_SESSION['trigger_passkey_setup'] = $salvarBiometria && (int)($user['passkey_enabled'] ?? 0) !== 1;
    $_SESSION['just_logged_admin_email'] = (string)$user['email'];

    redirect('painel_admin.php');
} catch (Throwable $e) {
    flash('erro', $e->getMessage());
    redirect('login_admin.php');
}

?>