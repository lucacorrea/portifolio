<?php
session_start();
require './conex.php';

/* ID do produto vindo da compra */
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
        /* ======= ESTILO PREMIUM DO CHECKOUT ======= */

        .checkout-box {
            background: #fff;
            padding: 40px 35px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.10);
        }

        .checkout-box h2 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .sub-text {
            font-size: 15px;
            color: #777;
            margin-top: -8px;
            margin-bottom: 25px;
        }

        .form-control {
            height: 52px;
            border-radius: 10px;
            border: 1px solid #dcdcdc;
            padding-left: 15px;
            font-size: 16px;
        }

        .form-control:focus {
            border-color: #FFC107;
            box-shadow: 0 0 4px rgba(255, 193, 7, 0.4);
        }

        .btn-amado-yellow {
            width: 100%;
            background: #FFD438;
            color: #3a3a3a;
            border: none;
            padding: 16px 0;
            border-radius: 10px;
            font-size: 20px;
            font-weight: 600;
            transition: 0.3s;
            cursor: pointer;
        }

        .btn-amado-yellow:hover {
            background: #F6FE0E;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 212, 56, 0.4);
        }

        .info-box {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.10);
        }

        .info-box h5 {
            font-size: 26px;
            margin-bottom: 20px;
        }

        .info-box ul {
            list-style: none;
            padding: 0;
        }

        .info-box ul li {
            font-size: 16px;
            margin-bottom: 10px;
            color: #444;
        }
    </style>

</head>

<body>

    <div class="main-content-wrapper d-flex clearfix">

        <!-- ===== MENU ===== -->
        <header class="header-area clearfix">
            <div class="logo">
                <a href="index.php"><img src="img/core-img/logo.png"></a>
            </div>
            <nav class="amado-nav">
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="shop.php">Compras</a></li>
                    <li class="active"><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>
        </header>

        <!-- ===== CONTEÚDO CENTRAL ===== -->
        <div class="cart-table-area section-padding-100">
            <div class="container-fluid">
                <div class="row">

                    <!-- FORMULÁRIO -->
                    <div class="col-12 col-lg-8">
                        <div class="checkout-box">

                            <h2>Crie sua conta</h2>
                            <p class="sub-text">Para concluir sua compra, preencha seus dados abaixo.</p>

                            <form action="salvar_cadastro.php" method="post">

                                <!-- Envia o produto que trouxe o cliente -->
                                <input type="hidden" name="produto_id" value="<?= $produto_id ?>">

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <input type="text" name="nome" class="form-control" placeholder="Nome completo" required>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                                    </div>

                                    <div class="col-12 mb-3">
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

                                <button type="submit" class="btn-amado-yellow">
                                    Cadastrar e Finalizar Compra
                                </button>

                            </form>

                        </div>
                    </div>

                    <!-- LADO DIREITO -->
                    <div class="col-12 col-lg-4">
                        <div class="info-box">
                            <h5>Por que cadastrar?</h5>

                            <ul>
                                <li>✔ Comprar facilmente</li>
                                <li>✔ Dados salvos automaticamente</li>
                                <li>✔ Segurança no processo</li>
                                <li>✔ Acesso rápido às próximas compras</li>
                            </ul>

                            <p style="font-size:15px;margin-top:10px;color:#666;">
                                Após cadastrar, sua compra continuará automaticamente.
                            </p>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

</body>

</html>