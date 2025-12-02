<?php
require './conex.php';

/* ================================
   BUSCAR TODOS OS PRODUTOS
================================ */
$sql = "SELECT id, nome, preco, imagem FROM produtos ORDER BY id DESC";
$res = $conex->query($sql);

if (!$res) {
    die("Erro ao consultar produtos: " . $conex->error);
}

$produtos = [];
while ($row = $res->fetch_assoc()) {
    $produtos[] = $row;
}

/* ================
   Função imagem
================ */
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
    <meta name="description" content="">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>pesoflor | Shop</title>

    <link rel="icon" href="img/core-img/favicon.ico">
    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- Search Wrapper -->
    <div class="search-wrapper section-padding-100">
        <div class="search-close"><i class="fa fa-close"></i></div>
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="search-content">
                        <form action="#" method="get">
                            <input type="search" name="search" placeholder="Buscar...">
                            <button type="submit"><img src="img/core-img/search.png" alt=""></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content-wrapper d-flex clearfix">

        <!-- Mobile -->
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
                    <li class="active"><a href="shop.php">Compras</a></li>
                    <li><a href="product-details.php">Produto</a></li>
                    <li><a href="cart.php">Carrinho</a></li>
                    <li><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>

            <div class="social-info d-flex justify-content-between">
                <a href="#"><i class="fa fa-instagram"></i></a>
                <a href="#"><i class="fa fa-facebook"></i></a>
            </div>
        </header>

        <!-- Sidebar Filtro -->
        <div class="shop_sidebar_area">
            <div class="widget catagory mb-50">
                <h6 class="widget-title mb-30">Categorias</h6>
                <div class="catagories-menu">
                    <ul>
                        <li class="active"><a href="#">Flores</a></li>
                        <li><a href="#">Buquês</a></li>
                        <li><a href="#">Cactos</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- PRODUTOS -->
        <div class="amado_product_area section-padding-100">
            <div class="container-fluid">
                <div class="row">

                    <?php foreach ($produtos as $p): ?>
                        <div class="col-12 col-sm-6 col-md-12 col-xl-6">
                            <div class="single-product-wrapper">

                                <!-- IMAGEM -->
                                <div class="product-img">
                                    <img src="<?= imgPath($p['imagem']) ?>" alt="<?= $p['nome'] ?>">
                                    <img class="hover-img" src="<?= imgPath($p['imagem']) ?>" alt="<?= $p['nome'] ?>">
                                </div>

                                <!-- DESCRIÇÃO -->
                                <div class="product-description d-flex align-items-center justify-content-between">
                                    <div class="product-meta-data">
                                        <div class="line"></div>
                                        <p class="product-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                                        <a href="product-details.php?id=<?= $p['id'] ?>">
                                            <h6><?= $p['nome'] ?></h6>
                                        </a>
                                    </div>

                                    <div class="ratings-cart text-right">
                                        <div class="ratings">
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                        </div>
                                        <div class="cart">
                                            <a href="cart.php" data-toggle="tooltip" title="Adicionar ao carrinho">
                                                <img src="img/core-img/cart.png" alt="">
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>

    </div>
    <!-- Newsletter -->
    <section class="newsletter-area section-padding-100-0">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-12 col-lg-6 col-xl-7">
                    <div class="newsletter-text mb-100">
                        <h2>Cadastre-se para receber promoções</h2>
                        <p>Receba novidades exclusivas.</p>
                    </div>
                </div>

                <div class="col-12 col-lg-6 col-xl-5">
                    <div class="newsletter-form mb-100">
                        <a href="checkout.php" class="btn amado-btn">Cadastrar-se</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer_area clearfix">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-12 col-lg-4">
                    <div class="single_widget_area">
                        <div class="footer-logo mr-50">
                            <a href="index.php"><img src="img/core-img/logo2.png" alt=""></a>
                        </div>
                        <p class="copywrite">© <script>
                                document.write(new Date().getFullYear());
                            </script> Todos os direitos reservados</p>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="single_widget_area">

                        <div class="footer_menu">
                            <nav class="navbar navbar-expand-lg justify-content-end">

                                <div class="collapse navbar-collapse" id="footerNavContent">
                                    <ul class="navbar-nav ml-auto">
                                        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                                        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                                        <li class="nav-item"><a class="nav-link" href="cart.php">Carrinho</a></li>
                                        <li class="nav-item"><a class="nav-link" href="checkout.php">Checkout</a></li>
                                    </ul>
                                </div>

                            </nav>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </footer>

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>