<?php
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

csrf_validate_or_die();
$pdo = db();

$id = post_int('id', 0);
$return_to = safe_return_to(post_str('return_to', url_here('../../../usuarios.php')));

if ($id <= 0) {
    flash_set('flash_err', 'ID inválido.');
    redirect($return_to);
}

try {
    $st = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $st->execute([$id]);
    flash_set('flash_ok', 'Usuário excluído com sucesso!');
} catch (PDOException $e) {
    flash_set('flash_err', 'Erro ao excluir: ' . $e->getMessage());
}

redirect($return_to);
?>
