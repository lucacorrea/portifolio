<?php

declare(strict_types=1);

/* =========================
   DEBUG (gera log na pasta do arquivo)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log');

/* =========================
   SESSION (cookie válido no site todo)
   ========================= */
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    } else {
        session_set_cookie_params(0, '/');
    }
    session_start();
}

/* =========================
   GUARD (logado + ADMIN)
   ========================= */
if (empty($_SESSION['usuario_logado'])) {
    header('Location: /index.php');
    exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];

if (!in_array('ADMIN', $perfis, true)) {
    header('Location: /painel/operador/index.php');
    exit;
}

/* =========================
   CONEXÃO (db(): PDO)
   ========================= */
$pathAbs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . "/assets/php/conexao.php";
$pathRel = __DIR__ . "/../../../assets/php/conexao.php";

try {
    if (is_file($pathAbs)) {
        require_once $pathAbs;
    } elseif (is_file($pathRel)) {
        require_once $pathRel;
    } else {
        throw new RuntimeException("Não encontrei conexao.php em: {$pathAbs} nem em: {$pathRel}");
    }

    if (!function_exists('db')) {
        throw new RuntimeException("Falha: função db() não existe no conexao.php");
    }

    $pdo = db();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException("Falha: db() não retornou uma instância de PDO");
    }
} catch (Throwable $e) {
    $fatal = $e->getMessage();
    echo "<h3>Erro ao iniciar a página</h3><pre>" . htmlspecialchars($fatal, ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}

/* =========================
   CSRF
   ========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* =========================
   Helpers
   ========================= */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$msg = null;
$err = null;

/* =========================
   SUBMIT (POST)
   ========================= */
$nome  = '';
$email = '';
$perfil = 'OPERADOR';
$ativo = 1;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($csrf, (string)$token)) {
            $err = "Falha de segurança (CSRF). Recarregue a página.";
        } else {
            $nome   = trim((string)($_POST['nome'] ?? ''));
            $email  = trim((string)($_POST['email'] ?? ''));
            $senha  = (string)($_POST['senha'] ?? '');
            $senha2 = (string)($_POST['senha2'] ?? '');
            $perfil = strtoupper(trim((string)($_POST['perfil'] ?? 'OPERADOR')));
            $ativo  = (int)($_POST['ativo'] ?? 1);

            if ($nome === '' || mb_strlen($nome) < 3) {
                $err = "Informe um nome válido (mínimo 3 caracteres).";
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = "Informe um e-mail válido.";
            } elseif (mb_strlen($senha) < 6) {
                $err = "A senha deve ter no mínimo 6 caracteres.";
            } elseif ($senha !== $senha2) {
                $err = "As senhas não conferem.";
            } elseif (!in_array($perfil, ['ADMIN', 'OPERADOR'], true)) {
                $err = "Perfil inválido.";
            } elseif (!in_array($ativo, [0, 1], true)) {
                $err = "Status inválido.";
            } else {
                // verifica se email já existe
                $st = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
                $st->execute([':email' => $email]);
                $existe = $st->fetchColumn();

                if ($existe) {
                    $err = "Já existe um usuário com esse e-mail.";
                } else {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                    // cria usuário
                    $st = $pdo->prepare("
            INSERT INTO usuarios (nome, email, senha_hash, ativo, criado_em)
            VALUES (:nome, :email, :senha_hash, :ativo, NOW())
          ");
                    $st->execute([
                        ':nome' => $nome,
                        ':email' => $email,
                        ':senha_hash' => $senhaHash,
                        ':ativo' => $ativo,
                    ]);

                    $novoId = (int)$pdo->lastInsertId();

                    // grava perfil na sessão? (não)
                    // grava perfil no banco? depende do seu modelo:
                    // Se você usa 'perfis' em outra tabela, adapte aqui.
                    // Por enquanto, se você guarda o perfil em uma coluna, descomente:
                    // $pdo->prepare("UPDATE usuarios SET perfil = :perfil WHERE id = :id")->execute([':perfil'=>$perfil, ':id'=>$novoId]);

                    $msg = "Usuário criado com sucesso! (ID: {$novoId})";
                    $nome = $email = '';
                    $perfil = 'OPERADOR';
                    $ativo = 1;
                }
            }
        }
    }
} catch (Throwable $e) {
    $err = "Erro ao salvar: " . $e->getMessage();
}

$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SIGRelatórios Admin</title>

    <!-- plugins:css -->
    <link rel="stylesheet" href="../../../vendors/feather/feather.css">
    <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

    <!-- inject:css -->
    <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../../../images/3.png" />

    <style>
        .sub-menu .nav-item .nav-link {
            color: black !important;
        }

        .sub-menu .nav-item .nav-link:hover {
            color: blue !important;
        }

        .card-title {
            margin-bottom: .25rem;
        }

        .card-description {
            margin-bottom: 1rem;
        }

        .form-control {
            border-radius: 10px;
            height: 44px;
        }

        textarea.form-control {
            height: auto;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .hint {
            font-size: 12px;
            opacity: .75;
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <!-- NAVBAR -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo mr-5" href="../index.php">SIGRelatórios</a>
                <a class="navbar-brand brand-logo-mini" href="../index.php"><img src="../../../images/3.png" alt="logo" /></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>

                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
                            <i class="ti-user"></i>
                            <span class="ml-1"><?= h($nomeTopo) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                            <a class="dropdown-item" href="../../logout.php">
                                <i class="ti-power-off text-primary"></i> Sair
                            </a>
                        </div>
                    </li>
                </ul>

                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>

        <div class="container-fluid page-body-wrapper">

            <!-- SIDEBAR -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="icon-grid menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-item"><a class="nav-link" href="../produtor/"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Feira do Produtor</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="../alternativa/"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Feira Alternativa</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="../mercado/"><i class="ti-home menu-icon"></i><span class="menu-title">Mercado Municipal</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="../relatorio/"><i class="ti-agenda menu-icon"></i><span class="menu-title">Relatórios</span></a></li>

                    <li class="nav-item active">
                        <a class="nav-link open" data-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
                            <i class="ti-user menu-icon"></i>
                            <span class="menu-title">Usuários</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="ui-basic">
                            <style>
                                .sub-menu .nav-item .nav-link {
                                    color: black !important;
                                }

                                .sub-menu .nav-item .nav-link:hover {

                                    color: blue !important;
                                }
                            </style>
                            <ul class="nav flex-column sub-menu " style=" background: white !important; ">
                                <li class="nav-item"> <a class="nav-link" href="./listaUser.php">Lista de Adicionados</a></li>
                                <li class="nav-item active"> <a class="nav-link" style="color:aliceblue !important;" href="#">Adicionar Usuários</a></li>

                            </ul>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
                            <i class="ti-headphone-alt menu-icon"></i>
                            <span class="menu-title">Suporte</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- MAIN -->
            <div class="main-panel">
                <div class="content-wrapper">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3 class="font-weight-bold">Adicionar usuário</h3>
                            <h6 class="font-weight-normal mb-0">Crie um novo usuário para acessar o sistema.</h6>
                        </div>
                    </div>

                    <?php if ($msg): ?>
                        <div class="alert alert-success"><?= h($msg) ?></div>
                    <?php endif; ?>
                    <?php if ($err): ?>
                        <div class="alert alert-danger"><?= h($err) ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title mb-0">Novo usuário</h4>
                                    <p class="card-description mb-3">Preencha os campos abaixo.</p>

                                    <form method="post" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                                        <div class="form-group">
                                            <label for="nome">Nome</label>
                                            <input id="nome" type="text" class="form-control" name="nome" value="<?= h($nome) ?>" required>
                                            <div class="hint">Ex.: João da Silva</div>
                                        </div>

                                        <div class="form-group">
                                            <label for="email">E-mail</label>
                                            <input id="email" type="email" class="form-control" name="email" value="<?= h($email) ?>" required>
                                            <div class="hint">Ex.: joao@email.com</div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="senha">Senha</label>
                                                <input id="senha" type="password" class="form-control" name="senha" minlength="6" required>
                                                <div class="hint">Mínimo 6 caracteres</div>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="senha2">Confirmar senha</label>
                                                <input id="senha2" type="password" class="form-control" name="senha2" minlength="6" required>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="perfil">Perfil</label>
                                                <select id="perfil" class="form-control" name="perfil">
                                                    <option value="OPERADOR" <?= $perfil === 'OPERADOR' ? 'selected' : '' ?>>OPERADOR</option>
                                                    <option value="ADMIN" <?= $perfil === 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="ativo">Status</label>
                                                <select id="ativo" class="form-control" name="ativo">
                                                    <option value="1" <?= (int)$ativo === 1 ? 'selected' : '' ?>>Ativo</option>
                                                    <option value="0" <?= (int)$ativo === 0 ? 'selected' : '' ?>>Inativo</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap" style="gap:10px;">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti-save mr-1"></i> Salvar usuário
                                            </button>

                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>


                    </div>

                </div>

                 <footer class="footer">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                        <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
                            © <?= date('Y') ?> SIGRelatórios —
                            <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">
                                lucascorrea.pro
                            </a>
                            . Todos os direitos reservados.
                        </span>

                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- plugins:js -->
    <script src="../../../vendors/js/vendor.bundle.base.js"></script>

    <!-- inject:js -->
    <script src="../../../js/off-canvas.js"></script>
    <script src="../../../js/template.js"></script>
    <script src="../../../js/settings.js"></script>
    <script src="../../../js/todolist.js"></script>
</body>

</html>