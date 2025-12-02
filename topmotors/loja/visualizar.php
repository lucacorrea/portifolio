<?php 
require "conexao.php";
$produtos = $con->query("SELECT * FROM produtos ORDER BY id DESC");
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <title>Produtos | Top Motors</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/owl.carousel.min.css">
    <link rel="stylesheet" href="../css/magnific-popup.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/themify-icons.css">
    <link rel="stylesheet" href="../css/nice-select.css">
    <link rel="stylesheet" href="../css/flaticon.css">
    <link rel="stylesheet" href="../css/gijgo.css">
    <link rel="stylesheet" href="../css/animate.css">
    <link rel="stylesheet" href="../css/slicknav.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/stl.css">

    <style>
        .bradcam_area {
            background-image: url('../img/banner/slider_bg_2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 320px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .bradcam_area h3 {
            color: white;
            font-size: 48px;
            font-weight: bold;
            text-shadow: 0px 2px 6px rgba(0,0,0,0.6);
        }
        .single_offers img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
    </style>
</head>

<body>

    <!-- HEADER MENU -->
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">

                        <div class="col-xl-5 col-lg-6">
                            <div class="main-menu d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                      
                                        <li><a class="text-danger" href="#">Produtos</a></li>
                                        <li><a href="adicionar.php">Adicionar Produto</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <div class="col-xl-2 col-lg-2">
                            <div class="logo-img"></div>
                        </div>

                        <div class="col-12">
                            <div class="mobile_menu d-block d-lg-none"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- HEADER END -->

    <!-- BANNER ÚNICO -->
    <div class="bradcam_area">
        <h3>Todos os Produtos</h3>
    </div>

    <!-- LISTA DE PRODUTOS -->
    <div class="offers_area mt-5">
        <div class="container">

            <div class="row">

                <?php while ($p = $produtos->fetch_assoc()) { ?>
                    <div class="col-xl-4 col-md-4">
                        <div class="single_offers">

                            <div class="about_thumb">
                                <img src="uploads/<?php echo $p['imagem']; ?>" alt="">
                            </div>

                            <h3><?php echo $p['nome']; ?></h3>

                            <ul>
                                <li><b>Preço:</b> R$ <?= number_format($p['preco'], 2, ',', '.') ?></li>
                                <li><b>Categoria:</b> <?= $p['categoria'] ?></li>
                                <li><b>Estoque:</b> <?= $p['quantidade'] ?></li>
                            </ul>

                            <a href="#" class="book_now bg-danger text-white">Ver Detalhes</a>
                        </div>
                    </div>
                <?php } ?>

            </div>

        </div>
    </div>

    <!-- FOOTER -->
    <?php include "../footer.php"; ?>

</body>
</html>
