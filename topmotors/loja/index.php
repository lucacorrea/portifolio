<?php
require "conexao.php";

// Buscar total de produtos
$total = $con->query("SELECT COUNT(*) AS t FROM produtos")->fetch_assoc()['t'];

// Últimos 4 produtos
$ultimos = $con->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Loja - Painel Inicial</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">

    <style>
        body { background: #f5f5f5; }
        .menu-box {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
            margin-top: 30px;
        }
        .card img { height: 150px; object-fit: cover; }
    </style>
</head>

<body>

<div class="container">

    <!-- Cabeçalho -->
    <div class="menu-box text-center">
        <h2>📦 Sistema de Produtos</h2>
        <p>Gerencie seus produtos de forma simples e rápida.</p>

        <div class="mt-4">
            <a href="adicionar.php" class="btn btn-success btn-lg me-2">+ Adicionar Produto</a>
            <a href="visualizar.php" class="btn btn-primary btn-lg">📋 Ver Produtos</a>
        </div>

        <hr>

        <h4>Total de produtos cadastrados: <b><?php echo $total; ?></b></h4>
    </div>


    <!-- Últimos produtos cadastrados -->
    <h3 class="mt-5 mb-3">Últimos produtos adicionados</h3>

    <div class="row">
        <?php while ($p = $ultimos->fetch_assoc()) { ?>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <img src="uploads/<?php echo $p['imagem']; ?>" class="card-img-top">
                    <div class="card-body">
                        <h5><?php echo $p['nome']; ?></h5>
                        <p>R$ <?php echo number_format($p['preco'], 2, ",", "."); ?></p>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

</div>

</body>
</html>
