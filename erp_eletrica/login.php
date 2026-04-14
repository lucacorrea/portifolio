<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Fetch active branches for the selection
$branches = $pdo->query("SELECT id, nome FROM filiais ORDER BY principal DESC, nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $selected_filial = $_POST['filial_id'] ?? '';

    if ($email && $senha && $selected_filial) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND filial_id = ? AND ativo = 1");
        $stmt->execute([$email, $selected_filial]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_nivel'] = $user['nivel'];
            $_SESSION['usuario_avatar'] = $user['avatar'];
            $_SESSION['filial_id'] = $user['filial_id'];
            
            // Check Matriz
            $stmt = $pdo->prepare("SELECT principal FROM filiais WHERE id = ?");
            $stmt->execute([$user['filial_id']]);
            $filial = $stmt->fetch();
            $_SESSION['is_matriz'] = ($filial && $filial['principal'] == 1);

            // Update last login
            $stmt = $pdo->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            header('Location: index.php');
            exit;
        } else {
            // Initial/Global Admin bypass or specific error
            if ($email === 'admin@erp.com' && $senha === 'admin123') {
                 // Check if it's the Matriz selected
                 $matriz = $pdo->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1")->fetch();
                 if ($matriz && $selected_filial == $matriz['id']) {
                    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch();
                    if ($admin && password_verify($senha, $admin['senha'])) {
                        // Correct credentials, proceed
                        $_SESSION['usuario_id'] = $admin['id'];
                        $_SESSION['usuario_nome'] = $admin['nome'];
                        $_SESSION['usuario_nivel'] = $admin['nivel'];
                        $_SESSION['filial_id'] = $admin['filial_id'];
                        $_SESSION['is_matriz'] = true;
                        header('Location: index.php');
                        exit;
                    }
                 }
            }
            $error = 'Credenciais inválidas para esta unidade.';
        }
    } else {
        $error = 'Por favor, selecione a unidade e informe suas credenciais.';
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
    <link rel="stylesheet" href="style.css?v=10.4">
    <link rel="stylesheet" href="public/css/corporate.css?v=10.4">
    <style>
        :root {
            --login-bg: #cbd5e1;
            --login-bg-accent: #94a3b8;
            --card-bg: #ffffff;
            --brand-header: #2b4c7d;
            --accent-gold: #FFC107;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --input-border: #e2e8f0;
            --corporate-blue: #2b4c7d;
        }

        body {
            background: linear-gradient(135deg, var(--login-bg) 0%, var(--login-bg-accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 
                0 10px 25px -5px rgba(0, 0, 0, 0.1),
                0 8px 10px -6px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 440px;
            position: relative;
            overflow: hidden;
            border: none;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            background: var(--brand-header);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            border-bottom: 4px solid var(--accent-gold);
        }

        .brand-header img {
            max-width: 80%;
            height: auto;
            max-height: 80px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        .login-body {
            padding: 40px;
        }

        .login-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-title p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f8fafc;
            color: var(--text-main);
        }

        .form-control:focus {
            border-color: var(--corporate-blue);
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(43, 76, 125, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--corporate-blue);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            box-shadow: 0 4px 6px -1px rgba(43, 76, 125, 0.2);
        }

        .btn-login:hover {
            background: var(--brand-header);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(43, 76, 125, 0.3);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: #fef2f2;
            border-color: #fee2e2;
            color: #b91c1c;
        }

        .alert-info {
            background-color: #eff6ff;
            border-color: #dbeafe;
            color: #1d4ed8;
        }

        .auth-admin-link {
            display: inline-flex;
            align-items: center;
            color: var(--corporate-blue);
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }

        .auth-admin-link:hover {
            color: var(--text-main);
        }

        .footer-info {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-muted);
            font-family: 'Roboto Mono', monospace;
            letter-spacing: 0.5px;
        }

        @media (max-width: 480px) {
            .login-card {
                max-width: 100%;
                border-radius: 0;
                box-shadow: none;
                min-height: 100vh;
            }
            .login-body {
                padding: 25px;
            }
            .brand-header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-header">
            <img src="logo_sistema_erp_eletrica.png?v=3" alt="ERP Elétrica">
        </div>

        <div class="login-body">
            <div class="login-title">
                <h2>LOGIN</h2>
                <p>Identifique-se para continuar</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Unidade de Acesso</label>
                    <select name="filial_id" class="form-control" required>
                        <option value="" disabled selected>Selecionar unidade...</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>"><?= $branch['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail Corporativo</label>
                    <input type="email" name="email" class="form-control" placeholder="usuario@empresa.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Senha Técnica</label>
                    <div class="input-group">
                        <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility(this)" style="border-color: var(--input-border); background: #f8fafc; color: #64748b;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    Confirmar Acesso <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="gerar_codigo.php" class="auth-admin-link">
                    <i class="fas fa-key me-1"></i> Área do Administrador
                </a>
            </div>

            <div class="footer-info">
               Desenvolvido por L&J Soluções Tecnológicas.
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/corporate.js"></script>
</body>
</html>
