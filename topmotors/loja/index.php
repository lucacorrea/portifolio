<?php
require "conexao.php";

// Total de produtos
$total = $con->query("SELECT COUNT(*) AS t FROM produtos")->fetch_assoc()['t'];

// Últimos produtos
$ultimos = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 4");
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Loja - Painel</title>
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

    <style>
        .custom-box {
            background: #fff;
            padding: 30px;
            margin-top: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, .1);
            text-align: center;
        }
        .card img {height: 180px; object-fit: cover;}
    </style>
</head>

<body>
    <!-- HEADER -->
    <?php include "topo.php"; ?>

    <section class="contact-section">
        <div class="container">

            <div class="custom-box">
                <h2>Sistema de Produtos</h2>
                <p>Gerencie seus produtos cadastrados.</p>

                <a href="adicionar.php" class="btn btn-success mt-3">+ Adicionar Produto</a>
                <a href="visualizar.php" class="btn btn-primary mt-3">Ver Todos</a>

                <hr>

                <h4>Total cadastrados: <b><?php echo $total; ?></b></h4>
            </div>


            <h3 class="mt-5 mb-3">Últimos produtos adicionados</h3>

            <div class="row">
                <?php while ($p = $ultimos->fetch_assoc()) { ?>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <img src="uploads/<?php echo $p['imagem']; ?>" class="card-img-top">
                        <div class="card-body">
                            <h5><?php echo $p['nome']; ?></h5>
                            <p>R$ <?php echo number_format($p['preco'], 2, ",", "."); ?></p>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

        </div>
    </section>

    <?php include "footer.php"; ?>

    <!-- JS -->
    <script src="js/vendor/jquery-1.12.4.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
