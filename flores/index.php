<?php
include_once("../conex.php");
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $categoria = $_POST['categoria'];
    $descricao = $_POST['descricao'];
    $quantidade = $_POST['quantidade'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {

        // PASTAS
        $pastaServidor = "../uploads/";  // onde será guardado fisicamente
        $pastaSite = "uploads/";         // caminho usado no index

        // Criar pasta se não existir
        if (!is_dir($pastaServidor)) mkdir($pastaServidor, 0777, true);

        // Nome único
        $nomeArquivo = uniqid() . "_" . basename($_FILES['imagem']['name']);

        // Caminhos completos
        $caminhoFisico = $pastaServidor . $nomeArquivo;  // para mover o arquivo
        $caminhoBanco = $pastaSite . $nomeArquivo;       // para salvar no banco

        // Mover imagem para uploads/
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoFisico)) {

            // Inserir no banco
            $sql = "INSERT INTO produtos (nome, preco, categoria, descricao, quantidade, imagem)
                    VALUES ('$nome', '$preco', '$categoria', '$descricao', '$quantidade', '$caminhoBanco')";

            if ($conex->query($sql)) {
                $msg = "<div class='alert alert-success text-center'>Produto cadastrado com sucesso!</div>";
            } else {
                $msg = "<div class='alert alert-danger text-center'>Erro ao salvar no banco.</div>";
            }

        } else {
            $msg = "<div class='alert alert-danger text-center'>Erro ao enviar a imagem.</div>";
        }

    } else {
        $msg = "<div class='alert alert-warning text-center'>Selecione uma imagem válida.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Cadastro de Produto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="../css/core-style.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div class="cart-table-area section-padding-100">
        <div class="container-fluid">
            <div class="row">

                <!-- FORMULÁRIO -->
                <div class="col-12 col-lg-8">
                    <div class="checkout_details_area mt-50 clearfix">

                        <div class="cart-title">
                            <h2>Cadastrar Produto</h2>
                        </div>

                        <?= $msg ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <input type="text" class="form-control" name="nome" placeholder="Nome do produto" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="text" class="form-control" name="preco" placeholder="Preço (ex: 29.90)" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <input type="text" class="form-control" name="categoria" placeholder="Categoria (Flores, Buquê...)" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <textarea class="form-control" name="descricao" rows="4" placeholder="Descrição" required></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="number" class="form-control" name="quantidade" placeholder="Quantidade" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="file" class="form-control" name="imagem" accept="image/*" required>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn amado-btn w-100">Cadastrar</button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>

                <!-- LADO DIREITO -->
                <div class="col-12 col-lg-4">
                    <div class="cart-summary mt-50">
                        <h5>Ajuda</h5>
                        <ul class="summary-table">
                            <li><span>Imagem:</span> <span>Obrigatória</span></li>
                            <li><span>Formato:</span> <span>jpg / png</span></li>
                        </ul>

                        <div class="cart-btn mt-4">
                            <a href="listar-produtos.php" class="btn amado-btn w-100">Ver Produtos</a>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="index.php" class="text-muted">← Voltar</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../js/jquery/jquery-2.2.4.min.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/active.js"></script>

</body>

</html>
