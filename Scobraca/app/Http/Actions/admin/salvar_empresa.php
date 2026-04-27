<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$nome = trim($_POST['nome'] ?? '');
$cnpj = trim($_POST['cnpj'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$planoId = (int) ($_POST['plano_id'] ?? 0);
$status = $_POST['status'] ?? 'teste';
$usuarioNome = trim($_POST['usuario_nome'] ?? '');
$usuarioEmail = trim($_POST['usuario_email'] ?? '');
$usuarioSenha = (string) ($_POST['usuario_senha'] ?? '');

if ($nome === '' || $email === '' || $usuarioNome === '' || $usuarioEmail === '' || $usuarioSenha === '') {
    flash('error', 'Preencha os dados da empresa e do usuário principal.');
    redirect('/admin/empresas.php');
}

$permitidos = ['teste', 'ativa', 'bloqueada', 'cancelada'];
if (!in_array($status, $permitidos, true)) {
    $status = 'teste';
}

$pdo = db();
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
        "INSERT INTO usuarios (empresa_id, nome, email, senha, tipo, ativo, criado_em)
         VALUES (:empresa_id, :nome, :email, :senha, 'empresa_admin', 1, NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':nome' => $usuarioNome,
        ':email' => $usuarioEmail,
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
    flash('error', 'Não foi possível cadastrar a empresa. Verifique se o e-mail já existe.');
}

redirect('/admin/empresas.php');
