<?php
require './conex.php';

/* ================================
   BUSCAR ATÉ 6 PRODUTOS DO BANCO
   ================================ */
$sql = "SELECT id, nome, preco, imagem FROM produtos ORDER BY id DESC LIMIT 6";
$res = $conex->query($sql);

if (!$res) {
    die("Erro ao executar a consulta: " . $conex->error);
}

$produtos = [];

while ($row = $res->fetch_assoc()) {
    $produtos[] = $row;
}

/* ======================================================
   COMPLETAR ATÉ 6 PRODUTOS CASO A TABELA TENHA < 6 ITENS
   ====================================================== */
for ($i = 0; $i < 6; $i++) {
    if (!isset($produtos[$i])) {
        $produtos[$i] = [
            'id' => 0,
            'nome' => 'Produto Indisponível',
            'preco' => 0,
            'imagem' => ''
        ];
    }
}

/* ============================================
   FUNÇÃO COMPLEXA PARA VALIDAR IMAGENS
   - Remove ../ e caminhos sujos
   - Verifica no servidor
   - Retorna imagem válida
   ============================================ */
function imgPath($img)
{
    if (!$img) {
        return "img/core-img/no-image.png";
    }

    // remove qualquer caminho (segurança)
    $img = basename(trim($img));

    $caminho = "uploads/" . $img;

    // verifica se realmente existe no servidor
    if (file_exists($caminho) && is_file($caminho)) {
        return $caminho;
    } else {
        return "img/core-img/no-image.png";
    }
}
?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Floricultura</title>

    <link rel="icon" href="img/core-img/favicon.ico">

    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="estilo.css">

  
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
                            <input type="search" name="search" id="search" placeholder="Type your keyword...">
                            <button type="submit"><img src="img/core-img/search.png" alt=""></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
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
                <a href="index.php"><img src="./img/floricultura.png" alt=""></a>
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

     
      <div class="products-catagories-area clearfix">
    <div class="amado-pro-catagory clearfix">

        <!-- PRODUTO 1 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[0]['id'] ?>">
                <img src="<?= imgPath($produtos[0]['imagem']) ?>" alt="<?= $produtos[0]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[0]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[0]['nome'] ?></h4>
                </div>
            </a>
        </div>

        <!-- PRODUTO 2 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[1]['id'] ?>">
                <img src="<?= imgPath($produtos[1]['imagem']) ?>" alt="<?= $produtos[1]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[1]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[1]['nome'] ?></h4>
                </div>
            </a>
        </div>

        <!-- PRODUTO 3 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[2]['id'] ?>">
                <img src="<?= imgPath($produtos[2]['imagem']) ?>" alt="<?= $produtos[2]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[2]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[2]['nome'] ?></h4>
                </div>
            </a>
        </div>

        <!-- PRODUTO 4 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[3]['id'] ?>">
                <img src="<?= imgPath($produtos[3]['imagem']) ?>" alt="<?= $produtos[3]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[3]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[3]['nome'] ?></h4>
                </div>
            </a>
        </div>

        <!-- PRODUTO 5 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[4]['id'] ?>">
                <img src="<?= imgPath($produtos[4]['imagem']) ?>" alt="<?= $produtos[4]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[4]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[4]['nome'] ?></h4>
                </div>
            </a>
        </div>

        <!-- PRODUTO 6 -->
        <div class="single-products-catagory clearfix">
            <a href="product-details.php?id=<?= $produtos[5]['id'] ?>">
                <img src="<?= imgPath($produtos[5]['imagem']) ?>" alt="<?= $produtos[5]['nome'] ?>">
                <div class="hover-content">
                    <div class="line"></div>
                    <p>R$ <?= number_format($produtos[5]['preco'], 2, ',', '.') ?></p>
                    <h4><?= $produtos[5]['nome'] ?></h4>
                </div>
            </a>
        </div>

    </div>
</div>

        <!-- Fim dos 6 produtos -->

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

    <!-- Scripts -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

    

</body>

</html>