<!-- app/views/login/index.php -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Elétrica</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e9ecef;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            background-color: #212529; /* Dark tech header */
            color: #fff;
            padding: 20px;
            text-align: center;
            border-bottom: 3px solid #0d6efd;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 10px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="card login-card">
        <div class="login-header rounded-top">
            <h4 class="mb-0">ERP ELÉTRICA</h4>
            <small class="text-white-50">Acesso Restrito</small>
        </div>
        <div class="card-body p-4">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger text-center text-small py-2 mb-3">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="?url=login/index">
                <div class="mb-3">
                    <label for="email" class="form-label text-muted small">E-mail Corporativo</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seunome@empresa.com" autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label text-muted small">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">Entrar no Sistema</button>
                </div>
                <div class="text-center">
                    <small class="text-muted">Esqueceu a senha? Contate o T.I.</small>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white text-center py-3">
             <small class="text-muted">Sistema Integrado v1.0</small>
        </div>
    </div>

</body>
</html>
