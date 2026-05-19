<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #0f172a 100%);
            overflow: hidden;
        }
        .login-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3.5rem 3rem;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
            text-align: center;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            font-size: 2.5rem;
            color: #4f46e5;
            margin-bottom: 1rem;
            filter: drop-shadow(0 2px 10px rgba(79, 70, 229, 0.4));
        }
        .login-card h2 {
            color: white;
            margin-bottom: 0.35rem;
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
        }
        .login-card p {
            color: #94a3b8;
            margin-bottom: 2.25rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1.15rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            transition: all 0.25s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4f46e5;
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 3rem;
        }
        .toggle-password {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: white;
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .btn-login:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: none;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.4;
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
