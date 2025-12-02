<?php

// PROCESSAMENTO DO LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require '../conex.php';

    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $sql = "SELECT * FROM usuarios WHERE email = '$email'";
    $resultado = $conex->query($sql);

    if ($resultado && $resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();

        if (password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_email'] = $usuario['email'];
            header('Location: cadprod.php');
            exit;
        } else {
            $erro = "Senha incorreta.";
        }
    } else {
        $erro = "E-mail não encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Área Administrativa - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="img/core-img/favicon.ico">
    <link rel="stylesheet" href="../css/core-style.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div class="main-content-wrapper d-flex align-items-center justify-content-center" style="min-height: 100vh; background-color: #f5f5f5;">

        <div class="cart-table-area p-5 bg-white shadow" style="width: 100%; max-width: 500px; border-radius: 15px;">
            <div class="text-center mb-4">
                <img src="../img/floricultura.png" alt="Logo" style="max-height: 80px;">
                <h2 class="mt-3">Área Administrativa</h2>
                <p class="text-muted">Informe seu e-mail e senha para entrar</p>

                <?php if (!empty($erro)): ?>
                    <div style="color: red; margin-top: 10px;"><?php echo $erro; ?></div>
                <?php endif; ?>
            </div>

            <form method="POST">
                <div class="form-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                </div>
                <div class="form-group mb-4">
                    <input type="password" name="senha" class="form-control" placeholder="Senha" required>
                </div>
                <button type="submit" class="btn amado-btn w-100">Entrar</button>
            </form>

            <div class="text-center mt-3">
                <div class="mb-2">
                    <a href="criar-conta.php" class="btn btn-link text-muted">Criar Conta</a>
                </div>
                <div>
                    <a href="../index.php" class="btn btn-link text-muted">← Voltar ao site</a>
                </div>
            </div>
        </div>

    </div>

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/active.js"></script>

</body>

</html>
