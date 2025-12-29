<?php

declare(strict_types=1);

/* =========================
   DEBUG (gera log na pasta do arquivo)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');      // hosting: não exibir
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_error.log'); // pode falhar sem permissão, mas não quebra

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
function fmtData($dt): string
{
    if (!$dt) return '-';
    $ts = strtotime((string)$dt);
    if (!$ts) return h($dt);
    return date('d/m/Y H:i', $ts);
}

$msg = null;
$err = null;

/* =========================
   AÇÕES (POST)
   ========================= */
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($csrf, (string)$token)) {
            $err = "Falha de segurança (CSRF). Recarregue a página.";
        } else {
            $acao = (string)($_POST['acao'] ?? '');
            $id   = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                $err = "Usuário inválido.";
            } else {
                if ($acao === 'toggle') {
                    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = IF(ativo=1,0,1) WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $msg = "Status do usuário atualizado!";
                } elseif ($acao === 'excluir') {
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $msg = "Usuário excluído com sucesso!";
                }
            }
        }
    }
} catch (Throwable $e) {
    $err = "Erro ao executar ação: " . $e->getMessage();
}

/* =========================
   LISTAGEM
   ========================= */
$usuarios = [];
try {
    $stmt = $pdo->query("
    SELECT id, nome, email, ativo, ultimo_login_em, criado_em
    FROM usuarios
    ORDER BY id DESC
  ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $err = "Erro ao carregar usuários: " . $e->getMessage();
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

    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

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

        .table td,
        .table th {
            vertical-align: middle;
        }

        .badge {
            font-size: 12px;
            padding: .45rem .65rem;
        }

        .btn-xs {
            padding: .25rem .5rem;
            font-size: .75rem;
        }

        .card-title {
            margin-bottom: .25rem;
        }

        .card-description {
            margin-bottom: 1rem;
        }

        /* ====== Search bonito (DataTables) ====== */
        .dt-topbar {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .dt-search-wrap {
            position: relative;
            flex: 1 1 320px;
            max-width: 520px;
        }

        .dt-search-wrap .dt-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            opacity: .55;
            pointer-events: none;
        }

        div.dataTables_filter {
            margin: 0 !important;
            width: 100%;
        }

        div.dataTables_filter label {
            margin: 0 !important;
            width: 100%;
            display: block;
            font-size: 0;
            /* esconde "Pesquisar:" */
        }

        div.dataTables_filter label input {
            font-size: 14px;
        }

        div.dataTables_filter input {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 38px !important;
            height: 42px;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, .12);
            background: #fff;
            transition: box-shadow .2s ease, border-color .2s ease;
        }

        div.dataTables_filter input:focus {
            border-color: rgba(0, 123, 255, .45);
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .15);
            outline: 0;
        }

        /* ===== Rodapé (info + paginação) alinhados ===== */
        .dt-bottombar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .dt-bottombar .dataTables_info,
        .dt-bottombar .dataTables_paginate {
            margin: 0 !important;
        }

        /* ===== Mobile: "Anterior" na esquerda e "Próxima" na direita ===== */
        @media (max-width: 576px) {
            .dt-bottombar {
                flex-wrap: nowrap;
                /* mantém lado a lado */
            }

            .dt-bottombar .dataTables_info {
                font-size: 12px;
                white-space: nowrap;
            }

            /* Paginação ocupa o espaço e joga prev/next para as pontas */
            .dt-bottombar .dataTables_paginate {
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            /* Esconde números + primeira/última no celular (fica só Anterior/Próxima) */
            .dt-bottombar .dataTables_paginate .paginate_button:not(.previous):not(.next) {
                display: none !important;
            }

            .dt-bottombar .dataTables_paginate .paginate_button {
                margin: 0 !important;
            }
        }


        /* ações não estourarem */
        .acoes-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        @media (max-width: 576px) {
            .dt-search-wrap {
                max-width: 100%;
            }

            div.dataTables_filter input {
                height: 44px;
            }
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
                            <a class="dropdown-item" href="../../../controle/auth/logout.php">
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
                                <li class="nav-item active"> <a class="nav-link" href="#" style="color:aliceblue !important;">Lista de Adicionados</a></li>
                                <li class="nav-item "> <a class="nav-link" href="./adicionarUser.php">Adicionar Usuários</a></li>

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
                            <h3 class="font-weight-bold">Usuários do sistema</h3>
                            <h6 class="font-weight-normal mb-0">Gerencie usuários (ativar/desativar/excluir).</h6>
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
                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <h4 class="card-title mb-0">Lista de Usuários</h4>
                                            <p class="card-description mb-0">Busca, ordenação e paginação automática.</p>
                                        </div>
                                        <a href="./adicionarUser.php" class="btn btn-primary btn-sm mt-2 mt-md-0">
                                            <i class="ti-plus"></i> Adicionar
                                        </a>
                                    </div>

                                    <div class="table-responsive pt-3">
                                        <table id="tabelaUsuarios" class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nome</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                    <th style="min-width: 210px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usuarios as $u): ?>
                                                    <?php
                                                    $id = (int)($u['id'] ?? 0);
                                                    $ativo = (int)($u['ativo'] ?? 0) === 1;
                                                    $badgeClass = $ativo ? 'badge-success' : 'badge-danger';
                                                    $badgeText  = $ativo ? 'Ativo' : 'Inativo';
                                                    ?>
                                                    <tr>
                                                        <td><?= $id ?></td>
                                                        <td><?= h($u['nome'] ?? '') ?></td>
                                                        <td><?= h($u['email'] ?? '') ?></td>
                                                        <td><label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label></td>
                                                        <td>
                                                            <div class="acoes-wrap">


                                                                <form method="post" class="m-0">
                                                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                                    <input type="hidden" name="acao" value="toggle">
                                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                                    <button type="submit" class="btn btn-outline-warning btn-xs"
                                                                        onclick="return confirm('Deseja <?= $ativo ? 'DESATIVAR' : 'ATIVAR' ?> este usuário?');">
                                                                        <i class="ti-power-off"></i> <?= $ativo ? 'Desativar' : 'Ativar' ?>
                                                                    </button>
                                                                </form>

                                                                <form method="post" class="m-0">
                                                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                                    <input type="hidden" name="acao" value="excluir">
                                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                                    <button type="submit" class="btn btn-outline-danger btn-xs"
                                                                        onclick="return confirm('Tem certeza que deseja EXCLUIR este usuário? Essa ação não pode ser desfeita.');">
                                                                        <i class="ti-trash"></i> Excluir
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>

                                        <?php if (empty($usuarios)): ?>
                                            <div class="text-muted mt-3">Nenhum usuário cadastrado ainda.</div>
                                        <?php endif; ?>
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

    <!-- DATATABLES -->
    <script src="../../../vendors/datatables.net/jquery.dataTables.js"></script>
    <script src="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>

    <!-- inject:js -->
    <script src="../../../js/off-canvas.js"></script>
    <script src="../../../js/hoverable-collapse.js"></script>
    <script src="../../../js/template.js"></script>
    <script src="../../../js/settings.js"></script>
    <script src="../../../js/todolist.js"></script>

    <script>
        $(function() {
            if (!$('#tabelaUsuarios').length) return;

            $('#tabelaUsuarios').DataTable({
                pageLength: 10,
                lengthChange: false, // remove "Mostrar X por página"
                dom: '<"dt-topbar"<"dt-search-wrap"f>>rt<"dt-bottombar"<"dt-info"i><"dt-pages"p>>',

                order: [
                    [0, 'desc']
                ],
                language: {
                    processing: "Processando...",
                    search: "Pesquisar:",
                    info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
                    infoEmpty: "Mostrando 0 até 0 de 0 registros",
                    infoFiltered: "(filtrado de _MAX_ registros)",
                    loadingRecords: "Carregando...",
                    zeroRecords: "Nenhum registro encontrado",
                    emptyTable: "Nenhum dado disponível na tabela",
                    paginate: {
                        first: "Primeira",
                        previous: "Anterior",
                        next: "Próxima",
                        last: "Última"
                    }
                },
                initComplete: function() {
                    const $filter = $('#tabelaUsuarios_filter');
                    const $input = $filter.find('input');

                    $input.attr('placeholder', 'Pesquisar usuários (nome, email...)');
                    $filter.addClass('w-100');

                    if (!$filter.find('.dt-search-icon').length) {
                        $filter.prepend('<i class="ti-search dt-search-icon"></i>');
                    }
                }
            });
        });
    </script>
</body>

</html>