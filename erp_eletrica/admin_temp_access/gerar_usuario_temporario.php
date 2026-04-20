<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$admin = app_require_admin();
$allowedLevels = app_allowed_temp_levels((string)$admin['nivel']);

$sql = "
    SELECT *
    FROM usuarios_temporarios
    WHERE admin_usuario_id = :admin_usuario_id
      AND revogado_em IS NULL
      AND valido_ate >= NOW()
    ORDER BY id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':admin_usuario_id' => (int)$admin['id']]);
$ativos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Usuário Temporário - ERP Elétrica</title>
    <style>
        :root {
            --bg: #bac6d6;
            --primary: #2f5488;
            --primary-dark: #1c3761;
            --accent: #f3c31b;
            --card: #ffffff;
            --field: #eef3fb;
            --border: #d7dfeb;
            --text: #1d355a;
            --muted: #6f7d95;
            --success: #1e8e5a;
            --danger: #c44141;
            --shadow: 0 18px 50px rgba(20, 39, 71, 0.18);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }
        .page {
            min-height: calc(100vh - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .layout {
            width: 100%;
            max-width: 1180px;
            display: grid;
            grid-template-columns: 560px 1fr;
            gap: 20px;
            align-items: start;
        }
        .card {
            border-radius: 24px;
            background: #fff;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .hero { background: var(--primary); padding: 26px 22px 22px; text-align: center; }
        .hero img { max-width: 260px; width: 100%; height: auto; }
        .accent-line { height: 6px; background: var(--accent); }
        .body { padding: 30px 28px 28px; }
        .title { text-align:center; font-size:1.9rem; font-weight:900; color:var(--primary-dark); margin-bottom:8px; }
        .subtitle { text-align:center; color:var(--muted); line-height:1.55; margin-bottom:26px; }
        .grid-two { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .field { margin-bottom: 14px; }
        .field label {
            display:block; margin-bottom:8px; font-size:.9rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--primary-dark);
        }
        .field input, .field select, .field textarea {
            width:100%; min-height:58px; border-radius:12px; border:1px solid var(--border); background:var(--field); padding:14px 16px; font-size:1rem; outline:none;
        }
        .field textarea { min-height: 110px; resize: vertical; }
        .field input[readonly] { color: var(--primary-dark); font-weight: 700; }
        .divider { height: 1px; background: var(--border); margin: 14px 0 18px; }
        .btn {
            width: 100%; height: 58px; border:none; border-radius:12px; font-size:1rem; font-weight:900; text-transform:uppercase; cursor:pointer;
        }
        .btn-primary { background: var(--primary); color:#fff; }
        .btn-primary:hover { background:#274774; }
        .btn-secondary { background: #ebf1f8; color: var(--primary-dark); }
        .btn-secondary:hover { background:#dfe8f4; }
        .actions-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:8px; }
        .status { display:none; margin-bottom:16px; padding:14px 16px; border-radius:12px; font-weight:700; line-height:1.5; }
        .status.show { display:block; }
        .status.info { background:#eef4ff; border:1px solid #d8e5ff; color:#2c4d81; }
        .status.success { background:#effaf4; border:1px solid #ccefd9; color:var(--success); }
        .status.error { background:#fff2f2; border:1px solid #ffd5d5; color:var(--danger); }
        .result-box {
            display:none; margin-top:18px; padding:18px; border-radius:16px; background:#f7fbff; border:1px solid #dbe6f2;
        }
        .result-box.show { display:block; }
        .result-code {
            margin-top:8px; display:inline-block; padding:12px 16px; border-radius:14px; background:var(--primary); color:#fff; font-size:1.45rem; font-weight:900; letter-spacing:1px;
        }
        .side-card .body { padding: 22px 22px 22px; }
        .side-title { font-size: 1.3rem; font-weight: 900; color: var(--primary-dark); margin-bottom: 14px; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e5edf5; text-align:left; vertical-align:top; }
        th { font-size: .84rem; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); }
        td strong { color: var(--primary-dark); }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; background:#ebf7ef; color:var(--success); font-weight:800; font-size:.8rem; }
        .pill.danger { background:#fff2f2; color:var(--danger); }
        .mini-btn {
            border:none; background:#ffe8e8; color:#a33a3a; font-weight:800; border-radius:10px; padding:10px 12px; cursor:pointer;
        }
        .top-link { display:flex; justify-content:flex-end; margin-bottom:10px; }
        .logout-link { color: var(--primary-dark); font-weight: 800; text-decoration:none; }
        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            body { padding: 14px; }
            .page { min-height: calc(100vh - 28px); }
            .body { padding: 24px 16px 20px; }
            .grid-two, .actions-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="layout">
        <section class="card">
            <div class="hero">
                <img src="assets/img/logo-centro-eletricista.png" alt="Centro do Eletricista" onerror="this.style.display='none'">
            </div>
            <div class="accent-line"></div>
            <div class="body">
                <div class="top-link"><a class="logout-link" href="admin_logout.php">Sair</a></div>
                <h1 class="title">GERAR USUÁRIO TEMPORÁRIO</h1>
                <p class="subtitle">Acesso restrito. Cada liberação criada aqui vale por <strong>30 minutos</strong>.</p>

                <div class="status" id="statusBox"></div>

                <div class="grid-two">
                    <div class="field">
                        <label>Sua unidade admin</label>
                        <input type="text" readonly value="<?= app_h(app_filial_label($admin['filial_id'])) ?>">
                    </div>
                    <div class="field">
                        <label>Nível do administrador</label>
                        <input type="text" readonly value="<?= app_h((string)$admin['nivel']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label>E-mail do administrador</label>
                    <input type="text" readonly value="<?= app_h((string)$admin['email']) ?>">
                </div>

                <div class="divider"></div>

                <form id="tempForm">
                    <div class="field">
                        <label for="nome_temporario">Nome do usuário temporário</label>
                        <input type="text" id="nome_temporario" name="nome_temporario" placeholder="Ex.: Apoio Caixa 01" required>
                    </div>

                    <div class="grid-two">
                        <div class="field">
                            <label for="nivel_temporario">Nível temporário</label>
                            <select id="nivel_temporario" name="nivel_temporario" required>
                                <?php foreach ($allowedLevels as $level): ?>
                                    <option value="<?= app_h($level) ?>"><?= app_h($level) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Validade</label>
                            <input type="text" readonly value="30 minutos">
                        </div>
                    </div>

                    <div class="field">
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="observacao" placeholder="Ex.: liberar acesso do gerente de plantão durante a troca de turno."></textarea>
                    </div>

                    <div class="actions-grid">
                        <button type="submit" class="btn btn-primary" id="btnGerar">Gerar acesso temporário</button>
                        <a href="acesso_temporario.php" class="btn btn-secondary" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">Abrir tela do temporário</a>
                    </div>
                </form>

                <div class="result-box" id="resultBox">
                    <div style="font-weight:800;color:var(--primary-dark);">Código gerado</div>
                    <div class="result-code" id="resultCode">-</div>
                    <div style="margin-top:12px;line-height:1.65;color:var(--muted);">
                        <div><strong>Nome:</strong> <span id="resultNome">-</span></div>
                        <div><strong>Nível:</strong> <span id="resultNivel">-</span></div>
                        <div><strong>Expira em:</strong> <span id="resultValidade">-</span></div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="card side-card">
            <div class="body">
                <h2 class="side-title">Acessos temporários ativos</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Nível</th>
                                <th>Código</th>
                                <th>Restante</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="activeTableBody">
                            <?php if (!$ativos): ?>
                                <tr><td colspan="5" style="color:var(--muted);">Nenhum acesso temporário ativo no momento.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ativos as $item): ?>
                                    <tr data-id="<?= (int)$item['id'] ?>">
                                        <td>
                                            <strong><?= app_h((string)$item['nome_temporario']) ?></strong><br>
                                            <span style="color:var(--muted);font-size:.88rem;">Expira: <?= app_h(app_format_dt((string)$item['valido_ate'])) ?></span>
                                        </td>
                                        <td><span class="pill"><?= app_h((string)$item['nivel_temporario']) ?></span></td>
                                        <td><strong><?= app_h((string)$item['codigo_acesso']) ?></strong></td>
                                        <td><?= app_remaining_minutes((string)$item['valido_ate']) ?> min</td>
                                        <td><button type="button" class="mini-btn" onclick="revokeTemp(<?= (int)$item['id'] ?>)">Revogar</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
const tempForm = document.getElementById('tempForm');
const statusBox = document.getElementById('statusBox');
const btnGerar = document.getElementById('btnGerar');
const resultBox = document.getElementById('resultBox');

function showStatus(message, type = 'info') {
    statusBox.className = 'status show ' + type;
    statusBox.textContent = message;
}

function upsertRow(item) {
    const tbody = document.getElementById('activeTableBody');
    const emptyRow = tbody.querySelector('td[colspan="5"]');
    if (emptyRow) {
        emptyRow.parentElement.remove();
    }

    const tr = document.createElement('tr');
    tr.dataset.id = item.id;
    tr.innerHTML = `
        <td>
            <strong>${item.nome_temporario}</strong><br>
            <span style="color:var(--muted);font-size:.88rem;">Expira: ${item.valido_ate_formatado}</span>
        </td>
        <td><span class="pill">${item.nivel_temporario}</span></td>
        <td><strong>${item.codigo_acesso}</strong></td>
        <td>${item.restante_minutos} min</td>
        <td><button type="button" class="mini-btn" onclick="revokeTemp(${item.id})">Revogar</button></td>
    `;
    tbody.prepend(tr);
}

tempForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    btnGerar.disabled = true;
    showStatus('Gerando acesso temporário...', 'info');

    try {
        const body = new FormData(tempForm);
        const response = await fetch('api/temp_user_generate.php', {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Não foi possível gerar o acesso temporário.');
        }

        showStatus(data.message || 'Acesso temporário criado com sucesso.', 'success');
        document.getElementById('resultCode').textContent = data.item.codigo_acesso;
        document.getElementById('resultNome').textContent = data.item.nome_temporario;
        document.getElementById('resultNivel').textContent = data.item.nivel_temporario;
        document.getElementById('resultValidade').textContent = data.item.valido_ate_formatado;
        resultBox.classList.add('show');
        tempForm.reset();
        upsertRow(data.item);
    } catch (error) {
        showStatus(error.message || 'Falha ao gerar o acesso temporário.', 'error');
    } finally {
        btnGerar.disabled = false;
    }
});

async function revokeTemp(id) {
    if (!confirm('Deseja revogar este acesso temporário agora?')) {
        return;
    }

    try {
        const body = new FormData();
        body.append('id', id);

        const response = await fetch('api/temp_user_revoke.php', {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Não foi possível revogar o acesso.');
        }

        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            row.remove();
        }

        showStatus(data.message || 'Acesso temporário revogado.', 'success');
    } catch (error) {
        showStatus(error.message || 'Falha ao revogar o acesso.', 'error');
    }
}
</script>
</body>
</html>
