<?php
require_once 'config.php';

$error = '';
$success = '';

// Security: Check if an admin already exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'admin'");
    $adminCount = $stmt->fetchColumn();
    if ($adminCount > 0) {
        $error = "O sistema já possui um administrador cadastrado. Por favor, utilize a tela de login.";
    }
} catch (Exception $e) {
    // Possibly tables don't exist yet? We'll report the error below.
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $matriz_nome = $_POST['matriz_nome'] ?? '';
    $admin_nome = $_POST['admin_nome'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';

    if ($matriz_nome && $admin_nome && $admin_email && $admin_password) {
        try {
            $pdo->beginTransaction();

            // 1. Create Matriz (Filial Principal)
            $stmt = $pdo->prepare("INSERT INTO filiais (nome, principal, ativo, cnpj) VALUES (?, 1, 1, ?)");
            $stmt->execute([$matriz_nome, $_POST['cnpj'] ?? '00.000.000/0001-00']);
            $filial_id = $pdo->lastInsertId();

            // 2. Create Admin User
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, ativo, filial_id) VALUES (?, ?, ?, 'admin', 1, ?)");
            $stmt->execute([$admin_nome, $admin_email, $hashed_password, $filial_id]);

            $pdo->commit();
            $success = "Configuração mestre concluída com sucesso! Redirecionando para o login...";
            
            // Redirect after 3 seconds
            header("Refresh: 3; url=login.php");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao configurar sistema: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Mestre - ERP Elétrica</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --login-bg: #0f172a;
            --card-bg: #1e293b;
            --accent-gold: #38bdf8;
            --accent-hover: #0ea5e9;
            --input-bg: #334155;
            --border-color: #475569;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            background-color: var(--login-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .setup-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 550px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .setup-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #38bdf8, #818cf8);
        }

        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .setup-header i {
            font-size: 3rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
            filter: drop-shadow(0 0 15px rgba(56, 189, 248, 0.4));
        }

        .setup-header h1 {
            font-size: 1.5rem;
            color: #fff;
            margin: 0;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .setup-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: var(--input-bg);
            color: #fff !important;
        }

        .form-control:focus {
            border-color: var(--accent-gold);
            background: #1e293b;
            outline: none;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.1);
        }

        .btn-setup {
            width: 100%;
            padding: 14px;
            background: var(--accent-gold);
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
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
        }

        .btn-setup:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn-setup:disabled {
            background: var(--border-color);
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.2);
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-muted);
            font-family: 'Roboto Mono', monospace;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="setup-header">
            <i class="fas fa-database"></i>
            <h1>SETUP INICIAL MESTRE</h1>
            <p>Configuração de Matriz e Conta Administradora</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" <?= ($error && strpos($error, 'já possui') !== false) ? 'style="display:none"' : '' ?>>
            <div class="section-title">Dados da Matriz</div>
            
            <div class="row">
                <div class="col-md-7">
                    <div class="form-group">
                        <label class="form-label">NOME DA EMPRESA</label>
                        <input type="text" name="matriz_nome" class="form-control" placeholder="Ex: Matriz Central" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0001-00">
                    </div>
                </div>
            </div>

            <div class="section-title">Conta Administradora</div>

            <div class="form-group">
                <label class="form-label">NOME COMPLETO</label>
                <input type="text" name="admin_nome" class="form-control" placeholder="Administrador do Sistema" required>
            </div>

            <div class="form-group">
                <label class="form-label">E-MAIL CORPORATIVO</label>
                <input type="email" name="admin_email" class="form-control" placeholder="admin@empresa.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">SENHA MESTRE</label>
                <input type="password" name="admin_password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-setup">
                FINALIZAR CONFIGURAÇÃO <i class="fas fa-rocket"></i>
            </button>
        </form>

        <?php if ($error && strpos($error, 'já possui') !== false): ?>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-primary">Ir para Login</a>
            </div>
        <?php endif; ?>

        <div class="footer">
            SYSTEM INITIALIZATION | ERP ELÉTRICA v2.0
        </div>
    </div>
</body>
</html>
