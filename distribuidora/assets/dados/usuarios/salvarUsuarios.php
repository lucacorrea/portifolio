<?php
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

csrf_validate_or_die();
$pdo = db();

$id = post_int('id', 0);
$nome = post_str('nome');
$email = post_str('email');
$senha = post_str('senha');
$perfil = post_str('perfil', 'VENDEDOR');
$status = post_str('status', 'ATIVO');
$return_to = safe_return_to(post_str('return_to', url_here('../../../usuarios.php')));

if ($nome === '' || $email === '') {
    flash_set('flash_err', 'Nome e Email são obrigatórios.');
    redirect($return_to);
}

try {
    if ($id > 0) {
        // Update
        if ($senha !== '') {
            $sql = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, perfil = ?, status = ? WHERE id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil, $status, $id]);
        } else {
            $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, status = ? WHERE id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$nome, $email, $perfil, $status, $id]);
        }
        flash_set('flash_ok', 'Usuário atualizado com sucesso!');
    } else {
        // Insert
        if ($senha === '') {
            flash_set('flash_err', 'Senha é obrigatória para novos usuários.');
            redirect($return_to);
        }
        $sql = "INSERT INTO usuarios (nome, email, senha, perfil, status) VALUES (?, ?, ?, ?, ?)";
        $st = $pdo->prepare($sql);
        $st->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil, $status]);
        flash_set('flash_ok', 'Usuário criado com sucesso!');
    }
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        flash_set('flash_err', 'Este email já está em uso.');
    } else {
        flash_set('flash_err', 'Erro ao salvar: ' . $e->getMessage());
    }
}

redirect($return_to);
?>
