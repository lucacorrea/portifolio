<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/parametros/_helpers.php';

require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

require_db_or_die();
$pdo = db();

$csrf = csrf_token();
$flashOk  = flash_pop('flash_ok');
$flashErr = flash_pop('flash_err');

$rows = $pdo->query("SELECT * FROM parametros ORDER BY chave ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel da Distribuidora | Parâmetros</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" />
    <link rel="stylesheet" href="assets/css/main.css" />
    <style>
        .card-style {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .card-style .head {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-style .body {
            padding: 14px 16px;
        }

        .muted {
            font-size: 13px;
            color: #64748b;
        }

        .table-wrap {
            overflow: auto;
            border-radius: 14px;
        }

        #tbParams thead th {
            background: #f8fafc;
            padding: 12px;
            font-size: 13.5px;
        }

        #tbParams tbody td {
            padding: 14px 12px;
            font-size: 14.5px;
            vertical-align: middle;
        }

        .logout-btn {
            padding: 8px 14px !important;
            min-width: 88px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none !important;
        }

        .logout-btn i {
            font-size: 16px;
        }

        .header-right {
            height: 100%;
        }

        .brand-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            text-align: center;
        }

        .brand-name {
            display: block;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 600;
            color: #1e2a78;
            white-space: normal;
            word-break: break-word;
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.php" class="brand-vertical">
                <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php">
                        <span class="icon">
                            <i class="lni lni-dashboard"></i>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon">
                            <i class="lni lni-cart"></i>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-layers"></i>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-package"></i>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-users"></i>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.php">
                        <span class="icon">
                            <i class="lni lni-clipboard"></i>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-cog"></i>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav show">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php" class="active">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon">
                            <i class="lni lni-whatsapp"></i>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>
    <main class="main-wrapper">
        <!-- Header -->
        <header class="header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-5 col-md-5 col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-15">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex"></div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right d-flex justify-content-end align-items-center">
                            <a href="logout.php" class="main-btn primary-btn btn-hover logout-btn">
                                <i class="lni lni-exit me-1"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid p-4">
                <?php if ($flashOk): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?>
                <?php if ($flashErr): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?>
                <div class="card-style mb-3">
                    <div class="head">
                        <div>
                            <h5 class="mb-0">Parâmetros do Sistema</h5>
                            <div class="muted">Configurações globais</div>
                        </div><button id="btnNovo" class="main-btn primary-btn btn-hover"><i class="lni lni-plus"></i> Novo</button>
                    </div>
                    <div class="body">
                        <div class="table-wrap">
                            <table class="table" id="tbParams">
                                <thead>
                                    <tr>
                                        <th>Chave</th>
                                        <th>Valor</th>
                                        <th>Descrição</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><b><?= e($r['chave']) ?></b></td>
                                            <td><?= e($r['valor']) ?></td>
                                            <td class="muted"><?= e($r['descricao']) ?></td>
                                            <td class="text-end">
                                                <button class="main-btn primary-btn btn-sm btnEdit" data-id="<?= $r['id'] ?>" data-chave="<?= e($r['chave']) ?>" data-valor="<?= e($r['valor']) ?>" data-desc="<?= e($r['descricao']) ?>"><i class="lni lni-pencil"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <div class="modal fade" id="mdForm" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="assets/dados/parametros/salvarParametros.php">
                    <div class="modal-header">
                        <h5>Parâmetro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" id="fmId">
                        <div class="mb-3"><label class="form-label">Chave</label><input type="text" class="form-control" name="chave" id="fmChave" required></div>
                        <div class="mb-3"><label class="form-label">Valor</label><textarea class="form-control" name="valor" id="fmValor" rows="3"></textarea></div>
                        <div class="mb-3"><label class="form-label">Descrição</label><input type="text" class="form-control" name="descricao" id="fmDesc"></div>
                    </div>
                    <div class="modal-footer"><button class="main-btn primary-btn" type="submit">Salvar</button></div>
                </form>
            </div>
        </div>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('mdForm'));
        document.getElementById('btnNovo').onclick = () => {
            document.getElementById('fmId').value = '';
            document.getElementById('fmChave').value = '';
            document.getElementById('fmValor').value = '';
            document.getElementById('fmDesc').value = '';
            modal.show();
        };
        document.querySelectorAll('.btnEdit').forEach(b => {
            b.onclick = () => {
                document.getElementById('fmId').value = b.dataset.id;
                document.getElementById('fmChave').value = b.dataset.chave;
                document.getElementById('fmValor').value = b.dataset.valor;
                document.getElementById('fmDesc').value = b.dataset.desc;
                modal.show();
            };
        });
    </script>
</body>

</html>