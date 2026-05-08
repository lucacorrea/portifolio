<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
    flash('error', 'Informe nome, e-mail válido e senha com pelo menos 6 caracteres.');
    redirect('/admin/usuarios-plataforma-cadastro.php');
}

try {
    $stmt = db()->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, senha, tipo, ativo, criado_em)
         VALUES (NULL, :nome, :email, :senha, 'platform_admin', 1, NOW())"
    );
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
    ]);

    flash('success', 'Administrador cadastrado com sucesso.');
    redirect('/admin/usuarios-plataforma.php');
} catch (Throwable $e) {
    error_log('[SALVAR USUÁRIO PLATAFORMA] ' . $e->getMessage());
    flash('error', 'Não foi possível cadastrar o administrador. Verifique se o e-mail já existe.');
    redirect('/admin/usuarios-plataforma-cadastro.php');
}
