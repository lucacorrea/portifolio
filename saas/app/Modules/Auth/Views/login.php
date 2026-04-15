<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ContaFlow | Login da Plataforma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-page: #F5F7FA;
            --bg-soft: #EEF3F9;
            --card-white: #FFFFFF;
            --border-light: #E9EDF2;
            --text-primary: #1A2C3E;
            --text-secondary: #5B6E8C;
            --text-muted: #8A99B0;
            --accent-blue: #1E4B8F;
            --accent-blue-soft: #2C6E9E;
            --accent-green: #10B981;
            --accent-green-bg: #EFFAF5;
            --accent-red: #DC2626;
            --accent-red-bg: #FEF2F2;
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 12px 30px rgba(0, 0, 0, 0.05), 0 3px 10px rgba(0, 0, 0, 0.04);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --transition: all 0.2s ease;
        }

        body {
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(30, 75, 143, 0.10), transparent 28%),
                radial-gradient(circle at bottom right, rgba(44, 110, 158, 0.10), transparent 26%),
                linear-gradient(135deg, var(--bg-page) 0%, var(--bg-soft) 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
        }

        .hero {
            padding: 52px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-soft) 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }

        .brand-text strong {
            display: block;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .brand-text span {
            display: block;
            margin-top: 2px;
            color: var(--text-muted);
            font-size: 0.92rem;
        }

        .hero-content {
            max-width: 560px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.78);
            border: 1px solid var(--border-light);
            border-radius: 999px;
            padding: 8px 14px;
            color: var(--accent-blue);
            font-weight: 600;
            font-size: 0.86rem;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .hero-title {
            font-size: 3rem;
            line-height: 1.06;
            letter-spacing: -1.4px;
            margin-bottom: 18px;
        }

        .hero-title .highlight {
            color: var(--accent-blue);
        }

        .hero-description {
            font-size: 1.02rem;
            line-height: 1.75;
            color: var(--text-secondary);
            max-width: 520px;
            margin-bottom: 30px;
        }

        .hero-list {
            display: grid;
            gap: 14px;
            max-width: 540px;
        }

        .hero-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            background: rgba(255,255,255,0.75);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: var(--shadow-sm);
        }

        .hero-item-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 12px;
            background: #EEF4FB;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .hero-item-text strong {
            display: block;
            font-size: 0.96rem;
            margin-bottom: 4px;
        }

        .hero-item-text span {
            color: var(--text-secondary);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .hero-footer {
            margin-top: 32px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .login-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: var(--card-white);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 34px 30px 30px;
        }

        .login-header {
            margin-bottom: 26px;
        }

        .login-header h1 {
            font-size: 1.9rem;
            letter-spacing: -0.8px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .alert {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 0.93rem;
            line-height: 1.5;
            border: 1px solid transparent;
        }

        .alert-error {
            background: var(--accent-red-bg);
            color: var(--accent-red);
            border-color: #FECACA;
        }

        .alert-success {
            background: var(--accent-green-bg);
            color: #047857;
            border-color: #BBF7D0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            height: 52px;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 0 16px;
            font-size: 0.96rem;
            color: var(--text-primary);
            background: #FBFCFE;
            outline: none;
            transition: var(--transition);
        }

        .form-input:focus {
            border-color: var(--accent-blue);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(30, 75, 143, 0.08);
        }

        .btn-submit {
            width: 100%;
            border: none;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-soft) 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            margin-top: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .login-note {
            margin-top: 18px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        @media (max-width: 1024px) {
            .page {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 34px 24px 8px;
            }

            .hero-title {
                font-size: 2.3rem;
            }

            .login-side {
                padding: 24px;
                padding-top: 8px;
            }
        }

        @media (max-width: 640px) {
            .hero {
                padding: 26px 18px 0;
            }

            .login-side {
                padding: 18px;
            }

            .login-card {
                padding: 24px 18px;
                border-radius: 20px;
            }

            .hero-title {
                font-size: 1.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="hero">
            <div>
                <div class="brand">
                    <div class="brand-icon">C</div>
                    <div class="brand-text">
                        <strong>ContaFlow</strong>
                        <span>Painel administrativo da plataforma</span>
                    </div>
                </div>

                <div class="hero-content">
                    <div class="hero-badge">
                        <span>●</span>
                        <span>Área Admin do SaaS</span>
                    </div>

                    <h2 class="hero-title">
                        Controle total da sua
                        <span class="highlight">plataforma contábil</span>
                    </h2>

                    <p class="hero-description">
                        Gerencie contadores, planos, assinaturas, suporte e faturamento da plataforma
                        em um painel centralizado, moderno e preparado para crescimento.
                    </p>

                    <div class="hero-list">
                        <div class="hero-item">
                            <div class="hero-item-icon">01</div>
                            <div class="hero-item-text">
                                <strong>Gestão de contadores</strong>
                                <span>Cadastre, acompanhe status, plano contratado e evolução da base.</span>
                            </div>
                        </div>

                        <div class="hero-item">
                            <div class="hero-item-icon">02</div>
                            <div class="hero-item-text">
                                <strong>Assinaturas e cobranças</strong>
                                <span>Controle vencimentos, pagamentos e receita recorrente.</span>
                            </div>
                        </div>

                        <div class="hero-item">
                            <div class="hero-item-icon">03</div>
                            <div class="hero-item-text">
                                <strong>Suporte e operação</strong>
                                <span>Centralize tickets e monitore a operação com mais segurança.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hero-footer">
                Estrutura separada corretamente entre <strong>Admin da plataforma</strong> e <strong>área do Contador</strong>.
            </div>
        </section>

        <section class="login-side">
            <div class="login-card">
                <div class="login-header">
                    <h1>Entrar</h1>
                    <p>Faça login para acessar o painel administrativo da plataforma.</p>
                </div>

                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($flashSuccess)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form action="<?= htmlspecialchars(url('/login'), ENT_QUOTES, 'UTF-8') ?>" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="email">E-mail</label>
                        <input
                            class="form-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="seuemail@empresa.com"
                            value="<?= htmlspecialchars((string) ($oldEmail ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="senha">Senha</label>
                        <input
                            class="form-input"
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Digite sua senha"
                            required
                        >
                    </div>

                    <button class="btn-submit" type="submit">
                        Acessar painel
                    </button>
                </form>

                <div class="login-note">
                    ContaFlow SaaS • acesso restrito ao time administrativo
                </div>
            </div>
        </section>
    </div>
</body>
</html>