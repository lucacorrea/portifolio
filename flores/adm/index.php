<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Área Administrativa - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Estilo do Template Amado -->
    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="main-content-wrapper d-flex clearfix justify-content-center align-items-center" style="min-height:100vh; background-color: #f5f5f5;">
        <div class="cart-table-area p-5 bg-white shadow" style="width: 100%; max-width: 500px; border-radius: 15px;">
            <div class="cart-title mb-4 text-center">
                <img src="img/core-img/logo.png" alt="Logo" style="max-height: 80px;">
                <h2 class="mt-3">Login Administrativo</h2>
            </div>
            <form method="POST" action="verifica_login.php">
                <div class="form-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                </div>
                <div class="form-group mb-4">
                    <input type="password" name="senha" class="form-control" placeholder="Senha" required>
                </div>
                <button class="btn amado-btn w-100" type="submit">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
