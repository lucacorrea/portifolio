<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_nivel'] = $user['nivel'];
            $_SESSION['usuario_avatar'] = $user['avatar'];
            $_SESSION['filial_id'] = $user['filial_id'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            header('Location: index.php');
            exit;
        } else {
            // Check for initial default user if table is empty or specific case
            if ($email === 'admin@erp.com' && $senha === 'admin123') {
                $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
                if ($count == 0 || ($email === 'admin@erp.com' && !$user)) {
                    $hash = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)");
                    $stmt->execute(['Administrador', 'admin@erp.com', $hash, 'admin']);
                    
                    // Try login again
                    $_SESSION['usuario_id'] = $pdo->lastInsertId();
                    $_SESSION['usuario_nome'] = 'Administrador';
                    $_SESSION['usuario_nivel'] = 'admin';
                    header('Location: index.php');
                    exit;
                }
            }
            $error = 'E-mail ou senha incorretos.';
        }
    } else {
        $error = 'Por favor, preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Elétrica</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <!-- Custom Corporate UI -->
    <link rel="stylesheet" href="public/css/corporate.css">
    <style>
        body {
            background-color: #f4f7f6;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            background: white;
            padding: 50px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 450px;
            border: 1px solid var(--border);
            position: relative;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color), #4da3ff);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .login-header i {
            font-size: 3.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 6px rgba(0,86,179,0.2));
        }
        .login-header h1 {
            font-size: 1.75rem;
            color: var(--secondary-color);
            margin: 0;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: #fcfdfe;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(0,86,179,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,86,179,0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }
    </style>
</head>
<body>
    <div class="login-card fade-in">
        <div class="login-header">
            <i class="fas fa-bolt"></i>
            <h1>ERP ELÉTRICA</h1>
            <p>CONEXÃO INDUSTRIAL SEGURA</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-shield-alt"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert" style="background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb;">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">E-MAIL CORPORATIVO</label>
                <input type="email" name="email" class="form-control" placeholder="usuario@empresa.com" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">SENHA TÉCNICA</label>
                <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">
                AUTENTICAR ACESSO <i class="fas fa-shield-alt"></i>
            </button>
        </form>

        <div style="margin-top: 25px; text-align: center; font-size: 0.75rem; color: #bdc3c7; font-family: 'Roboto Mono', monospace;">
            VERSÃO <?php echo APP_VERSION; ?> | SECURE INDUSTRIAL ACCESS
        </div>
    </div>
</body>
</html>
