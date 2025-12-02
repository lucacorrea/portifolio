<?php
require 'conex.php';

// BUSCAR os 6 produtos mais recentes
$sql = "SELECT * FROM produtos ORDER BY id DESC LIMIT 6";
$produtos = $conex->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Title  -->
    <title>Floricultura</title>

    <!-- Favicon  -->
    <link rel="icon" href="img/core-img/favicon.ico">

    <!-- Core Style CSS -->
    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="estilo.css">

</head>

<body>

    <!-- Search Wrapper Area Start -->
    <div class="search-wrapper section-padding-100">
        <div class="search-close"><i class="fa fa-close" aria-hidden="true"></i></div>
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="search-content">
                        <form action="#" method="get">
                            <input type="search" name="search" id="search" placeholder="Digite algo...">
                            <button type="submit"><img src="img/core-img/search.png" alt=""></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Search Wrapper Area End -->

    <!-- ##### Main Content Wrapper Start ##### -->
    <div class="main-content-wrapper d-flex clearfix">

        <!-- Mobile Nav -->
        <div class="mobile-nav">
            <div class="amado-navbar-brand">
                <a href="index.php"><img src="img/core-img/logo.png" alt=""></a>
            </div>
            <div class="amado-navbar-toggler"><span></span><span></span><span></span></div>
        </div>

        <!-- Header Area Start -->
        <header class="header-area clearfix">
            <div class="nav-close"><i class="fa fa-close" aria-hidden="true"></i></div>

            <div class="logo">
                <a href="index.php"><img src="img/floricultura.png" alt=""></a>
            </div>

            <nav class="amado-nav">
                <ul>
                    <li class="active"><a href="index.php">Início</a></li>
                    <li><a href="shop.php">Compras</a></li>
                    <li><a href="product-details.php">Produtos</a></li>
                    <li><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>

            <div class="social-info d-flex justify-content-between">
                <a href="./adm/index.php"><i class="fa fa-user"></i></a>
                <a href="#"><i class="fa fa-instagram"></i></a>
                <a href="#"><i class="fa fa-facebook"></i></a>
                <a href="#"><i class="fa fa-twitter"></i></a>
            </div>
        </header>
        <!-- Header Area End -->

        <!-- ############ PRODUTOS DINÂMICOS ############ -->

        <div class="products-catagories-area clearfix">
            <div class="amado-pro-catagory clearfix">

                <?php if ($produtos->num_rows > 0): ?>
                    <?php while ($p = $produtos->fetch_assoc()): ?>

                        <div class="single-products-catagory clearfix">
                            <a href="product-details.php?id=<?php echo $p['id']; ?>">
                                
                                <!-- IMAGEM -->
                                <img src="<?php echo $p['imagem']; ?>" 
                                     alt="<?php echo htmlspecialchars($p['nome']); ?>"
                                     style="height: 350px; width:100%; object-fit: cover;">

                                <!-- HOVER -->
                                <div class="hover-content">
                                    <div class="line"></div>
                                    <p>R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?></p>
                                    <h4><?php echo htmlspecialchars($p['nome']); ?></h4>
                                </div>

                            </a>
                        </div>

                    <?php endwhile; ?>
                <?php else: ?>

                    <div class="col-12 text-center mt-5">
                        <p class="text-muted">Nenhum produto cadastrado ainda.</p>
                    </div>

                <?php endif; ?>

            </div>
        </div>
        <!-- Product Categories Area End -->

    </div>
    <!-- ##### Main Content Wrapper End ##### -->

    <!-- ################## NEWSLETTER ################## -->
    <section class="newsletter-area section-padding-100-0">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-12 col-lg-6 col-xl-7">
                    <div class="newsletter-text mb-100">
                        <h2>Cadastre-se e ganhe <span>Desconto Exclusivo</span></h2>
                        <p>Receba novidades, promoções e ofertas especiais direto no seu e‑mail.</p>
                    </div>
                </div>

                <div class="col-12 col-lg-6 col-xl-5">
                    <div class="newsletter-form mb-100 text-center text-lg-end">
                        <a href="checkout.php" class="btn amado-btn">Inscrever-se</a>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <!-- Newsletter End -->

    <!-- ##### Footer Area Start ##### -->
    <footer class="footer_area clearfix">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-12 col-lg-4">
                    <div class="single_widget_area">
                        <div class="footer-logo mr-50">
                            <a href="index.php"><img src="img/core-img/logo2.png" alt=""></a>
                        </div>
                        <p class="copywrite">
                            Copyright &
                            <script>document.write(new Date().getFullYear());</script>
                            Todos os direitos reservados
                        </p>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="single_widget_area">
                        <div class="footer_menu">
                            <nav class="navbar navbar-expand-lg justify-content-end">
                                <button class="navbar-toggler" type="button" data-toggle="collapse"
                                    data-target="#footerNavContent"><i class="fa fa-bars"></i></button>

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
    <!-- ##### Footer Area End ##### -->

    <!-- Scripts -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>
