<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$tempAuth = $_SESSION['temp_auth'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Temporário - ERP Elétrica</title>
    <style>
        :root {
            --bg:#bac6d6; --primary:#2f5488; --primary-dark:#1c3761; --accent:#f3c31b; --card:#fff; --field:#eef3fb; --border:#d7dfeb; --text:#1d355a; --muted:#6f7d95; --success:#1e8e5a; --danger:#c44141; --shadow:0 18px 50px rgba(20,39,71,.18);
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:"Segoe UI",Arial,sans-serif; background:var(--bg); color:var(--text); padding:20px; }
        .page { min-height:calc(100vh - 40px); display:flex; align-items:center; justify-content:center; }
        .card { width:100%; max-width:560px; background:#fff; border-radius:24px; overflow:hidden; box-shadow:var(--shadow); }
        .hero { background:var(--primary); padding:26px 22px 22px; text-align:center; }
        .hero img { max-width:260px; width:100%; height:auto; }
        .accent-line { height:6px; background:var(--accent); }
        .body { padding:30px 28px 26px; }
        .title { text-align:center; font-size:1.8rem; font-weight:900; color:var(--primary-dark); margin-bottom:8px; }
        .subtitle { text-align:center; color:var(--muted); margin-bottom:24px; line-height:1.55; }
        .field { margin-bottom:14px; }
        .field label { display:block; margin-bottom:8px; font-size:.9rem; font-weight:800; color:var(--primary-dark); text-transform:uppercase; }
        .field input { width:100%; height:58px; border-radius:12px; border:1px solid var(--border); background:var(--field); padding:0 16px; font-size:1.2rem; outline:none; text-transform:uppercase; letter-spacing:1px; }
        .btn { width:100%; height:58px; border:none; border-radius:12px; font-size:1rem; font-weight:900; text-transform:uppercase; cursor:pointer; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-secondary { background:#ebf1f8; color:var(--primary-dark); margin-top:12px; }
        .status { display:none; margin-bottom:16px; padding:14px 16px; border-radius:12px; font-weight:700; line-height:1.5; }
        .status.show { display:block; }
        .status.info { background:#eef4ff; border:1px solid #d8e5ff; color:#2c4d81; }
        .status.success { background:#effaf4; border:1px solid #ccefd9; color:var(--success); }
        .status.error { background:#fff2f2; border:1px solid #ffd5d5; color:var(--danger); }
        .session-box { margin-bottom:18px; padding:16px; border-radius:14px; background:#f8fbff; border:1px solid #dde7f2; }
        .session-box strong { display:block; margin-bottom:6px; color:var(--primary-dark); }
        .session-box span { color:var(--muted); line-height:1.6; }
    </style>
</head>
<body>
<div class="page">
    <main class="card">
        <div class="hero">
            <img src="assets/img/logo-centro-eletricista.png" alt="Centro do Eletricista" onerror="this.style.display='none'">
        </div>
        <div class="accent-line"></div>
        <div class="body">
            <h1 class="title">ACESSO TEMPORÁRIO</h1>
            <p class="subtitle">Use o código gerado pelo administrador para entrar com o nível temporário liberado.</p>

            <?php if (is_array($tempAuth)): ?>
                <div class="session-box">
                    <strong>Sessão temporária ativa</strong>
                    <span>
                        Usuário: <?= app_h((string)($tempAuth['nome_temporario'] ?? '')) ?><br>
                        Nível: <?= app_h((string)($tempAuth['nivel_temporario'] ?? '')) ?><br>
                        Válido até: <?= app_h(app_format_dt((string)($tempAuth['valido_ate'] ?? ''))) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="status" id="statusBox"></div>

            <form id="tempLoginForm">
                <div class="field">
                    <label for="codigo_acesso">Código temporário</label>
                    <input type="text" id="codigo_acesso" name="codigo_acesso" placeholder="TMP-ABC123" required>
                </div>
                <button type="submit" class="btn btn-primary">Validar código</button>
            </form>

            <?php if (is_array($tempAuth)): ?>
                <a class="btn btn-secondary" style="display:flex;align-items:center;justify-content:center;text-decoration:none;" href="temp_logout.php">Encerrar acesso temporário</a>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
const form = document.getElementById('tempLoginForm');
const statusBox = document.getElementById('statusBox');

function showStatus(message, type = 'info') {
    statusBox.className = 'status show ' + type;
    statusBox.textContent = message;
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = new FormData(form);
    showStatus('Validando código temporário...', 'info');

    try {
        const response = await fetch('api/temp_user_validate.php', {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Código inválido.');
        }
        showStatus(data.message || 'Código validado com sucesso.', 'success');
        window.location.reload();
    } catch (error) {
        showStatus(error.message || 'Falha ao validar o código temporário.', 'error');
    }
});
</script>
</body>
</html>
