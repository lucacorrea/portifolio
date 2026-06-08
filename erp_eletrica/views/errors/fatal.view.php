<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Algo nao deu certo - ERP Eletrica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f6fb;
            color: #0f2f5f;
            font-family: Arial, sans-serif;
        }
        .error-card {
            width: min(92vw, 520px);
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            box-shadow: 0 18px 50px rgba(15, 47, 95, .12);
            padding: 28px;
            text-align: center;
        }
        .error-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fff3cd;
            color: #9a6700;
            font-size: 24px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 1.35rem;
            font-weight: 800;
        }
        p {
            color: #5f6f89;
            margin-bottom: 22px;
            line-height: 1.55;
        }
        .ref {
            color: #8a97ab;
            font-size: .78rem;
            margin-top: 18px;
        }
    </style>
</head>
<body>
    <main class="error-card">
        <div class="error-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <h1>Nao foi possivel concluir agora</h1>
        <p>O sistema encontrou uma falha inesperada. A operacao nao foi finalizada. Tente novamente em instantes ou volte ao painel.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <button type="button" class="btn btn-primary px-4" onclick="history.back()">Voltar</button>
            <a href="index.php" class="btn btn-outline-secondary px-4">Painel</a>
        </div>
        <div class="ref">Referencia: <?= date('YmdHis') ?></div>
    </main>
</body>
</html>
