<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Agro Forest</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f8f6f0 0%, #f3efe6 100%);
            color: #1f2d16;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 420px;
            padding: 16px;
        }

        .card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 10px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.14);
            overflow: hidden;
        }

        .card-body {
            padding: 32px 28px;
        }

        h2 {
            margin: 0 0 24px;
            font-size: 1.9rem;
            text-align: center;
            color: #1d3b11;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cddad0;
            border-radius: 7px;
            background: #f7f9f4;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4ea34a;
            box-shadow: 0 0 0 4px rgba(78, 163, 74, 0.16);
            background: #ffffff;
        }

        .btn {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 7px;
            background: #2f7c28;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .btn:hover {
            background: #25631f;
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 24px 18px;
            }

            h2 {
                font-size: 1.7rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <div class="logo">
                    <img src="" class="logo-img" alt="Agro Forest">
                </div>
                <h2>Seja Bem-vindo</h2>
                <form action="login_process.php" method="POST">
                    <div class="form-group">
                        <label for="username">Usuário:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Senha:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>