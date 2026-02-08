<?php

declare(strict_types=1);
session_start();

/* Login */
if (empty($_SESSION['usuario_logado'])) {
    header('Location: ../../index.php');
    exit;
}

/* ADMIN */
if (!in_array('ADMIN', $_SESSION['perfis'] ?? [], true)) {
    header('Location: ../operador/index.php');
    exit;
}

require_once '../../assets/php/conexao.php';

/* Helper */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* CONFIG */
$TABELA = 'localidades'; // <<< MUDE se a sua tabela tiver outro nome

$msgErro = '';
$msgSucesso = '';

/* Ações (toggle / excluir) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msgErro = 'Token de segurança inválido.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $msgErro = 'ID inválido.';
        } else {
            try {
                if ($acao === 'toggle') {
                    // inverte ativo (1->0, 0->1)
                    $st = $pdo->prepare("UPDATE {$TABELA} SET ativo = IF(ativo=1,0,1), atualizado_em = NOW() WHERE id = :id");
                    $st->execute([':id' => $id]);
                    $msgSucesso = 'Status atualizado com sucesso.';
                } elseif ($acao === 'excluir') {
                    $st = $pdo->prepare("DELETE FROM {$TABELA} WHERE id = :id");
                    $st->execute([':id' => $id]);
                    $msgSucesso = 'Registro excluído com sucesso.';
                } else {
                    $msgErro = 'Ação inválida.';
                }
            } catch (Throwable $e) {
                error_log("Erro em localidades.php: " . $e->getMessage());
                $msgErro = 'Erro ao executar ação. Verifique o error_log.';
            }
        }
    }
}

/* Buscar registros */
try {
    $sql = "
    SELECT id, feira_id, nome, ativo, observacao, criado_em, atualizado_em
    FROM {$TABELA}
    ORDER BY id DESC
  ";
    $localidades = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Erro ao listar localidades: " . $e->getMessage());
    $localidades = [];
    $msgErro = $msgErro ?: 'Erro ao carregar a lista.';
}
?>
>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SIGRelatórios Feira do Produtor — Localidades</title>

    <link rel="stylesheet" href="../../vendors/feather/feather.css">
    <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../vendors/css/vendor.bundle.base.css">

    <link rel="stylesheet" href="../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" type="text/css" href="../../js/select.dataTables.min.css">

    <link rel="stylesheet" href="../../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../../images/3.png" />

    <style>
        ul .nav-link:hover {
            color: blue !important;
        }

        .nav-link {
            color: black !important;
        }

        .sidebar .sub-menu .nav-item .nav-link {
            margin-left: -35px !important;
        }

        .sidebar .sub-menu li {
            list-style: none !important;
        }

        .form-control {
            height: 42px;
        }

        .form-group label {
            font-weight: 600;
        }

        .sig-flash-wrap {
            position: fixed;
            top: 78px;
            right: 18px;
            left: auto;
            width: min(420px, calc(100vw - 36px));
            z-index: 9999;
            pointer-events: none;
        }

        .sig-toast.alert {
            pointer-events: auto;
            border: 0 !important;
            border-left: 6px solid !important;
            border-radius: 14px !important;
            padding: 10px 12px !important;
            box-shadow: 0 10px 28px rgba(0, 0, 0, .10) !important;
            font-size: 13px !important;
            margin-bottom: 10px !important;
            opacity: 0;
            transform: translateX(10px);
            animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
        }

        .sig-toast--success {
            background: #f1fff6 !important;
            border-left-color: #22c55e !important;
        }

        .sig-toast--danger {
            background: #fff1f2 !important;
            border-left-color: #ef4444 !important;
        }

        .sig-toast__row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .sig-toast__icon i {
            font-size: 16px;
            margin-top: 2px;
        }

        .sig-toast__title {
            font-weight: 800;
            margin-bottom: 1px;
            line-height: 1.1;
        }

        .sig-toast__text {
            margin: 0;
            line-height: 1.25;
        }

        .sig-toast .close {
            opacity: .55;
            font-size: 18px;
            line-height: 1;
            padding: 0 6px;
        }

        .sig-toast .close:hover {
            opacity: 1;
        }

        @keyframes sigToastIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes sigToastOut {
            to {
                opacity: 0;
                transform: translateX(12px);
                visibility: hidden;
            }
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
                <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../images/3.png" alt="logo" /></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>

                <ul class="navbar-nav mr-lg-2">
                    <li class="nav-item nav-search d-none d-lg-block"></li>
                </ul>

                <ul class="navbar-nav navbar-nav-right"></ul>

                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>

        <?php if ($msg || $err): ?>
            <div class="sig-flash-wrap">
                <?php if ($msg): ?>
                    <div class="alert sig-toast sig-toast--success alert-dismissible" role="alert">
                        <div class="sig-toast__row">
                            <div class="sig-toast__icon"><i class="ti-check"></i></div>
                            <div>
                                <div class="sig-toast__title">Tudo certo!</div>
                                <p class="sig-toast__text"><?= h($msg) ?></p>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($err): ?>
                    <div class="alert sig-toast sig-toast--danger alert-dismissible" role="alert">
                        <div class="sig-toast__row">
                            <div class="sig-toast__icon"><i class="ti-alert"></i></div>
                            <div>
                                <div class="sig-toast__title">Atenção!</div>
                                <p class="sig-toast__text"><?= h($err) ?></p>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="container-fluid page-body-wrapper">

            <div id="right-sidebar" class="settings-panel">
                <i class="settings-close ti-close"></i>
                <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab" aria-controls="todo-section" aria-expanded="true">TO DO LIST</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab" aria-controls="chats-section">CHATS</a>
                    </li>
                </ul>
            </div>

            <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
                <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                    <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
                    <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../images/3.png" alt="logo" /></a>
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
                                <a class="dropdown-item" href="../../controle/auth/logout.php">
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

            <div class="main-panel">
                <div class="content-wrapper">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3 class="font-weight-bold">Localidades</h3>
                            <h6 class="font-weight-normal mb-0">Cadastre e gerencie Comunidades (feira 1/2) e Bairros (feira 3).</h6>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">

                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <h4 class="card-title mb-0">Lista de Localidades</h4>
                                            <p class="card-description mb-0">Mostrando <?= (int)count($localidades) ?> registro(s).</p>
                                        </div>

                                        <a href="./adicionarLocalidade.php" class="btn btn-primary btn-sm mt-2 mt-md-0">
                                            <i class="ti-plus"></i> Adicionar
                                        </a>
                                    </div>

                                    <?php if (!empty($msgErro)): ?>
                                        <div class="alert alert-danger mt-3 mb-0"><?= h($msgErro) ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($msgSucesso)): ?>
                                        <div class="alert alert-success mt-3 mb-0"><?= h($msgSucesso) ?></div>
                                    <?php endif; ?>

                                    <div class="table-responsive pt-3">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th style="width: 90px;">ID</th>
                                                    <th>Nome</th>
                                                    <th style="width: 150px;">Tipo</th>
                                                    <th style="width: 110px;">Feira</th>
                                                    <th style="width: 160px;">Status</th>
                                                    <th style="min-width: 260px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($localidades)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-4">
                                                            Nenhuma localidade cadastrada ainda.
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($localidades as $l): ?>
                                                        <?php
                                                        $id = (int)($l['id'] ?? 0);
                                                        $f  = (int)($l['feira_id'] ?? 0);

                                                        $ativoBool = (int)($l['ativo'] ?? 0) === 1;
                                                        $badgeClass = $ativoBool ? 'badge-success' : 'badge-danger';
                                                        $badgeText  = $ativoBool ? 'Ativo' : 'Inativo';

                                                        $tipoLabel = ($f === 3) ? 'Bairro' : 'Comunidade';
                                                        $obs = trim((string)($l['observacao'] ?? ''));
                                                        ?>
                                                        <tr>
                                                            <td><?= $id ?></td>

                                                            <td>
                                                                <div class="font-weight-bold"><?= h($l['nome'] ?? '') ?></div>
                                                                <?php if ($obs !== ''): ?>
                                                                    <small class="text-muted"><?= h($obs) ?></small>
                                                                <?php endif; ?>
                                                            </td>

                                                            <td><?= h($tipoLabel) ?></td>
                                                            <td><?= $f ?></td>

                                                            <td>
                                                                <label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label>
                                                            </td>

                                                            <td>
                                                                <div class="acoes-wrap" style="display:flex; gap:8px; flex-wrap:wrap;">

                                                                    <form method="post" class="m-0">
                                                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                                        <input type="hidden" name="acao" value="toggle">
                                                                        <input type="hidden" name="id" value="<?= $id ?>">
                                                                        <button type="submit" class="btn btn-outline-warning btn-xs"
                                                                            onclick="return confirm('Deseja <?= $ativoBool ? 'DESATIVAR' : 'ATIVAR' ?> esta localidade?');">
                                                                            <i class="ti-power-off"></i> <?= $ativoBool ? 'Desativar' : 'Ativar' ?>
                                                                        </button>
                                                                    </form>

                                                                    <form method="post" class="m-0">
                                                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                                        <input type="hidden" name="acao" value="excluir">
                                                                        <input type="hidden" name="id" value="<?= $id ?>">
                                                                        <button type="submit" class="btn btn-outline-danger btn-xs"
                                                                            onclick="return confirm('Tem certeza que deseja EXCLUIR esta localidade?');">
                                                                            <i class="ti-trash"></i> Excluir
                                                                        </button>
                                                                    </form>

                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <footer class="footer">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                        <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
                            © <?= date('Y') ?> SIGRelatórios —
                            <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
                            Todos os direitos reservados.
                        </span>
                    </div>
                </footer>
            </div>


            <script>
                (function() {
                    const tipo = document.getElementById('tipo');
                    const wrapFeira = document.getElementById('wrapFeira');
                    const feiraSelect = document.getElementById('feira_id');

                    function update() {
                        const t = (tipo?.value || '');
                        const isComunidade = t === 'comunidade';

                        // Mostrar/ocultar
                        wrapFeira.style.display = isComunidade ? '' : 'none';

                        // Required só para comunidade
                        if (isComunidade) {
                            feiraSelect.setAttribute('required', 'required');
                        } else {
                            feiraSelect.removeAttribute('required');
                            feiraSelect.value = ''; // limpa
                        }
                    }

                    if (tipo) {
                        tipo.addEventListener('change', update);
                        // inicial
                        update();
                    }
                })();
            </script>


        </div>
    </div>

    <script src="../../vendors/js/vendor.bundle.base.js"></script>
    <script src="../../vendors/chart.js/Chart.min.js"></script>
    <script src="../../js/off-canvas.js"></script>
    <script src="../../js/hoverable-collapse.js"></script>
    <script src="../../js/template.js"></script>
    <script src="../../js/settings.js"></script>
    <script src="../../js/todolist.js"></script>
    <script src="../../js/dashboard.js"></script>
    <script src="../../js/Chart.roundedBarCharts.js"></script>
</body>

</html>