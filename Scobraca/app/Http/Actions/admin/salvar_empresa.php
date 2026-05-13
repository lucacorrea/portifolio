<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$nome = trim($_POST['nome'] ?? '');
$cnpj = only_digits((string) ($_POST['cnpj'] ?? ''));
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$planoId = (int) ($_POST['plano_id'] ?? 0);
$status = $_POST['status'] ?? 'teste';
$usuarioNome = trim($_POST['usuario_nome'] ?? '');
$usuarioEmail = trim($_POST['usuario_email'] ?? '');
$usuarioDocumento = only_digits((string) ($_POST['usuario_documento'] ?? ''));
$usuarioDocumentoTipo = usuario_documento_tipo($usuarioDocumento);
$usuarioSenha = (string) ($_POST['usuario_senha'] ?? '');

if ($nome === '' || $email === '' || $usuarioNome === '' || $usuarioEmail === '' || $usuarioDocumento === '' || $usuarioSenha === '') {
    flash('error', 'Preencha os dados da empresa e do usuário principal.');
    redirect('/admin/empresas-cadastro.php');
}

if ($cnpj !== '' && !cnpj_valido($cnpj)) {
    flash('error', 'Informe um CNPJ válido para a empresa.');
    redirect('/admin/empresas-cadastro.php');
}

if (!filter_var($usuarioEmail, FILTER_VALIDATE_EMAIL) || !$usuarioDocumentoTipo || !documento_cpf_cnpj_valido($usuarioDocumento)) {
    flash('error', 'Informe e-mail e CPF/CNPJ válidos para o usuário principal.');
    redirect('/admin/empresas-cadastro.php');
}

$permitidos = ['teste', 'ativa', 'bloqueada', 'cancelada'];
if (!in_array($status, $permitidos, true)) {
    $status = 'teste';
}

$pdo = db();

if ($cnpj !== '') {
    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj, ''), '.', ''), '/', ''), '-', ''), ' ', '') = :cnpj LIMIT 1");
    $stmt->execute([':cnpj' => $cnpj]);

    if ($stmt->fetch()) {
        flash('error', 'Já existe uma empresa cadastrada com este CNPJ.');
        redirect('/admin/empresas-cadastro.php');
    }
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO empresas (plano_id, nome, cnpj, email, telefone, status, criado_em)
         VALUES (:plano_id, :nome, :cnpj, :email, :telefone, :status, NOW())"
    );
    $stmt->execute([
        ':plano_id' => $planoId ?: null,
        ':nome' => $nome,
        ':cnpj' => $cnpj ?: null,
        ':email' => $email,
        ':telefone' => $telefone ?: null,
        ':status' => $status,
    ]);

    $empresaId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, documento, documento_tipo, senha, tipo, ativo, criado_em)
         VALUES (:empresa_id, :nome, :email, :documento, :documento_tipo, :senha, 'empresa_admin', 1, NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':nome' => $usuarioNome,
        ':email' => $usuarioEmail,
        ':documento' => $usuarioDocumento,
        ':documento_tipo' => $usuarioDocumentoTipo,
        ':senha' => password_hash($usuarioSenha, PASSWORD_DEFAULT),
    ]);

    if ($planoId > 0) {
        $plano = $pdo->prepare('SELECT preco FROM planos WHERE id = :id LIMIT 1');
        $plano->execute([':id' => $planoId]);
        $planoDados = $plano->fetch();
        $valor = (float) ($planoDados['preco'] ?? 0);

        $stmt = $pdo->prepare(
            "INSERT INTO assinaturas (empresa_id, plano_id, status, valor, data_inicio, data_vencimento, criado_em)
             VALUES (:empresa_id, :plano_id, :status, :valor, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), NOW())"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':plano_id' => $planoId,
            ':status' => $status === 'ativa' ? 'ativa' : 'teste',
            ':valor' => $valor,
        ]);
    }

    $pdo->commit();
    flash('success', 'Empresa locatária e usuário principal cadastrados com sucesso.');
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[SALVAR EMPRESA] ' . $e->getMessage());
    flash('error', 'Não foi possível cadastrar a empresa. Verifique se o e-mail ou CPF/CNPJ do usuário já existe.');
    redirect('/admin/empresas-cadastro.php');
}

redirect('/admin/empresas.php');
