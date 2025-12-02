<?php
require "loja/conexao.php";

// 3 produtos para offers
$offers = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 3");

// 4 produtos para rooms
$rooms  = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 4");
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Top Motors - Motos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS TEMPLATE -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/magnific-popup.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/themify-icons.css">
    <link rel="stylesheet" href="css/nice-select.css">
    <link rel="stylesheet" href="css/flaticon.css">
    <link rel="stylesheet" href="css/gijgo.css">
    <link rel="stylesheet" href="css/animate.css">
    <link rel="stylesheet" href="css/slicknav.css">
    <link rel="stylesheet" href="css/stl.css">
</head>

<body>
<style>
    .breadcam_bg_1 {
        background-image: url(img/offers/motos.jpg);
        background-size: cover;
        background-position: center;
    }
    .room_thumb img, .about_thumb img {
        width: 100%;
        height: 240px;
        object-fit: cover;
    }
</style>

<!-- HEADER -->
<header>
    <div class="header-area ">
        <div id="sticky-header" class="main-header-area">
            <div class="container-fluid p-0">
                <div class="row align-items-center no-gutters">

                    <div class="col-xl-5 col-lg-6">
                        <div class="main-menu d-none d-lg-block">
                            <nav>
                                <ul id="navigation">
                                    <li><a href="index.php">Home</a></li>
                                    <li><a class="text-danger" href="motos.php">Motos</a></li>
                                    <li><a href="contact.php">Cadastro</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <div class="col-xl-2 col-lg-2"></div>

                    <div class="col-12">
                        <div class="mobile_menu d-block d-lg-none"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</header>

<!-- SLIDER -->
<div class="slider_area">
    <div class="slider_active owl-carousel">

        <div class="single_slider d-flex align-items-center justify-content-center slider_bg_1">
            <div class="container">
                <div class="row"><div class="col-xl-12"><div class="slider_text text-center">
                    <h3>Top Motors</h3><p>Encontre a moto ideal para você</p>
                </div></div></div>
            </div>
        </div>

        <div class="single_slider d-flex align-items-center justify-content-center slider_bg_2">
            <div class="container">
                <div class="row"><div class="col-xl-12"><div class="slider_text text-center">
                    <h3>Qualidade e Segurança</h3><p>As melhores motos com preço justo</p>
                </div></div></div>
            </div>
        </div>

    </div>
</div>

<!-- ABOUT -->
<div class="about_area">
    <div class="container">
        <div class="row">

            <div class="col-xl-5 col-lg-5">
                <div class="about_info">
                    <div class="section_title mb-20px">
                        <span>Sobre Nós</span>
                    </div>
                    <p>
                        Na <strong>Top Motors</strong> você encontra motos selecionadas com qualidade,
                        segurança e preço justo. Trabalhamos com transparência para ajudar você a fazer
                        o melhor negócio.
                    </p>
                </div>
            </div>

            <div class="col-xl-7 col-lg-7">
                <div class="about_thumb d-flex">
                    <div class="img_1"><img src="img/about/about_1.jpg"></div>
                    <div class="img_2"><img src="img/about/about_2.jpg"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============ 3 PRODUTOS (ESTILO OFFERS) ============ -->
<div class="offers_area">
    <div class="container">

        <div class="section_title text-center mb-100">
            <span class="text-danger">Modelos em Destaque</span>
            <h3>Novidades da Loja</h3>
        </div>

        <div class="row">

            <?php while($p = $offers->fetch_assoc()) { ?>
                <div class="col-xl-4 col-md-4">
                    <div class="single_offers">

                        <div class="about_thumb">
                            <img src="loja/uploads/<?= $p['imagem'] ?>">
                        </div>

                        <h3><?= $p['nome'] ?></h3>

                        <ul>
                            <li><b>Preço:</b> R$ <?= number_format($p['preco'], 2, ',', '.') ?></li>
                            <li><b>Categoria:</b> <?= $p['categoria'] ?></li>
                            <li><b>Estoque:</b> <?= $p['quantidade'] ?></li>
                        </ul>

                        <a href="loja/visualizar.php" class="book_now bg-danger text-white">Ver mais</a>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>

<!-- ============ 4 PRODUTOS (ESTILO ROOMS) ============ -->
<div class="features_room">
    <div class="container">

        <div class="section_title text-center mb-100">
            <span class="text-danger">Categorias</span>
            <h3>Escolha sua Moto</h3>
        </div>

    </div>

    <div class="rooms_here">

        <?php while($p = $rooms->fetch_assoc()) { ?>
            <div class="single_rooms">
                <div class="room_thumb">
                    <img src="loja/uploads/<?= $p['imagem'] ?>">
                    <div class="room_heading d-flex justify-content-between align-items-center">

                        <div class="room_heading_inner">
                            <span>R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                            <h3><?= $p['nome'] ?></h3>
                        </div>

                        <a href="loja/visualizar.php" class="line-button">Detalhes</a>
                    </div>
                </div>
            </div>
        <?php } ?>

    </div>
</div>

<!-- FOOTER -->
<?php include "footer.php"; ?>

<!-- JS -->
<script src="js/vendor/jquery-1.12.4.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/owl.carousel.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>
