<?php
require "conexao.php";

$nome       = $_POST['nome'];
$preco      = $_POST['preco'];
$categoria  = $_POST['categoria'];
$descricao  = $_POST['descricao'];
$quantidade = $_POST['quantidade'];

$img = $_FILES['imagem']['name'];
$tmp = $_FILES['imagem']['tmp_name'];

$destino = "uploads/" . $img;

if (move_uploaded_file($tmp, $destino)) {

    $sql = "INSERT INTO produtos (nome, preco, categoria, descricao, quantidade, imagem)
            VALUES ('$nome', '$preco', '$categoria', '$descricao', '$quantidade', '$img')";

    if ($con->query($sql)) {
        echo "<script>alert('Produto cadastrado com sucesso!'); window.location='visualizar.php';</script>";
    } else {
        echo "Erro ao salvar no banco: " . $con->error;
    }

} else {
    echo "Erro ao enviar imagem!";
}
?>
