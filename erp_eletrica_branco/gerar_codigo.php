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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/corporate.css">
    <style>
        body { background-color: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
        .auth-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 480px; border-top: 5px solid var(--erp-primary); }
        .code-display { font-size: 3.5rem; letter-spacing: 8px; font-weight: 800; color: var(--erp-primary); background: #f8f9fa; padding: 20px; border-radius: 12px; border: 2px dashed #dee2e6; margin: 20px 0; font-family: 'Roboto Mono', monospace; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-shield-halved fa-3x text-primary mb-3"></i>
            <h4 class="fw-bold">Geração de Código Único</h4>
            <p class="text-muted small">Este código permite autorizar operações restritas (Sangria, Descontos) por gerentes.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger small"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
        <?php endif; ?>

        <?php if ($code): ?>
            <div class="text-center fade-in">
                <p class="mb-1 small fw-bold text-uppercase opacity-50">Código Gerado para Unidade Selecionada:</p>
                <div class="code-display"><?= $code ?></div>
                <div class="alert alert-warning extra-small py-2 mt-2">
                    <i class="fas fa-info-circle me-1"></i> Este código é de <strong>uso único</strong> e expira em 30 minutos. 
                    Gerar um novo código invalidará este automaticamente.
                </div>
                <button class="btn btn-outline-primary w-100 fw-bold mt-3" onclick="navigator.clipboard.writeText('<?= $code ?>'); this.innerText='COPIADO!';">
                    <i class="fas fa-copy me-2"></i>COPIAR CÓDIGO
                </button>
                <a href="login.php" class="btn btn-link w-100 text-muted small mt-2">Voltar ao Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="auth">
                <div class="mb-3">
                    <label class="form-label small fw-bold">SUA UNIDADE ADM</label>
                    <select name="filial_id" class="form-select" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">E-MAIL DO ADMINISTRADOR</label>
                    <input type="email" name="email" class="form-control" placeholder="seu-email@adm.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">SENHA</label>
                    <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                </div>
                <hr>
                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <label class="form-label small fw-bold">UNIDADE DESTINO</label>
                        <select name="unidade_alvo" class="form-select form-select-sm" required>
                             <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">TIPO DE OPERAÇÃO</label>
                        <select name="tipo" class="form-select form-select-sm" required>
                            <option value="geral">Qualquer Operação</option>
                            <option value="sangria">Sangria de Caixa</option>
                            <option value="suprimento">Suprimento de Caixa</option>
                            <option value="desconto">Desconto Especial</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold">
                    GERAR CÓDIGO DE ACESSO <i class="fas fa-key ms-1"></i>
                </button>
                <a href="login.php" class="btn btn-link w-100 text-muted small mt-2">Cancelar e Voltar</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
