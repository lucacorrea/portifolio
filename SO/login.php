<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$page_title = "Login - SGAO";

if (isset($_SESSION['user_id']) || isset($_SESSION['secretaria_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // Tentar login como Usuário (Admin/Suporte/Func)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['nivel'] = $user['nivel'];
        log_action($pdo, "LOGIN", "Usuário {$user['usuario']} logado");
        header("Location: dashboard.php");
        exit();
    }

    // Tentar login como Secretaria (Código de Acesso) - senha é o próprio código aqui para simplificar
    $stmt = $pdo->prepare("SELECT * FROM secretarias WHERE codigo_acesso = ?");
    $stmt->execute([$login]);
    $sec = $stmt->fetch();

    if ($sec && $senha === $sec['codigo_acesso']) {
        $_SESSION['secretaria_id'] = $sec['id'];
        $_SESSION['secretaria_nome'] = $sec['nome'];
        log_action($pdo, "LOGIN_SEC", "Secretaria {$sec['nome']} logada");
        header("Location: acompanhamento.php");
        exit();
    }

    $error = "Login ou senha inválidos!";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGAO - Sistema de Gestão de Ofícios e Aquisições</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-body);
            height: 100vh;
            margin: 0;
            padding: 1.5rem;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            padding: 3rem 2.5rem;
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-logo i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .login-logo p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-group-icon {
            position: relative;
        }

        .form-group-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .form-group-icon .form-control {
            padding-left: 2.75rem;
            height: 3rem;
        }

        .login-btn {
            width: 100%;
            height: 3rem;
            font-size: 1rem;
            margin-top: 1rem;
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8125rem;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <img src="assets/img/prefeitura.png" alt="SGAO" width="90">
            <p>Sistema de Gestão de Ofícios e Aquisições</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 2rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Usuário ou Código da Secretaria</label>
                <div class="form-group-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="login" class="form-control" required placeholder="Digite seu acesso" autofocus>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Senha</label>
                <div class="form-group-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="senha" class="form-control" required placeholder="Digite sua senha">
                </div>
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                <i class="fas fa-sign-in-alt"></i> Entrar no Sistema
            </button>
        </form>
        
        <div class="login-footer">
            <p>Seu acesso é restrito e monitorado.<br>Caso tenha problemas, contate o administrador.</p>
            <p style="margin-top: 1rem; font-weight: 700;">Prefeitura Municipal de Coari &copy; 2026</p>
        </div>
    </div>
</body>
</html>
