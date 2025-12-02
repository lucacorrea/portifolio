<?php
session_start();

/* PEGAR ID DO PRODUTO */
$produto_id = $_POST['produto_id'] ?? ($_GET['produto'] ?? 0);

if (!$produto_id) {
    die("Produto inválido.");
}

/* VERIFICAR SE EXISTE CLIENTE CADASTRADO NA SESSION */
if (
    !isset($_SESSION['cliente_id']) ||
    !isset($_SESSION['cliente_nome']) ||
    !isset($_SESSION['cliente_email'])
) {
    // CLIENTE AINDA NÃO FEZ O CHECKOUT → LEVAR PARA CADASTRO
    header("Location: checkout.php?produto=$produto_id");
    exit;
}

/* SE ESTIVER CADASTRADO → VOLTA PARA O PRODUTO COM MENSAGEM */
header("Location: product-details.php?id=$produto_id&sucesso=1");
exit;
