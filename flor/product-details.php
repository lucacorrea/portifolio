<?php
require './conex.php';
session_start();

/* PEGAR ID */
$id = $_GET['id'] ?? 0;

$sql = "SELECT * FROM produtos WHERE id = $id LIMIT 1";
$res = $conex->query($sql);

if (!$res || $res->num_rows == 0) {
    die("Produto não encontrado.");
}

$p = $res->fetch_assoc();

/* Função imagem */
function imgPath($img)
{
    if (!$img) return "img/core-img/no-image.png";
    $img = basename(trim($img));
    return file_exists("uploads/" . $img) ? "uploads/" . $img : "img/core-img/no-image.png";
}

/* VERIFICAR SE COMPRA FOI FEITA */
$compra_ok = isset($_GET['sucesso']) && $_GET['sucesso'] == 1;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $p['nome'] ?></title>

    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">

<style>
.msg-sucesso {
    background: #28a745;
    color: #fff;
    padding: 18px;
    border-radius: 10px;
    font-size: 20px;
    text-align: center;
    margin-bottom: 25px;
}
</style>

</head>
<body>

<div class="main-content-wrapper d-flex clearfix">

    <header class="header-area clearfix">
        <div class="logo">
            <a href="index.php"><img src="img/core-img/logo.png"></a>
        </div>
        <nav class="amado-nav">
            <ul>
                <li><a href="index.php">Início</a></li>
                <li class="active"><a href="shop.php">Compras</a></li>
                <li><a href="checkout.php">Cadastro</a></li>
            </ul>
        </nav>
    </header>

    <div class="single-product-area section-padding-100 clearfix">
        <div class="container-fluid">

            <?php if ($compra_ok): ?>
                <div class="msg-sucesso">
                    Compra realizada com sucesso! ❤️ Obrigado pela preferência!
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12 col-lg-7">
                    <img src="<?= imgPath($p['imagem']) ?>" style="width:100%; border-radius:10px;">
                </div>

                <div class="col-12 col-lg-5">
                    <h3><?= $p['nome'] ?></h3>
                    <p class="product-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                    <p><?= $p['descricao'] ?></p>

                    <a href="checkout.php?produto=<?= $p['id'] ?>" class="btn amado-btn" style="width:100%;">
                        COMPRAR
                    </a>

                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>
