<?php
declare(strict_types=1);

$adminNome = (string)($admin['nome'] ?? 'Admin');
$adminNivel = (string)($admin['nivel'] ?? 'admin');
$iniciais = '';

$partesNome = preg_split('/\s+/', trim($adminNome)) ?: [];
foreach ($partesNome as $parte) {
    $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    if (mb_strlen($iniciais) >= 2) {
        break;
    }
}
$iniciais = $iniciais ?: 'AD';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ContaFlow | Dashboard Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-page: #F5F7FA;
            --card-white: #FFFFFF;
            --sidebar-bg: #FFFFFF;
            --border-light: #E9EDF2;
            --text-primary: #1A2C3E;
            --text-secondary: #5B6E8C;
            --text-muted: #8A99B0;
            --accent-blue: #1E4B8F;
            --accent-blue-soft: #2C6E9E;
            --accent-green: #10B981;
            --accent-green-bg: #EFFAF5;
            --accent-yellow: #F59E0B;
            --accent-yellow-bg: #FFF7E8;
            --accent-red: #DC2626;
            --accent-red-bg: #FEF2F2;
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 8px 20px rgba(0, 0, 0, 0.03), 0 2px 6px rgba(0, 0, 0, 0.05);
            --radius-md: 12px;
            --radius-sm: 10px;
            --transition: all 0.2s ease;
        }

        body {
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            line-height: 1.4;
            overflow-x: hidden;
        }

        .app {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-light);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        .logo-area {
            padding: 28px 24px;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 24px;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1E4B8F 0%, #2C6E9E 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo-sub {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .nav {
            flex: 1;
            padding: 0 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 4px 0;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .nav-item.active {
            background: #F0F4F9;
            color: var(--accent-blue);
            font-weight: 600;
        }

        .nav-item:hover:not(.active) {
            background: #F8FAFE;
            color: var(--accent-blue-soft);
        }

        .sidebar-footer {
            padding: 16px 18px 22px;
            border-top: 1px solid var(--border-light);
        }

        .logout-link {
            display: block;
            text-align: center;
            text-decoration: none;
            background: #F8FAFE;
            color: var(--accent-blue);
            border: 1px solid var(--border-light);
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            width: calc(100% - 260px);
        }

        .topbar {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-title strong {
            display: block;
            font-size: 1.08rem;
        }

        .topbar-title span {
            color: var(--text-muted);
            font-size: 0.86rem;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .badge-role {
            background: #EFF4FB;
            color: var(--accent-blue);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: #E9EDF2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .dashboard-container {
            padding: 32px 32px 48px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 2rem;
            letter-spacing: -0.8px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--text-secondary);
            max-width: 700px;
            line-height: 1.7;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .card {
            background: var(--card-white);
            border-radius: var(--radius-md);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .card-title {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .card-trend {
            font-size: 0.78rem;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
        }

        .trend-success {
            color: #0E7B4E;
            background: var(--accent-green-bg);
        }

        .trend-warning {
            color: #B45F06;
            background: var(--accent-yellow-bg);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 28px;
        }

        .panel {
            background: var(--card-white);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }

        .panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--border-light);
        }

        .panel-header h2 {
            font-size: 1.06rem;
            margin-bottom: 6px;
        }

        .panel-header p {
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .panel-body {
            padding: 16px 22px 22px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            text-align: left;
            padding: 14px 12px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border-light);
            background: #FBFCFE;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .status-ativo {
            background: var(--accent-green-bg);
            color: #0E7B4E;
        }

        .status-teste {
            background: var(--accent-yellow-bg);
            color: #B45F06;
        }

        .status-bloqueado {
            background: var(--accent-red-bg);
            color: #B91C1C;
        }

        .alert-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-dot {
            width: 10px;
            height: 10px;
            min-width: 10px;
            border-radius: 50%;
            background: var(--accent-yellow);
            margin-top: 5px;
        }

        .alert-text strong {
            display: block;
            font-size: 0.94rem;
            margin-bottom: 4px;
        }

        .alert-text span {
            color: var(--text-secondary);
            font-size: 0.88rem;
            line-height: 1.55;
        }

        @media (max-width: 1180px) {
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .app {
                flex-direction: column;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .topbar {
                padding: 16px 18px;
            }

            .dashboard-container {
                padding: 24px 18px 32px;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .topbar-actions {
                gap: 10px;
            }

            .badge-role {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="logo-area">
            <div class="logo">ContaFlow</div>
            <div class="logo-sub">admin da plataforma</div>
        </div>

        <nav class="nav">
            <a href="<?= e(url('/admin/dashboard')) ?>" class="nav-item active">Dashboard</a>
            <a href="#" class="nav-item">Contadores</a>
            <a href="#" class="nav-item">Planos</a>
            <a href="#" class="nav-item">Assinaturas</a>
            <a href="#" class="nav-item">Suporte</a>
            <a href="#" class="nav-item">Financeiro</a>
            <a href="#" class="nav-item">Configurações</a>
        </nav>

        <div class="sidebar-footer">
            <a class="logout-link" href="<?= e(url('/logout')) ?>">Sair</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-title">
                <strong>Painel administrativo</strong>
                <span>Gestão central da plataforma SaaS</span>
            </div>

            <div class="topbar-actions">
                <div class="badge-role"><?= e($adminNivel) ?></div>
                <div class="avatar"><?= e($iniciais) ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <div class="page-header">
                <h1>Olá, <?= e($adminNome) ?></h1>
                <p>
                    Aqui você acompanha a saúde da plataforma, crescimento da base de contadores,
                    assinaturas, cobranças e operação do SaaS.
                </p>
            </div>

            <section class="cards-grid">
                <?php foreach ($metricas as $metrica): ?>
                    <?php
                    $trendClass = ($metrica['tipo'] ?? '') === 'warning'
                        ? 'trend-warning'
                        : 'trend-success';
                    ?>
                    <article class="card">
                        <div class="card-title"><?= e((string)$metrica['titulo']) ?></div>
                        <div class="card-value"><?= e((string)$metrica['valor']) ?></div>
                        <span class="card-trend <?= e($trendClass) ?>">
                            <?= e((string)$metrica['detalhe']) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Últimos contadores cadastrados</h2>
                        <p>Visão rápida da base recente da plataforma.</p>
                    </div>

                    <div class="panel-body">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Escritório</th>
                                        <th>Plano</th>
                                        <th>Status</th>
                                        <th>Cadastro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimosContadores as $contador): ?>
                                        <?php
                                        $status = mb_strtolower((string)$contador['status']);
                                        $statusClass = 'status-ativo';

                                        if ($status === 'teste') {
                                            $statusClass = 'status-teste';
                                        } elseif ($status === 'bloqueado') {
                                            $statusClass = 'status-bloqueado';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= e((string)$contador['nome']) ?></td>
                                            <td><?= e((string)$contador['plano']) ?></td>
                                            <td>
                                                <span class="status <?= e($statusClass) ?>">
                                                    <?= e((string)$contador['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= e((string)$contador['data']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Alertas da operação</h2>
                        <p>Pontos que exigem atenção rápida no admin.</p>
                    </div>

                    <div class="panel-body">
                        <?php foreach ($alertas as $alerta): ?>
                            <div class="alert-item">
                                <div class="alert-dot"></div>
                                <div class="alert-text">
                                    <strong><?= e((string)$alerta['titulo']) ?></strong>
                                    <span><?= e((string)$alerta['descricao']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>