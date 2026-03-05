<?php
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

csrf_validate_or_die();
$pdo = db();

$id = post_int('id', 0);
$chave = post_str('chave');
$valor = post_str('valor');
$desc = post_str('descricao');
$return_to = safe_return_to(post_str('return_to', url_here('../../../parametros.php')));

if ($chave === '') {
    flash_set('flash_err', 'Chave é obrigatória.');
    redirect($return_to);
}

try {
    if ($id > 0) {
        $st = $pdo->prepare("UPDATE parametros SET chave = ?, valor = ?, descricao = ? WHERE id = ?");
        $st->execute([$chave, $valor, $desc, $id]);
    } else {
        $st = $pdo->prepare("INSERT INTO parametros (chave, valor, descricao) VALUES (?, ?, ?)");
        $st->execute([$chave, $valor, $desc]);
    }
    flash_set('flash_ok', 'Parâmetro salvo com sucesso!');
} catch (PDOException $e) {
    flash_set('flash_err', 'Erro ao salvar: ' . $e->getMessage());
}

redirect($return_to);
?>
