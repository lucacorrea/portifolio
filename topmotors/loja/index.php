<?php
require "conexao.php";

// Buscar últimos 6 produtos
$sql = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 6");
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

    <?php include "topo.php"; ?>

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

                <?php while ($p = $sql->fetch_assoc()) { ?>
                    <div class="col-xl-4 col-md-4">
                        <div class="single_offers">

                            <div class="about_thumb">
                                <img src="uploads/<?php echo $p['imagem']; ?>" alt="">
                            </div>

                            <h3><?php echo $p['nome']; ?></h3>

                            <ul>
                                <li><b>Preço:</b> R$ <?php echo number_format($p['preco'], 2, ",", "."); ?></li>
                                <li><b>Categoria:</b> <?php echo $p['categoria']; ?></li>
                                <li><b>Estoque:</b> <?php echo $p['quantidade']; ?> unidade(s)</li>
                            </ul>

                            <a href="visualizar.php" class="book_now bg-danger text-white">Ver Todos</a>
                        </div>
                    </div>
                <?php } ?>

            </div>

        </div>
    </div>

    <?php include "footer.php"; ?>

</body>

</html>