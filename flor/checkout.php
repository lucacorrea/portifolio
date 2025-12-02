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
</head>
<body>

<div class="main-content-wrapper d-flex clearfix">

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

    <div class="cart-table-area section-padding-100">
        <div class="container-fluid">
            <div class="row">

                <!-- FORMULÁRIO -->
                <div class="col-12 col-lg-8">
                    <h2>Criar Conta</h2>

                    <form action="salvar_cadastro.php" method="post">

                        <!-- envia o produto -->
                        <input type="hidden" name="produto_id" value="<?= $produto_id ?>">

                        <input type="text" name="nome" class="form-control" placeholder="Nome Completo" required><br>
                        <input type="email" name="email" class="form-control" placeholder="Email" required><br>
                        <input type="text" name="cidade" class="form-control" placeholder="Cidade" required><br>
                        <input type="text" name="endereco" class="form-control" placeholder="Endereço" required><br>
                        <input type="text" name="cep" class="form-control" placeholder="CEP" required><br>
                        <input type="text" name="telefone" class="form-control" placeholder="Telefone" required><br>

                        <button type="submit" class="btn amado-btn" style="width:100%;">
                            Cadastrar e Finalizar Compra
                        </button>

                    </form>
                </div>

                <!-- INFORMAÇÕES -->
                <div class="col-12 col-lg-4">
                    <div class="cart-summary">
                        <h5>Por que criar conta?</h5>
                        <ul>
                            <li>✔ Comprar rapidamente</li>
                            <li>✔ Dados salvos</li>
                            <li>✔ Maior segurança</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

</body>
</html>
