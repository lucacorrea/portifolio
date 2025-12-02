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
        $pasta = "../uploads/";
        if (!is_dir($pasta)) mkdir($pasta, 0777, true);

        $nomeArquivo = uniqid() . "_" . basename($_FILES['imagem']['name']);
        $caminho = $pasta . $nomeArquivo;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho)) {
            $sql = "INSERT INTO produtos (nome, preco, categoria, descricao, quantidade, imagem) 
                    VALUES ('$nome', '$preco', '$categoria', '$descricao', '$quantidade', '$caminho')";

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

    <!-- Estilos Amado -->
    <link rel="stylesheet" href="../css/core-style.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <!-- Wrapper principal -->
    <div class="main-content-wrapper d-flex align-items-center justify-content-center" style="min-height: 100vh; background-color: #f5f5f5;">
        <div class="cart-table-area p-5 bg-white shadow" style="width: 100%; max-width: 600px; border-radius: 15px;">

            <div class="text-center mb-4">
                <img src="../img/floricultura.png" alt="Logo" style="max-height: 80px;">
                <h2 class="mt-3">Cadastrar Produto</h2>
                <p class="text-muted">Preencha os dados abaixo para adicionar um novo produto</p>
            </div>

            <!-- Mensagem de retorno -->
            <?php echo $msg; ?>

            <!-- Formulário -->
            <form method="POST" enctype="multipart/form-data">
                <input type="text" class="form-control mt-3" name="nome" placeholder="Nome do produto" required>
                <input type="text" class="form-control mt-3" name="preco" placeholder="Preço (ex: 29.90)" required>
                <input type="text" class="form-control mt-3" name="categoria" placeholder="Categoria (Flores, Buquê...)" required>
                <textarea name="descricao" class="form-control mt-3" rows="4" placeholder="Descrição do produto" required></textarea>
                <input type="number" class="form-control mt-3" name="quantidade" placeholder="Quantidade em estoque" required>
                <input type="file" name="imagem" class="form-control mt-3" accept="image/*" required>

                <button type="submit" class="btn amado-btn mt-4 w-100">Cadastrar</button>
            </form>

            <div class="text-center mt-4">
                <a href="index.php" class="text-muted">← Voltar para o login</a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../js/jquery/jquery-2.2.4.min.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/active.js"></script>

</body>

</html>
