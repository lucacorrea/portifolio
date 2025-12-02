<?php
session_start();

$produto_id = $_POST['produto_id'] ?? 0;

/* Se não tiver cadastrado → manda para checkout */
if (!isset($_SESSION['cliente_id'])) {
    header("Location: checkout.php");
    exit;
}

/* Se estiver cadastrado → volta com mensagem */
header("Location: product-details.php?id=$produto_id&sucesso=1");
exit;
