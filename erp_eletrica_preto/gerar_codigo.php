<?php
require_once 'config.php';

$error = '';
$code = '';
$branches = $pdo->query("SELECT id, nome FROM filiais ORDER BY principal DESC, nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'auth') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $selected_filial = $_POST['filial_id'] ?? '';

    // Specialized check for Admin/Master only
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND filial_id = ? AND nivel IN ('admin', 'master') AND ativo = 1");
    $stmt->execute([$email, $selected_filial]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        // Generate the code
        $authService = new \App\Services\AuthorizationService();
        $tipo = $_POST['tipo'] ?? 'geral';
        $unidadeAlvo = $_POST['unidade_alvo'] ?? $selected_filial;
        
        $code = $authService->generateCode($tipo, $unidadeAlvo, $user['id']);
    } else {
        $error = 'Credenciais administrativas inválidas ou permissão insuficiente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Autorização - ERP Elétrica</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <!-- Custom Corporate UI -->
    <link rel="stylesheet" href="style.css?v=3.9">
    <link rel="stylesheet" href="public/css/corporate.css?v=3.9">
    <style>
        :root {
            --login-bg: #0a0a0a;
            --card-bg: #141414;
            --accent-gold: #FFC107;
            --accent-hover: #FFB300;
            --input-bg: #0d0d0d;
            --border-color: #262626;
        }

        body {
            background-color: var(--login-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: #f8fafc;
        }

        .auth-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gold);
        }

        .code-display {
            font-size: 3rem;
            letter-spacing: 8px;
            font-weight: 800;
            color: var(--accent-gold);
            background: var(--input-bg);
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed var(--border-color);
            margin: 20px 0;
            font-family: 'Roboto Mono', monospace;
            text-align: center;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #a3a3a3;
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: var(--input-bg);
            color: #fff !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-gold);
            background: var(--input-bg);
            outline: none;
            box-shadow: none;
        }

        .form-control::placeholder {
            color: #475569 !important;
        }

        .btn-primary {
            background-color: var(--accent-gold) !important;
            border: none !important;
            color: #000 !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 14px !important;
            transition: all 0.2s ease-in-out !important;
        }

        .btn-primary:hover {
            background-color: #0a0a0a !important;
            border-color: #FFC107 !important;
            transform: translateY(-1px);
            color: #fff !important;
        }

        .btn-outline-primary {
            border-color: var(--accent-gold) !important;
            color: var(--accent-gold) !important;
            background: transparent !important;
            font-weight: 700 !important;
        }

        .btn-outline-primary:hover {
            background-color: #0a0a0a !important;
            border-color: #FFC107 !important;
            color: #fff !important;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.05) !important;
            backdrop-filter: blur(4px);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15) !important;
            color: #ff8a8a !important;
        }

        .alert-warning {
            background-color: rgba(197, 160, 40, 0.15) !important;
            color: #ffd75e !important;
        }

        .text-accent {
            color: var(--accent-gold) !important;
        }

        small.text-muted {
            color: #737373 !important;
        }

        hr {
            border-top-color: var(--border-color);
            opacity: 1;
        }

        select option {
            background: var(--card-bg);
            color: #fff;
        }

        @media (max-width: 480px) {
            .auth-card {
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
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-shield-halved fa-3x text-accent mb-3"></i>
            <h4 class="fw-bold mb-1">Geração de Código Único</h4>
            <p class="text-muted small">Autorização para operações restritas (Sangria, Descontos).</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
        <?php endif; ?>

        <?php if ($code): ?>
            <div class="text-center">
                <p class="mb-1 small fw-bold text-uppercase opacity-50">Código Gerado:</p>
                <div class="code-display"><?= $code ?></div>
                <div class="alert alert-warning extra-small py-2 mt-2">
                    <i class="fas fa-info-circle me-1"></i> Este código é de <strong>uso único</strong> e expira em 30 minutos. 
                </div>
                <button class="btn btn-outline-primary w-100 fw-bold mt-4" onclick="navigator.clipboard.writeText('<?= $code ?>'); this.innerText='COPIADO!';">
                    <i class="fas fa-copy me-2"></i>COPIAR CÓDIGO
                </button>
                <div class="mt-4">
                    <a href="login.php" class="text-muted small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Voltar ao Login</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="auth">
                <div class="mb-4">
                    <label class="form-label">SUA UNIDADE ADM</label>
                    <select name="filial_id" class="form-select" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">E-MAIL DO ADMINISTRADOR</label>
                    <input type="email" name="email" class="form-control" placeholder="seu-email@adm.com" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">SENHA</label>
                    <div class="input-group">
                        <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility(this)" style="border-color: var(--border-color); background: var(--input-bg); color: #475569;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label">UNIDADE DESTINO</label>
                        <select name="unidade_alvo" class="form-select form-select-sm" required>
                             <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">TIPO DE OPERAÇÃO</label>
                        <select name="tipo" class="form-select form-select-sm" required>
                            <option value="geral">Qualquer</option>
                            <option value="sangria">Sangria</option>
                            <option value="suprimento">Suprimento</option>
                            <option value="desconto">Desconto</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                    GERAR CÓDIGO DE ACESSO <i class="fas fa-key ms-1"></i>
                </button>
                <div class="mt-4 text-center">
                    <a href="login.php" class="text-muted small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Cancelar e Voltar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/corporate.js"></script>
</body>
</html>
