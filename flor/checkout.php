<?php
session_start();
require './conex.php';

/* PEGAR ID DO PRODUTO QUE TROUXE O CLIENTE PARA O CHECKOUT */
$produto_id = $_GET['produto'] ?? 0;
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Cadastro | Floricultura</title>

    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">

    <style>
        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 1px solid #ddd;
            padding-left: 15px;
        }

        .form-control:focus {
            border-color: #ff4f4f;
            box-shadow: 0 0 4px rgba(255, 0, 0, 0.4);
        }

        .btn-amado {
            display: block;
            width: 100%;
            background: #FFD438FF !important;
            padding: 15px;
            font-size: 18px;
            border-radius: 10px;
            text-align: center;
            color: white;
            margin-top: 20px;
            transition: .3s;
            border: none;
        }

        .btn-amado:hover {
            background: #F6FE0EFF !important;
            letter-spacing: 1px;
        }

        .cart-summary {
            border-radius: 12px;
            padding: 25px;
        }
    </style>

</head>

<body>

    <!-- Search Wrapper -->
    <div class="search-wrapper section-padding-100">
        <div class="search-close">
            <i class="fa fa-close"></i>
        </div>

        <div class="container">
            <div class="search-content">
                <form action="#" method="get">
                    <input type="search" name="search" placeholder="Buscar...">
                    <button><img src="img/core-img/search.png"></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Wrapper -->
    <div class="main-content-wrapper d-flex clearfix">

        <!-- Mobile Menu -->
        <div class="mobile-nav">
            <div class="amado-navbar-brand">
                <a href="index.php"><img src="img/core-img/logo.png"></a>
            </div>
            <div class="amado-navbar-toggler"><span></span><span></span><span></span></div>
        </div>

        <!-- Sidebar -->
        <header class="header-area clearfix">
            <div class="nav-close"><i class="fa fa-close"></i></div>

            <div class="logo">
                <a href="index.php"><img src="img/core-img/logo.png"></a>
            </div>

            <nav class="amado-nav">
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="shop.php">Compras</a></li>
                    <li><a href="checkout.php" class="active">Cadastro</a></li>
                </ul>
            </nav>

            <div class="social-info d-flex justify-content-between">
                <a href="#"><i class="fa fa-instagram"></i></a>
                <a href="#"><i class="fa fa-facebook"></i></a>
            </div>

        </header>

        <!-- Conteúdo centro -->
        <div class="cart-table-area section-padding-100">
            <div class="container-fluid">

                <div class="row">

                    <!-- FORMULÁRIO -->
                    <div class="col-12 col-lg-8">
                        <div class="checkout_details_area mt-50 clearfix">

                            <div class="cart-title">
                                <h2>Criar Conta</h2>
                                <p style="font-size:14px;color:#777">Para concluir sua compra, crie rapidamente sua conta.</p>
                            </div>

                            <form action="salvar_cadastro.php" method="post">

                                <!-- Passa o ID do produto -->
                                <input type="hidden" name="produto_id" value="<?= $produto_id ?>">

                                <div class="row">

                                    <div class="col-md-12 mb-3">
                                        <input type="text" name="nome" class="form-control" placeholder="Digite seu nome completo" required>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <input type="text" name="cidade" class="form-control" placeholder="Cidade" required>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <input type="text" name="endereco" class="form-control" placeholder="Endereço completo" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <input type="text" name="cep" class="form-control" placeholder="CEP" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <input type="text" name="telefone" class="form-control" placeholder="Telefone" required>
                                    </div>

                                </div>

                                <button type="submit" class="btn-amado">Cadastrar e Finalizar Compra</button>

                            </form>

                        </div>
                    </div>

                    <!-- INFO LATERAL -->
                    <div class="col-12 col-lg-4">
                        <div class="cart-summary">

                            <h5>Por que cadastrar?</h5>
                            <ul class="summary-table">
                                <li><span>✔ Cadastro:</span> <span>Gratuito</span></li>
                                <li><span>✔ Permite:</span> <span>Realizar compras</span></li>
                                <li><span>✔ Segurança:</span> <span>Seus dados protegidos</span></li>
                            </ul>

                            <p style="margin-top:15px; font-size:15px; color:#666">
                                Depois de cadastrar, você será automaticamente redirecionado para finalizar sua compra.
                            </p>

                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>
</html>
