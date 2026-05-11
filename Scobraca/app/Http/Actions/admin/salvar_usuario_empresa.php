<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$empresaId = (int) ($_POST['empresa_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$documento = only_digits((string) ($_POST['documento'] ?? ''));
$documentoTipo = usuario_documento_tipo($documento);
$senha = (string) ($_POST['senha'] ?? '');
$tipo = $_POST['tipo'] ?? 'operador';

if ($empresaId <= 0 || $nome === '' || $email === '' || $documento === '' || $senha === '') {
    flash('error', 'Preencha todos os dados do usuário.');
    $redirectPath = $empresaId > 0 ? '/admin/empresa-usuario-cadastro.php?empresa_id=' . $empresaId : '/admin/empresas.php';
    redirect($redirectPath);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$documentoTipo || !documento_cpf_cnpj_valido($documento)) {
    flash('error', 'Informe e-mail e CPF/CNPJ válidos.');
    redirect('/admin/empresa-usuario-cadastro.php?empresa_id=' . $empresaId);
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

    flash('success', 'Usuário da empresa cadastrado com sucesso.');
} catch (Throwable $e) {
    error_log('[SALVAR USUÁRIO EMPRESA] ' . $e->getMessage());
    flash('error', 'Erro ao cadastrar usuário. Verifique se o e-mail ou CPF/CNPJ já existe.');
    redirect('/admin/empresa-usuario-cadastro.php?empresa_id=' . $empresaId);
}

redirect('/admin/empresas.php');
