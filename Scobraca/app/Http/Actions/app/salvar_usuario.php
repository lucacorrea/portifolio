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
$senha = (string) ($_POST['senha'] ?? '');
$tipo = $_POST['tipo'] ?? 'operador';

if (!$empresaId || $nome === '' || $email === '' || $senha === '') {
    flash('error', 'Preencha todos os dados.');
    redirect('/app/usuarios.php');
}

if (!in_array($tipo, ['empresa_admin', 'operador'], true)) {
    $tipo = 'operador';
}

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

flash('success', 'Usuário cadastrado com sucesso.');
redirect('/app/usuarios.php');
