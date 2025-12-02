<?php require "conexao.php"; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Produto</title>

    <link rel="stylesheet" href="css/bootstrap.min.css">

    <style>
        .form-box {
            max-width: 700px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 18px rgba(0,0,0,.1);
        }
    </style>
</head>

<body>

<div class="container">
    <div class="form-box">

        <h3 class="mb-4">Cadastrar Produto</h3>

        <form action="processa_produto.php" method="POST" enctype="multipart/form-data">

            <div class="row">

                <div class="col-md-6">
                    <label>Nome do Produto</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label>Preço</label>
                    <input type="number" step="0.01" name="preco" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label>Qtd</label>
                    <input type="number" name="quantidade" class="form-control" required>
                </div>

                <div class="col-md-6 mt-3">
                    <label>Categoria</label>
                    <input type="text" name="categoria" class="form-control">
                </div>

                <div class="col-md-6 mt-3">
                    <label>Imagem</label>
                    <input type="file" name="imagem" class="form-control" required>
                </div>

                <div class="col-12 mt-3">
                    <label>Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4"></textarea>
                </div>

            </div>

            <button class="btn btn-success mt-4">Salvar Produto</button>

        </form>

    </div>
</div>

</body>
</html>
