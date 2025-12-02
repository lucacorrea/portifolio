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

/* MENSAGEM DE SUCESSO */
.msg-sucesso {
    background: linear-gradient(135deg, #34c759, #28a745);
    color: #fff;
    padding: 18px 25px;
    text-align: center;
    font-size: 22px;
    border-radius: 10px;
    margin: 15px auto 30px auto;
    max-width: 700px;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    animation: aparece .6s ease-out forwards;
}
@keyframes aparece {
    from {opacity:0; transform:translateY(-20px);}
    to   {opacity:1; transform:translateY(0);}
}

/* IMAGEM DO PRODUTO */
.produto-imagem img {
    width: 100%;
    border-radius: 12px;
    transition: 0.3s ease-in-out;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.produto-imagem img:hover {
    transform: scale(1.02);
}

/* BLOCO DE DESCRIÇÃO */
.bloco-detalhes {
    padding: 25px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.13);
}

/* TÍTULO */
.bloco-detalhes h3 {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}

/* PREÇO */
.bloco-detalhes .product-price {
    font-size: 28px !important;
    color: #28a745;
    font-weight: bold;
    margin-bottom: 20px;
}

/* BOTÃO COMPRAR */
.bloco-detalhes .btn-comprar {
    width: 100%;
    font-size: 22px;
    padding: 15px;
    background: #FCD309FF;
    border-radius: 10px;
    color: white !important;
    text-align: center;
    border: none;
    transition: .3s;
}
.bloco-detalhes .btn-comprar:hover {
    background: #D7DA01FF !important;
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(255,94,87,0.4);
}

/* DESCRIÇÃO */
.short_overview p {
    font-size: 17px;
    color: #555;
    line-height: 1.6;
}

</style>

</head>

<body>

<div class="main-content-wrapper d-flex clearfix">

    <!-- MENU -->
    <header class="header-area clearfix">
        <div class="logo">
            <a href="index.php"><img src="img/core-img/logo.png" alt=""></a>
        </div>

        <nav class="amado-nav">
            <ul>
                <li><a href="index.php">Início</a></li>
                <li class="active"><a href="shop.php">Compras</a></li>
                <li><a href="checkout.php">Cadastro</a></li>
            </ul>
        </nav>
    </header>

    <!-- DETALHES -->
    <div class="single-product-area section-padding-100 clearfix">
        <div class="container-fluid">

            <?php if ($compra_ok): ?>
                <div class="msg-sucesso">
                    Compra realizada com sucesso! ❤️ Obrigado pela preferência!
                </div>
            <?php endif; ?>

            <div class="row">

                <!-- IMAGEM -->
                <div class="col-12 col-lg-7 produto-imagem">
                    <img src="<?= imgPath($p['imagem']) ?>" alt="">
                </div>

                <!-- DESCRIÇÃO -->
                <div class="col-12 col-lg-5">
                    <div class="bloco-detalhes">

                        <div class="product-meta-data">
                            <p class="product-price">
                                R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                            </p>

                            <h3><?= $p['nome'] ?></h3>
                        </div>

                        <div class="short_overview my-4">
                            <p><?= $p['descricao'] ?></p>
                        </div>

                        <!-- BOTÃO COMPRAR (AGORA LEVA AO CHECKOUT COM O PRODUTO) -->
                        <a href="checkout.php?produto=<?= $p['id'] ?>" class="btn-comprar">
                            COMPRAR
                        </a>

                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

</body>
</html>
