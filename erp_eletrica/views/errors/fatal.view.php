<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sinto muito! - ERP Elétrica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            max-width: 500px;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #ef4444;
        }
        h1 {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }
        p {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn-primary {
            background-color: #3b82f6;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <h1>Algo não deu certo</h1>
        <p>
            Desculpe pelo transtorno. Ocorreu um erro interno inesperado enquanto processávamos sua solicitação.
            Nossa equipe técnica já foi notificada silenciosamente.
        </p>
        <div class="d-grid gap-2">
            <a href="index.php" class="btn btn-primary">Voltar ao Painel</a>
            <button onclick="location.reload()" class="btn btn-light">Tentar Novamente</button>
        </div>
        <div class="mt-4 extra-small text-muted">
            Código de Referência: <?= date('YmdHis') ?>
        </div>
    </div>
</body>
</html>
