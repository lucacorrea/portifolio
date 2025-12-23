<?php

declare(strict_types=1);
session_start();

if (empty($_SESSION['usuario_logado'])) {
    header('Location: ../../../index.php');
    exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!in_array('ADMIN', $perfis, true)) {
    header('Location: ../../operador/index.php');
    exit;
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$feiraId = 1;

$nome = '';
$ativo = '1';
$ordem = '';
$descricao = '';

require '../../../assets/php/conexao.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $ativo = (string)($_POST['ativo'] ?? '1');
    $ordem = trim((string)($_POST['ordem'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $err = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif ($nome === '' || mb_strlen($nome) < 2) {
        $err = 'Informe um nome válido para a categoria.';
    } elseif (!in_array($ativo, ['0', '1'], true)) {
        $err = 'Status inválido.';
    }

    $ordemInt = null;
    if (!$err && $ordem !== '') {
        if (!preg_match('/^\d+$/', $ordem)) {
            $err = 'A ordem deve ser um número inteiro (ou deixe em branco).';
        } else {
            $ordemInt = (int)$ordem;
        }
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM categorias WHERE feira_id = :feira AND nome = :nome LIMIT 1");
            $check->bindValue(':feira', $feiraId, PDO::PARAM_INT);
            $check->bindValue(':nome', $nome, PDO::PARAM_STR);
            $check->execute();
            if ($check->fetchColumn()) {
                $pdo->rollBack();
                $err = 'Já existe uma categoria com esse nome.';
            } else {
                $sql = "INSERT INTO categorias (feira_id, nome, ativo)
        VALUES (:feira, :nome, :ativo)";
                $ins = $pdo->prepare($sql);
                $ins->bindValue(':feira', $feiraId, PDO::PARAM_INT);
                $ins->bindValue(':nome', $nome, PDO::PARAM_STR);
                $ins->bindValue(':ativo', (int)$ativo, PDO::PARAM_INT);
                $ins->execute();


                if ($descricao === '') $ins->bindValue(':descricao', null, PDO::PARAM_NULL);
                else $ins->bindValue(':descricao', $descricao, PDO::PARAM_STR);

                if ($ordemInt === null) $ins->bindValue(':ordem', null, PDO::PARAM_NULL);
                else $ins->bindValue(':ordem', $ordemInt, PDO::PARAM_INT);

                $ins->bindValue(':ativo', (int)$ativo, PDO::PARAM_INT);
                $ins->execute();

                $pdo->commit();

                $_SESSION['flash_ok'] = "Categoria adicionada: {$nome}";
                header('Location: ./listaCategoria.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            $mysqlCode = (int)($e->errorInfo[1] ?? 0);

            if ($mysqlCode === 1062) {
                $err = 'Já existe uma categoria com esse nome.';
            } elseif ($mysqlCode === 1452) {
                $err = 'Feira não cadastrada no banco (tabela feiras). Rode o SQL de feiras (id=1 e id=2).';
            } elseif ($mysqlCode === 1146) {
                $err = 'Tabela "categorias" não existe. Rode o SQL das tabelas.';
            } elseif ($mysqlCode === 1054) {
                $err = 'Coluna inválida na tabela "categorias". Confira se seu SQL está igual ao padrão.';
            } else {
                $err = 'Não foi possível salvar a categoria agora.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'Não foi possível salvar a categoria agora.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SIGRelatórios Feira do Produtor — Adicionar Categoria</title>

    <link rel="stylesheet" href="../../../vendors/feather/feather.css">
    <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

    <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

    <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../../../images/3.png" />

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
                <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
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

            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">

                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="icon-grid menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-item active">
                        <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
                            <i class="ti-id-badge menu-icon"></i>
                            <span class="menu-title">Cadastros</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <div class="collapse show" id="feiraCadastros">
                            <style>
                                .sub-menu .nav-item .nav-link {
                                    color: black !important;
                                }

                                .sub-menu .nav-item .nav-link:hover {
                                    color: blue !important;
                                }
                            </style>

                            <ul class="nav flex-column sub-menu" style="background: white !important;">

                                <li class="nav-item">
                                    <a class="nav-link" href="./listaProduto.php">
                                        <i class="ti-clipboard mr-2"></i> Lista de Produtos
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="./listaCategoria.php">
                                        <i class="ti-layers mr-2"></i> Categorias
                                    </a>
                                </li>

                                <li class="nav-item active">
                                    <a class="nav-link" href="./adicionarCategoria.php" style="color:white !important; background: #231475C5 !important;">
                                        <i class="ti-plus mr-2"></i> Adicionar Categoria
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="./listaUnidade.php">
                                        <i class="ti-ruler-pencil mr-2"></i> Unidades
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="./listaProdutor.php">
                                        <i class="ti-user mr-2"></i> Produtores
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
                            <i class="ti-exchange-vertical menu-icon"></i>
                            <span class="menu-title">Movimento</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <div class="collapse" id="feiraMovimento">
                            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                                <li class="nav-item">
                                    <a class="nav-link" href="./lancamentos.php">
                                        <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="./fechamentoDia.php">
                                        <i class="ti-check-box mr-2"></i> Fechamento do Dia
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
                            <i class="ti-clipboard menu-icon"></i>
                            <span class="menu-title">Relatórios</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <div class="collapse text-black" id="feiraRelatorios">
                            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                                <li class="nav-item">
                                    <a class="nav-link" href="./relatorioFinanceiro.php">
                                        <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="./relatorioProdutos.php">
                                        <i class="ti-list mr-2"></i> Produtos Comercializados
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="./relatorioMensal.php">
                                        <i class="ti-calendar mr-2"></i> Resumo Mensal
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="./configRelatorio.php">
                                        <i class="ti-settings mr-2"></i> Configurar
                                    </a>
                                </li>
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

            <div class="main-panel">
                <div class="content-wrapper">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3 class="font-weight-bold">Adicionar Categoria</h3>
                            <h6 class="font-weight-normal mb-0">Crie os “tipos” de produto (ex.: Frutas, Hortaliças, Farinhas...).</h6>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">

                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <h4 class="card-title mb-0">Dados da Categoria</h4>
                                            <p class="card-description mb-0">Sem código/sigla — só nome e organização.</p>
                                        </div>

                                        <a href="./listaCategoria.php" class="btn btn-light btn-sm mt-2 mt-md-0">
                                            <i class="ti-arrow-left"></i> Voltar
                                        </a>
                                    </div>

                                    <hr>

                                    <form action="./adicionarCategoria.php" method="post" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <div class="row">

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Nome da Categoria *</label>
                                                    <input type="text" class="form-control" name="nome" value="<?= h($nome) ?>" placeholder="Ex.: Frutas, Hortaliças, Farinhas..." required>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Status *</label>
                                                    <select class="form-control" name="ativo" required>
                                                        <option value="1" <?= $ativo === '1' ? 'selected' : '' ?>>Ativo</option>
                                                        <option value="0" <?= $ativo === '0' ? 'selected' : '' ?>>Inativo</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Ordem (opcional)</label>
                                                    <input type="number" class="form-control" name="ordem" value="<?= h($ordem) ?>" placeholder="Ex.: 1, 2, 3...">
                                                    <small class="text-muted">Só para organizar a lista depois.</small>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label>Descrição (opcional)</label>
                                                    <textarea class="form-control" name="descricao" rows="3" placeholder="Ex.: Produtos frescos, de época, verduras, etc."><?= h($descricao) ?></textarea>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="d-flex flex-wrap" style="gap:8px;">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti-save mr-1"></i> Salvar
                                            </button>
                                            <button type="reset" class="btn btn-light">
                                                <i class="ti-close mr-1"></i> Limpar
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
                            <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
                            Todos os direitos reservados.
                        </span>
                    </div>
                </footer>
            </div>

        </div>
    </div>

    <script src="../../../vendors/js/vendor.bundle.base.js"></script>
    <script src="../../../vendors/chart.js/Chart.min.js"></script>
    <script src="../../../js/off-canvas.js"></script>
    <script src="../../../js/hoverable-collapse.js"></script>
    <script src="../../../js/template.js"></script>
    <script src="../../../js/settings.js"></script>
    <script src="../../../js/todolist.js"></script>
    <script src="../../../js/dashboard.js"></script>
    <script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>

</html>