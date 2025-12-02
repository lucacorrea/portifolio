<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Cadastro | Floricultura</title>

    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">

<style>
/* FUNDO SUAVE */
body {
    background: #f7f7f7;
}

/* CONTAINER PRINCIPAL */
.checkout-container {
    max-width: 700px;
    margin: 60px auto;
    background: white;
    border-radius: 14px;
    padding: 40px 45px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.10);
}

/* TÍTULO */
.checkout-container h2 {
    text-align: center;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 35px;
    color: #333;
}

/* CAMPOS */
.form-control {
    border-radius: 10px;
    height: 50px;
    border: 1px solid #dcdcdc;
    padding-left: 15px;
    font-size: 15px;
    transition: .3s;
}

.form-control:focus {
    border-color: #ff5e57;
    box-shadow: 0 0 4px rgba(255,94,87,0.5);
}

/* BOTÃO */
.btn-cadastrar {
    width: 100%;
    padding: 15px;
    border-radius: 10px;
    background: #ff5e57;
    color: white !important;
    border: none;
    font-size: 20px;
    font-weight: bold;
    transition: .3s;
    margin-top: 15px;
}

.btn-cadastrar:hover {
    background: #ff3b30;
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(255,94,87,0.4);
}

/* LINKS */
.link-voltar {
    text-align: center;
    margin-top: 18px;
}

.link-voltar a {
    color: #555;
    font-size: 15px;
    text-decoration: none;
}

.link-voltar a:hover {
    text-decoration: underline;
}

</style>

</head>

<body>

    <div class="main-content-wrapper d-flex clearfix">

        <!-- MENU -->
        <header class="header-area clearfix">
            <div class="logo">
                <a href="index.php"><img src="img/core-img/logo.png" alt=""></a>
            </div>

            <nav class="amado-nav">
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="shop.php">Compras</a></li>
                    <li class="active"><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>
        </header>

        <!-- ÁREA DE CADASTRO -->
        <div class="checkout-container">
            <h2>Crie sua conta</h2>

            <form action="salvar_cadastro.php" method="post">

                <div class="row">

                    <div class="col-12 mb-3">
                        <input type="text" class="form-control" name="nome" placeholder="Seu nome completo" required>
                    </div>

                    <div class="col-12 mb-3">
                        <input type="email" class="form-control" name="email" placeholder="Email" required>
                    </div>

                    <div class="col-12 mb-3">
                        <input type="text" class="form-control" name="cidade" placeholder="Cidade" required>
                    </div>

                    <div class="col-12 mb-3">
                        <input type="text" class="form-control" name="endereco" placeholder="Endereço" required>
                    </div>

                    <div class="col-6 mb-3">
                        <input type="text" class="form-control" name="cep" placeholder="CEP" required>
                    </div>

                    <div class="col-6 mb-3">
                        <input type="text" class="form-control" name="telefone" placeholder="Telefone" required>
                    </div>

                </div>

                <button type="submit" class="btn-cadastrar">Cadastrar</button>

            </form>

            <div class="link-voltar">
                <a href="shop.php">← Voltar às compras</a>
            </div>

        </div>

    </div>

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>
