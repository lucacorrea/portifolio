<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$empresaId = (int) ($_POST['empresa_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');
$tipo = $_POST['tipo'] ?? 'operador';

if ($empresaId <= 0 || $nome === '' || $email === '' || $senha === '') {
    flash('error', 'Preencha todos os dados do usuário.');
    redirect('/admin/empresas.php');
}

if (!in_array($tipo, ['empresa_admin', 'operador'], true)) {
    $tipo = 'operador';
}

try {
    $stmt = db()->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, senha, tipo, ativo, criado_em)
         VALUES (:empresa_id, :nome, :email, :senha, :tipo, 1, NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':nome' => $nome,
        ':email' => $email,
        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
        ':tipo' => $tipo,
    ]);

    flash('success', 'Usuário da empresa cadastrado com sucesso.');
} catch (Throwable $e) {
    error_log('[SALVAR USUÁRIO EMPRESA] ' . $e->getMessage());
    flash('error', 'Erro ao cadastrar usuário. Verifique se o e-mail já existe.');
}

redirect('/admin/empresas.php');
