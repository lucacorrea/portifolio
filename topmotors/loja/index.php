<?php 
require "conexao.php";
$produtos = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 6");
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <title>Loja | Top Motors</title>
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
    <link rel="stylesheet" href="../css/stl.css">

    <style>
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
                                        <li><a class="text-danger" href="../index.php">Home</a></li>
                                 
                                        <li><a href="visualizar.php">Produtos</a></li>
                                        <li><a href="adicionar.php">Adicionar Produto</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <div class="col-xl-2 col-lg-2">
                            <div class="logo-img">
                                <!-- SUA LOGO AQUI -->
                            </div>
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

    <div class="offers_area mt-5">
        <div class="container">

            <div class="row">
                <div class="col-xl-12">
                    <div class="section_title text-center mb-100">
                        <span class="text-danger">Produtos Recentes</span>
                        <h3>Últimos Adicionados</h3>
                    </div>
                </div>
            </div>

            <div class="row">

                <?php while ($p = $produtos->fetch_assoc()) { ?>
                    <div class="col-xl-4 col-md-4">
                        <div class="single_offers">

                            <div class="about_thumb">
                                <img src="uploads/<?php echo $p['imagem']; ?>" alt="">
                            </div>

                            <h3><?php echo $p['nome']; ?></h3>

                            <ul>
                                <li><b>Preço:</b> R$ <?php echo number_format($p['preco'], 2, ",", "."); ?></li>
                                <li><b>Categoria:</b> <?php echo $p['categoria']; ?></li>
                                <li><b>Estoque:</b> <?php echo $p['quantidade']; ?> unidades</li>
                            </ul>

                            <a href="visualizar.php" class="book_now bg-danger text-white">Ver Todos</a>
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
