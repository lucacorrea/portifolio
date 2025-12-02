<?php
require "conexao.php";

// =====================================
// PROCESSAR ENVIO DO FORMULÁRIO
// =====================================
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome       = $_POST['nome'] ?? '';
    $preco      = $_POST['preco'] ?? '';
    $categoria  = $_POST['categoria'] ?? '';
    $descricao  = $_POST['descricao'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';

    // ---------------------------
    // UPLOAD DA IMAGEM
    // ---------------------------
    $nomeImagem = "";

    if (!empty($_FILES['imagem']['name'])) {
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $nomeImagem = "produto_" . time() . "." . $ext;

        move_uploaded_file($_FILES['imagem']['tmp_name'], "uploads/" . $nomeImagem);
    }

    // ---------------------------
    // SALVAR NO BANCO
    // ---------------------------
    $stmt = $con->prepare("INSERT INTO produtos (nome, preco, categoria, descricao, quantidade, imagem) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssis", $nome, $preco, $categoria, $descricao, $quantidade, $nomeImagem);

    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success text-center mt-4'>Produto adicionado com sucesso!</div>";
    } else {
        $msg = "<div class='alert alert-danger text-center mt-4'>Erro ao salvar produto.</div>";
    }
}
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Adicionar Produto | Top Motors</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">

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
        .breadcam_bg_2 {
            background-image: url('../img/banner/slider_bg_2.jpg') !important;
            
        }
        #dark:hover {
            background-color: darkred !important;
            color: #ffffff !important;
        }
    </style>

</head>

<body>

    <!-- HEADER -->
    <header>
        <div class="header-area">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">

                        <div class="col-xl-5 col-lg-6">
                            <div class="main-menu d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                        <li><a href="../index.php">home</a></li>
                                        <li><a href="../motos.php">Motos</a></li>
                                        <li><a href="../contact.php">Cadastro</a></li>
                                        <li><a href="index.php">Loja</a></li>
                                        <li><a class="text-danger" href="adicionar.php">Adicionar Produto</a></li>
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

    <!-- BANNER -->
    <div class="bradcam_area breadcam_bg_2">
        <h3>Adicionar Produto</h3>
    </div>

    <!-- FORMULÁRIO -->
    <section class="contact-section">
        <div class="container">

            <?= $msg ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <form class="form-contact contact_form" action="" method="POST" enctype="multipart/form-data">

                        <div class="row">

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input class="form-control" name="nome" type="text" placeholder="Nome do Produto" required>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input class="form-control" name="preco" type="number" step="0.01" placeholder="Preço" required>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input class="form-control" name="categoria" type="text" placeholder="Categoria" required>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input class="form-control" name="quantidade" type="number" placeholder="Quantidade" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <textarea class="form-control" name="descricao" rows="3" placeholder="Descrição do produto"></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <label><b>Imagem do Produto:</b></label>
                                <div class="form-group">
                                    <input class="form-control" name="imagem" type="file" required>
                                </div>
                            </div>

                        </div>

                        <div class="form-group mt-3 text-center">
                            <button type="submit" class="button button-contactForm boxed-btn"
                                id="dark" style="border: 1px solid darkred; color:darkred;">
                                Salvar Produto
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </section>

    <!-- FOOTER -->
    <?php include "../footer.php"; ?>

</body>
</html>
