<?php
session_start();

/* PEGAR ID DO PRODUTO */
$produto_id = $_POST['produto_id'] ?? ($_GET['produto'] ?? 0);

if (!$produto_id) {
    die("Produto inválido.");
}

/* SE O CLIENTE NÃO ESTIVER CADASTRADO */
if (!isset($_SESSION['cliente_id'])) {
    header("Location: checkout.php?produto=$produto_id");
    exit;
}

/* SE O CLIENTE ESTIVER CADASTRADO → VOLTA PARA O DETALHE COM MENSAGEM */
header("Location: product-details.php?id=$produto_id&sucesso=1");
exit;
