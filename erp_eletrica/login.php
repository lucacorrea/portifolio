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
    <link rel="stylesheet" href="style.css?v=7.0">
    <link rel="stylesheet" href="public/css/corporate.css?v=7.0">
    <style>
        :root {
            --login-bg: #cbd5e1;
            --card-bg: #ffffff;
            --accent-gold: #2b4c7d;
            --accent-hover: #1e3a62;
            --input-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        body {
            background-color: var(--login-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        .login-card {
            background: var(--card-bg);
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 450px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gold);
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header i {
            font-size: 3.5rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(43, 76, 125, 0.3));
        }

        .login-header h1 {
            font-size: 1.75rem;
            color: #1e293b;
            margin: 0;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 5px;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: #64748b;
            font-size: 0.7rem;
            letter-spacing: 1px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background: var(--input-bg);
            color: #1e293b !important;
        }

        .form-control:focus {
            border-color: var(--accent-gold);
            background: var(--input-bg);
            outline: none;
            box-shadow: none;
            color: #1e293b;
        }

        .form-control::placeholder {
            color: #475569 !important;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--accent-gold);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            background: #1e3a62 !important;
            border: 2px solid #2b4c7d !important;
            transform: translateY(-1px);
            color: #fff !important;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255,255,255,0.1) !important;
            background-color: rgba(239, 68, 68, 0.15) !important;
            color: #ff8a8a !important;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.15) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.2) !important;
        }

        .text-accent {
            color: var(--accent-gold) !important;
        }

        .footer-info {
            margin-top: 30px;
            text-align: center;
            font-size: 0.7rem;
            color: #404040;
            font-family: 'Roboto Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        select.form-control option {
            background-color: #ffffff;
            color: #1e293b;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
                border-radius: 0;
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
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
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">UNIDADE DE ACESSO</label>
                <select name="filial_id" class="form-control" required>
                    <option value="" disabled selected>Selecione a Empresa...</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>"><?= $branch['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">E-MAIL CORPORATIVO</label>
                <input type="email" name="email" class="form-control" placeholder="usuario@empresa.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">SENHA TÉCNICA</label>
                <div class="input-group">
                    <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility(this)" style="border-color: var(--border-color); background: var(--input-bg); color: #475569;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login">
                AUTENTICAR ACESSO <i class="fas fa-shield-alt"></i>
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="gerar_codigo.php" class="text-accent small fw-bold text-decoration-none transition-all">
                <i class="fas fa-key me-1"></i> Gerar Código de Autorização (Admin)
            </a>
        </div>

        <div class="footer-info">
            VERSÃO <?php echo APP_VERSION; ?> | SECURE INDUSTRIAL ACCESS
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/corporate.js"></script>
</body>
</html>
