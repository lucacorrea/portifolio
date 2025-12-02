<?php
session_start();

/* PEGAR ID DO PRODUTO */
$produto_id = $_POST['produto_id'] ?? ($_GET['produto'] ?? 0);

if (!$produto_id) {
    die("Produto inválido.");
}

/* SE CLIENTE NÃO ESTIVER LOGADO → VAI PARA CHECKOUT */
if (
    !isset($_SESSION['cliente_id']) ||
    !isset($_SESSION['cliente_nome']) ||
    !isset($_SESSION['cliente_email'])
) {
    header("Location: checkout.php?produto=$produto_id");
    exit;
}

/* SE JÁ ESTIVER LOGADO → APENAS MOSTRA A MENSAGEM */
header("Location: product-details.php?id=$produto_id&sucesso=1");
exit;
