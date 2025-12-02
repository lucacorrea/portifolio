<?php
include_once("conex.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <form action="" method="post" enctype="multipart/form-data">
        <input type="text" class="form-control mt-3" name="nome" placeholder="digite o nome" required>
        <input type="text" class="form-control mt-3" name="preco" placeholder="digite o preço" required>
        <input type="text" class="form-control mt-3" name="categoria" placeholder="(Flores, Buquê, Cactos)" required>
        <textarea name="descricao" class="form-control mt-3" rows="4" placeholder="Escreva a descrição do produto" required></textarea>
        <input type="number" class="form-control mt-3" name="quantidade" placeholder="digite a quantidade" required>
        <input type="file" name="imagem" class="form-control mt-3" accept="image/*" required>
        <input type="submit" class="btn btn-primary mt-3" value="Cadastrar">
    </form>

<?php

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $categoria = $_POST['categoria'];
    $descricao = $_POST['descricao'];
    $quantidade = $_POST['quantidade'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {

        $pasta = "uploads/";
        if (!is_dir($pasta)) mkdir($pasta, 0777, true);

        $nomeArquivo = uniqid() . "_" . basename($_FILES['imagem']['name']);
        $caminho = $pasta . $nomeArquivo;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho)) {

            $sql = "INSERT INTO produtos (nome, preco, categoria, descricao, quantidade, imagem) 
                    VALUES ('$nome', '$preco', '$categoria', '$descricao', '$quantidade', '$caminho')";

            if ($conex->query($sql)) {
                echo "<div class='alert alert-success text-center mt-3'>Produto cadastrado com sucesso!</div>";
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Erro ao salvar no banco.</div>";
            }

        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Erro ao enviar a imagem.</div>";
        }

    } else {
        echo "<div class='alert alert-warning text-center mt-3'>Selecione uma imagem válida.</div>";
    }
}

?>
</body>
</html>