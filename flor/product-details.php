<?php
require './conex.php';

/* ================================
   PEGAR ID DO PRODUTO
================================ */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Produto inválido.");
}

/* ================================
   BUSCAR PRODUTO NO BANCO
================================ */
$sql = "SELECT id, nome, preco, imagem, descricao, categoria 
        FROM produtos 
        WHERE id = $id LIMIT 1";

$res = $conex->query($sql);

if (!$res || $res->num_rows == 0) {
    die("Produto não encontrado.");
}

$produto = $res->fetch_assoc();

/* ================================
   Função para validar imagem
================================ */
function imgPath($img)
{
    if (!$img) return "img/core-img/no-image.png";
    $img = basename(trim($img));
    $path = "uploads/" . $img;
    return file_exists($path) ? $path : "img/core-img/no-image.png";
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">

    <title><?= $produto['nome'] ?> | pesoflor</title>

    <link rel="icon" href="img/core-img/favicon.ico">
    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- Search Wrapper -->
    <div class="search-wrapper section-padding-100">
        <div class="search-close"><i class="fa fa-close"></i></div>
        <div class="container">
            <input type="search" placeholder="Buscar...">
        </div>
    </div>

    <div class="main-content-wrapper d-flex clearfix">

        <!-- Mobile Nav -->
        <div class="mobile-nav">
            <div class="amado-navbar-brand">
                <a href="index.php"><img src="img/core-img/logo.png" alt=""></a>
            </div>
            <div class="amado-navbar-toggler"><span></span><span></span><span></span></div>
        </div>

        <!-- Sidebar -->
        <header class="header-area clearfix">
            <div class="nav-close"><i class="fa fa-close"></i></div>

            <div class="logo">
                <a href="index.php"><img src="img/core-img/logo.png" alt=""></a>
            </div>

            <nav class="amado-nav">
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="shop.php">Compras</a></li>
                    <li class="active"><a href="#">Produto</a></li>
                    <li><a href="cart.php">Carrinho</a></li>
                    <li><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>

        </header>

        <!-- Product Details -->
        <div class="single-product-area section-padding-100 clearfix">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mt-50">
                                <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                                <li class="breadcrumb-item"><a href="shop.php">Compras</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?= $produto['nome'] ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <div class="row">
                    <!-- IMAGEM -->
                    <div class="col-12 col-lg-7">
                        <div class="single_product_thumb">
                            <div id="product_details_slider" class="carousel slide" data-ride="carousel">

                                <!-- INDICADORES -->
                                <ol class="carousel-indicators">
                                    <li class="active" data-target="#product_details_slider" data-slide-to="0"
                                        style="background-image: url('<?= imgPath($produto['imagem']) ?>');"></li>
                                </ol>

                                <!-- SLIDER -->
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <a class="gallery_img" href="<?= imgPath($produto['imagem']) ?>">
                                            <img class="d-block w-100" src="<?= imgPath($produto['imagem']) ?>" alt="">
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- DESCRIÇÃO -->
                    <div class="col-12 col-lg-5">
                        <div class="single_product_desc">

                            <div class="product-meta-data">
                                <div class="line"></div>

                                <p class="product-price">
                                    R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                                </p>

                                <h6><?= $produto['nome'] ?></h6>

                                <p class="avaibility">
                                    <i class="fa fa-circle"></i> Em estoque
                                </p>
                            </div>

                            <div class="short_overview my-5">
                                <p><?= $produto['descricao'] ?? "Sem descrição disponível." ?></p>
                            </div>

                            <!-- Botão adicionar ao carrinho -->
                            <form class="cart clearfix" method="POST" action="cart_add.php">
                                <input type="hidden" name="id" value="<?= $produto['id'] ?>">

                                <div class="cart-btn d-flex mb-50">
                                    <p>Qtd</p>
                                    <div class="quantity">
                                        <span class="qty-minus"><i class="fa fa-caret-down"></i></span>
                                        <input type="number" class="qty-text" name="quantidade" value="1" min="1">
                                        <span class="qty-plus"><i class="fa fa-caret-up"></i></span>
                                    </div>
                                </div>

                                <button type="submit" class="btn amado-btn">Adicionar ao carrinho</button>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Newsletter -->
    <section class="newsletter-area section-padding-100-0">
        <div class="container">
            <h2>Cadastre-se para receber promoções</h2>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer_area clearfix">
        <div class="container">
            <p class="copywrite">© <?= date('Y') ?> todos direitos reservados</p>
        </div>
    </footer>

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>
