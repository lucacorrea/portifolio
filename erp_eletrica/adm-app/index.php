<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isLoggedIn = isset($_SESSION['usuario_id'])
    && (int) $_SESSION['usuario_id'] !== -1
    && strtolower((string) ($_SESSION['usuario_nivel'] ?? '')) === 'admin';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Administrador';

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
| Coloque sua logo neste caminho:
| assets/img/logo-centro-eletricista.png
|--------------------------------------------------------------------------
*/
$logoPath = 'assets/img/logo-centro-eletricista.png';

/*
|--------------------------------------------------------------------------
| UNIDADES
|--------------------------------------------------------------------------
| Se existir tabela filiais, ele tenta puxar do banco.
| Se não existir, usa as unidades fixas abaixo.
|--------------------------------------------------------------------------
*/
$filiais = [
    ['id' => 'matriz', 'nome' => 'Centro do Eletricista'],
    ['id' => 'loja-01', 'nome' => 'Loja 01'],
    ['id' => 'loja-02', 'nome' => 'Loja 02'],
];

$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($conexao) && $conexao instanceof PDO) {
    $db = $conexao;
}

if ($db instanceof PDO) {
    try {
        $stmt = $db->query("
            SELECT id, nome 
            FROM filiais 
            WHERE ativo = 1 
            ORDER BY nome ASC
        ");

        $filiaisBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($filiaisBanco)) {
            $filiais = $filiaisBanco;
        }
    } catch (Throwable $e) {
        // Se não existir tabela filiais, mantém as unidades fixas.
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Login - ERP Elétrica</title>

    <link rel="manifest" href="manifest.json">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="index.css">

    <meta name="theme-color" content="#2f5487">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>


<body>
    <main class="page-auth">
        <section class="auth-shell">

            <?php if (!$isLoggedIn): ?>

                <div class="login-card">
                    <div class="login-brand">
                        <div class="brand-logo-wrap">
                            <img
                                src="<?= h($logoPath) ?>"
                                alt="Centro do Eletricista"
                                class="brand-logo">
                        </div>
                    </div>

                    <div class="brand-line"></div>

                    <div class="login-body">
                        <div class="login-title-box">
                            <h1>LOGIN</h1>
                            <p>Identifique-se para continuar</p>
                        </div>

                        <form id="login-form" method="post" autocomplete="off">
                            <div class="form-group-ce">
                                <label for="login-unit">Unidade de acesso</label>

                                <select
                                    id="login-unit"
                                    name="filial_id"
                                    class="form-control-ce"
                                    required>
                                    <option value="">Selecionar unidade...</option>

                                    <?php foreach ($filiais as $filial): ?>
                                        <option value="<?= h($filial['id']) ?>">
                                            <?= h($filial['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group-ce">
                                <label for="email">ID ou e-mail corporativo</label>

                                <input
                                    type="text"
                                    id="email"
                                    name="email"
                                    class="form-control-ce"
                                    placeholder="Digite seu usuário ou e-mail"
                                    required
                                    autocomplete="username">
                            </div>

                            <div class="form-group-ce">
                                <label for="password">Senha técnica</label>

                                <div class="password-field">
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control-ce password-input"
                                        placeholder="Digite sua senha"
                                        required
                                        autocomplete="current-password">

                                    <button
                                        type="button"
                                        class="btn-password-toggle"
                                        id="toggle-password"
                                        aria-label="Mostrar ou ocultar senha">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-access">
                                <span>Confirmar acesso</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>

                            <div id="biometric-login" class="biometric-box">
                                <button
                                    type="button"
                                    class="btn-biometric"
                                    onclick="tryBiometricLogin()">
                                    <i class="fa-solid fa-fingerprint"></i>
                                    Desbloquear com biometria
                                </button>
                            </div>

                            <div id="login-error" class="login-error"></div>
                        </form>

                        <a href="admin_login.php" class="admin-link">
                            <i class="fa-solid fa-key"></i>
                            Área do Administrador
                        </a>

                        <div class="login-footer">
                            <p>Desenvolvido por L&amp;J Soluções Tecnológicas.</p>
                            <p>ERP Elétrica © <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <div class="dashboard-card">
                    <div class="dashboard-brand">
                        <div class="brand-logo-wrap">
                            <img
                                src="<?= h($logoPath) ?>"
                                alt="Centro do Eletricista"
                                class="brand-logo">
                        </div>
                    </div>

                    <div class="brand-line"></div>

                    <div class="dashboard-body">
                        <div class="dashboard-header">
                            <div>
                                <span>Painel Administrativo</span>
                                <h1><?= h($usuarioNome) ?></h1>
                                <p>Controle de acessos e autorizações</p>
                            </div>

                            <div class="dashboard-avatar">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                        </div>

                        <div class="quick-grid">
                            <article class="quick-card">
                                <i class="fa-solid fa-key"></i>
                                <strong>Códigos</strong>
                                <span>Autorizações rápidas</span>
                            </article>

                            <article class="quick-card">
                                <i class="fa-solid fa-user-clock"></i>
                                <strong>Temporário</strong>
                                <span>Acesso especial</span>
                            </article>
                        </div>

                        <ul class="nav nav-pills adm-tabs" id="admTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link active"
                                    id="tab-codes"
                                    data-bs-toggle="pill"
                                    data-bs-target="#codes-section"
                                    type="button"
                                    role="tab">
                                    <i class="fa-solid fa-key"></i>
                                    Códigos
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link"
                                    id="tab-logins"
                                    data-bs-toggle="pill"
                                    data-bs-target="#logins-section"
                                    type="button"
                                    role="tab">
                                    <i class="fa-solid fa-user-shield"></i>
                                    Logins
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content adm-tab-content">
                            <div
                                class="tab-pane fade show active"
                                id="codes-section"
                                role="tabpanel">
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fa-solid fa-lock"></i>

                                        <div>
                                            <h2>Gerar autorização</h2>
                                            <p>Crie um código único para liberar operações.</p>
                                        </div>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="code-type">Tipo de operação</label>

                                        <select id="code-type" class="form-control-ce">
                                            <option value="geral">Qualquer operação</option>
                                            <option value="sangria">Sangria</option>
                                            <option value="suprimento">Suprimento</option>
                                            <option value="desconto">Desconto</option>
                                            <option value="cancelamento">Cancelamento</option>
                                        </select>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="code-filial">Unidade destino</label>

                                        <select id="code-filial" class="form-control-ce">
                                            <?php foreach ($filiais as $filial): ?>
                                                <option value="<?= h($filial['id']) ?>">
                                                    <?= h($filial['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button
                                        type="button"
                                        onclick="generateCode()"
                                        class="btn-access">
                                        <span>Gerar código único</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>

                                    <div id="code-result" class="generated-area">
                                        <p>Código gerado</p>

                                        <div class="code-display" id="display-code">
                                            ------
                                        </div>

                                        <div class="action-row">
                                            <button
                                                type="button"
                                                class="btn-outline-ce"
                                                onclick="copyToClipboard('display-code')">
                                                <i class="fa-regular fa-copy"></i>
                                                Copiar
                                            </button>

                                            <button
                                                type="button"
                                                class="btn-whatsapp"
                                                onclick="shareToWhatsApp('code')">
                                                <i class="fa-brands fa-whatsapp"></i>
                                                Enviar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="tab-pane fade"
                                id="logins-section"
                                role="tabpanel">
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fa-solid fa-user-clock"></i>

                                        <div>
                                            <h2>Login temporário</h2>
                                            <p>Crie um acesso master com expiração automática.</p>
                                        </div>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="temp-time">Tempo de acesso</label>

                                        <select id="temp-time" class="form-control-ce">
                                            <option value="30">30 minutos</option>
                                            <option value="60" selected>1 hora</option>
                                            <option value="240">4 horas</option>
                                            <option value="480">8 horas</option>
                                            <option value="720">12 horas</option>
                                        </select>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="temp-filial">Unidade filial</label>

                                        <select id="temp-filial" class="form-control-ce">
                                            <?php foreach ($filiais as $filial): ?>
                                                <option value="<?= h($filial['id']) ?>">
                                                    <?= h($filial['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button
                                        type="button"
                                        onclick="generateTempLogin()"
                                        class="btn-access btn-access-purple">
                                        <span>Criar acesso especial</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>

                                    <div id="temp-result" class="generated-area generated-login">
                                        <div class="info-alert">
                                            <i class="fa-solid fa-circle-info"></i>
                                            Este acesso libera permissões administrativas temporárias.
                                        </div>

                                        <div class="login-result-row">
                                            <span>Usuário</span>
                                            <strong id="display-user">-</strong>
                                        </div>

                                        <div class="login-result-row">
                                            <span>Senha</span>
                                            <strong id="display-pass">-</strong>
                                        </div>

                                        <div class="login-result-row">
                                            <span>Válido até</span>
                                            <strong id="display-time">-</strong>
                                        </div>

                                        <div class="action-row">
                                            <button
                                                type="button"
                                                class="btn-outline-ce"
                                                onclick="copyLogin()">
                                                <i class="fa-regular fa-copy"></i>
                                                Copiar tudo
                                            </button>

                                            <button
                                                type="button"
                                                class="btn-whatsapp"
                                                onclick="shareToWhatsApp('login')">
                                                <i class="fa-brands fa-whatsapp"></i>
                                                WhatsApp
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card biometric-section">
                                    <div class="section-title">
                                        <i class="fa-solid fa-fingerprint"></i>

                                        <div>
                                            <h2>Biometria / FaceID</h2>
                                            <p>Vincule este aparelho para acessos rápidos.</p>
                                        </div>
                                    </div>

                                    <div id="biometrics-status" class="info-alert warning">
                                        <i class="fa-solid fa-circle-info"></i>
                                        Biometria não configurada.
                                    </div>

                                    <button
                                        type="button"
                                        onclick="registerBiometrics()"
                                        id="btn-register-bio"
                                        class="btn-outline-warning-ce">
                                        <i class="fa-solid fa-plus-circle"></i>
                                        Configurar neste celular
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="logout-btn" onclick="logout()">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Sair da conta ADM
                        </button>

                        <div class="login-footer dashboard-footer">
                            <p>Desenvolvido por L&amp;J Soluções Tecnológicas.</p>
                            <p>ERP Elétrica © <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </section>
    </main>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
    </script>

    <script src="script.js"></script>
</body>

</html>