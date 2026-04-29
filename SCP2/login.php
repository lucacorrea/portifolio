<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: v2/index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP 2.0 - Acesso ao Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="v2/assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: #0f172a;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .input-group input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: white;
            outline: none;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-login:hover {
            box-shadow: 0 0 20px var(--primary-glow);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="glass-card">
            <div class="login-header">
                <i class="fas fa-microchip"></i>
                <h1 style="font-weight: 800; font-size: 1.8rem;">SCP 2.0</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Versão Premium com Integração Projudi</p>
            </div>

            <form id="form-login">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" id="login" name="login" placeholder="Seu usuário" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
                </div>

                <button type="submit" class="btn-login" id="btn-entrar">
                    Entrar no Sistema
                </button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: var(--text-muted);">
                &copy; 2024 Procuradoria Geral do Município
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('form-login').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btn-entrar');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Autenticando...';
            btn.disabled = true;

            const login = document.getElementById('login').value;
            const senha = document.getElementById('senha').value;

            try {
                const response = await fetch('api.php?acao=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ login, senha })
                });
                
                const result = await response.json();
                
                if(result.status === 'sucesso') {
                    location.href = 'v2/index.html';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Acesso Negado',
                        text: result.message,
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#f87171'
                    });
                    btn.innerHTML = 'Entrar no Sistema';
                    btn.disabled = false;
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Conexão',
                    text: 'Não foi possível falar com o servidor.',
                    background: '#1e293b',
                    color: '#fff'
                });
                btn.innerHTML = 'Entrar no Sistema';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
