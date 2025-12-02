<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    require '../conex.php'; 

    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Criptografar senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // SQL para inserir
    $sql = "INSERT INTO usuarios (email, senha) VALUES ('$email', '$senhaHash')";

    if ($conex->query($sql) === TRUE) {
        echo "<script>alert('Conta criada com sucesso!'); window.location='adm/index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Erro: Este e-mail já está cadastrado.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Criar Conta - Área Administrativa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Favicon -->
    <link rel="icon" href="img/core-img/favicon.ico">

    <!-- Estilos Amado -->
    <link rel="stylesheet" href="../css/core-style.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div class="main-content-wrapper d-flex align-items-center justify-content-center" style="min-height: 100vh; background-color: #f5f5f5;">

        <!-- Área de cadastro -->
        <div class="cart-table-area p-5 bg-white shadow" style="width: 100%; max-width: 500px; border-radius: 15px;">
            <div class="text-center mb-4">
                <img src="../img/floricultura.png" alt="Logo" style="max-height: 80px;">
                <h2 class="mt-3">Criar Conta</h2>
                <p class="text-muted">Cadastre um novo usuário administrativo</p>
            </div>

            <!-- Formulário de cadastro -->
            <form method="POST">
                <div class="form-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                </div>

                <div class="form-group mb-4">
                    <input type="password" name="senha" class="form-control" placeholder="Senha" required>
                </div>

                <button type="submit" class="btn amado-btn w-100">Cadastrar</button>
            </form>

            <div class="text-center mt-3">
                <a href="index.php" class="text-muted">← Voltar ao login</a>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>
