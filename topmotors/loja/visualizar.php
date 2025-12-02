<?php require "conexao.php"; ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Produtos cadastrados</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">

    <style>
        .card img { height: 160px; object-fit: cover; }
    </style>
</head>

<body>

<div class="container mt-4">
    <h3 class="mb-4">Produtos cadastrados</h3>
    <a href="adicionar.php" class="btn btn-primary mb-3">+ Adicionar Produto</a>

    <div class="row">
        <?php
        $sql = $con->query("SELECT * FROM produtos ORDER BY id DESC");
        while ($p = $sql->fetch_assoc()) {
        ?>
        <div class="col-md-3 mb-4">
            <div class="card">
                <img src="uploads/<?php echo $p['imagem']; ?>" class="card-img-top">
                <div class="card-body">
                    <h5><?php echo $p['nome']; ?></h5>
                    <p>R$ <?php echo number_format($p['preco'], 2, ",", "."); ?></p>
                    <p><b>Qtd:</b> <?php echo $p['quantidade']; ?></p>
                    <small><?php echo $p['categoria']; ?></small>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

</body>
</html>
