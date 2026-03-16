<?php
include 'conexao.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("DELETE FROM membros WHERE id = ?");
$stmt->execute([$id]);

header('Location: listar.php');
exit;
?>
