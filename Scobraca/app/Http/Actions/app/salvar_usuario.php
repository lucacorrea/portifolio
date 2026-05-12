<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

if (($_SESSION['usuario']['tipo'] ?? '') !== 'empresa_admin') {
    http_response_code(403);
    exit('Somente o administrador da empresa pode criar usuários.');
}

$empresaId = current_empresa_id();
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$documento = only_digits((string) ($_POST['documento'] ?? ''));
$documentoTipo = usuario_documento_tipo($documento);
$senha = (string) ($_POST['senha'] ?? '');
$tipo = $_POST['tipo'] ?? 'operador';

if (!$empresaId || $nome === '' || $email === '' || $documento === '' || $senha === '') {
    flash('error', 'Preencha todos os dados.');
    redirect('/app/usuarios-cadastro.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$documentoTipo || !documento_cpf_cnpj_valido($documento)) {
    flash('error', 'Informe e-mail e CPF/CNPJ válidos.');
    redirect('/app/usuarios-cadastro.php');
}

if (!in_array($tipo, ['empresa_admin', 'operador'], true)) {
    $tipo = 'operador';
}

try {
    $stmt = db()->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, documento, documento_tipo, senha, tipo, ativo, criado_em)
         VALUES (:empresa_id, :nome, :email, :documento, :documento_tipo, :senha, :tipo, 1, NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':nome' => $nome,
        ':email' => $email,
        ':documento' => $documento,
        ':documento_tipo' => $documentoTipo,
        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
        ':tipo' => $tipo,
    ]);

    flash('success', 'Usuário cadastrado com sucesso.');
} catch (Throwable $e) {
    error_log('[SALVAR USUÁRIO APP] ' . $e->getMessage());
    flash('error', 'Não foi possível cadastrar o usuário. Verifique se o e-mail ou CPF/CNPJ já existe.');
    redirect('/app/usuarios-cadastro.php');
}

redirect('/app/usuarios.php');
