<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .login-logo {
            font-size: 3rem;
            color: #38bdf8;
            margin-bottom: 1.5rem;
        }
        .login-card h2 {
            color: white;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .login-card p {
            color: #94a3b8;
            margin-bottom: 2rem;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            color: #cbd5e1;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #38bdf8;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #38bdf8;
            color: #0f172a;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-login:hover {
            background: #7dd3fc;
            transform: translateY(-2px);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: none;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <i class="fas fa-balance-scale"></i>
    </div>
    <h2>SCP PGM</h2>
    <p>Acesse o sistema de controle</p>

    <div id="error-msg" class="error-message"></div>

    <form id="login-form">
        <div class="form-group">
            <label for="login">Usuário</label>
            <input type="text" id="login" placeholder="Seu login" required>
        </div>
        <div class="form-group">
            <label for="senha">Senha</label>
            <div class="password-wrapper">
                <input type="password" id="senha" placeholder="••••••••" required>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('senha', this)" style="color:#94a3b8;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login" id="btn-submit">
            <i class="fas fa-sign-in-alt"></i> Entrar no Sistema
        </button>
    </form>
</div>

<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-submit');
        const errorMsg = document.getElementById('error-msg');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Autenticando...';
        errorMsg.style.display = 'none';

        const login = document.getElementById('login').value;
        const senha = document.getElementById('senha').value;

        try {
            const response = await fetch('api.php?acao=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ login, senha })
            });
            const data = await response.json();

            if (data.status === 'sucesso') {
                window.location.href = 'index.php';
            } else {
                errorMsg.textContent = data.message;
                errorMsg.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar no Sistema';
            }
        } catch (error) {
            errorMsg.textContent = 'Erro de conexão com o servidor';
            errorMsg.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar no Sistema';
        }
    });
</script>

</body>
</html>
