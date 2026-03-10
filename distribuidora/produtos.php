<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/assets/conexao.php';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtMoney($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   SELECTS
========================= */
$categorias = $pdo->query("
    SELECT id, nome, status
    FROM categorias
    ORDER BY nome ASC
")->fetchAll();

$fornecedores = $pdo->query("
    SELECT id, nome, status
    FROM fornecedores
    ORDER BY nome ASC
")->fetchAll();

/* =========================
   PRODUTOS
========================= */
$produtos = $pdo->query("
    SELECT
        p.*,
        c.nome AS categoria_nome,
        f.nome AS fornecedor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    ORDER BY p.id DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Produtos</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .profile-box .dropdown-menu {
            width: max-content;
            min-width: 260px;
            max-width: calc(100vw - 24px);
        }

        .profile-box .dropdown-menu .author-info {
            width: max-content;
            max-width: 100%;
            display: flex !important;
            align-items: center;
            gap: 10px;
        }

        .profile-box .dropdown-menu .author-info .content {
            min-width: 0;
            max-width: 100%;
        }

        .profile-box .dropdown-menu .author-info .content a {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
        }

        .main-btn.btn-compact {
            height: 38px !important;
            padding: 8px 14px !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .main-btn.btn-compact i {
            font-size: 14px;
            vertical-align: -1px;
        }

        .icon-btn {
            height: 34px !important;
            width: 42px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .minw-120 {
            min-width: 120px;
        }

        .minw-140 {
            min-width: 140px;
        }

        .minw-160 {
            min-width: 160px;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbProdutos {
            width: 100%;
            min-width: 1180px;
        }

        #tbProdutos th,
        #tbProdutos td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            text-align: center !important;
        }

        #tbProdutos th.col-produto,
        #tbProdutos td.col-produto {
            text-align: left !important;
        }

        .badge-soft {
            padding: .35rem .6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
        }

        .badge-soft-success {
            background: rgba(34, 197, 94, .12);
            color: #16a34a;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, .12);
            color: #b45309;
        }

        .badge-soft-gray {
            background: rgba(148, 163, 184, .18);
            color: #475569;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
        }

        .pagination-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .page-btn {
            width: 42px;
            height: 42px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s ease;
        }

        .page-btn:hover:not(:disabled) {
            background: #eef2ff;
            color: #1e40af;
            border-color: #c7d2fe;
        }

        .page-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .page-info {
            font-weight: 700;
            color: #475569;
            min-width: 90px;
            text-align: center;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
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

        @media (max-width: 767.98px) {
            .pagination-wrap {
                justify-content: center;
                width: 100%;
            }

            #infoCount {
                text-align: center;
                width: 100%;
            }

            .toolbar-actions {
                justify-content: stretch;
            }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

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
                        <span class="icon"><i class="lni lni-dashboard"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon"><i class="lni lni-cart"></i></span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon"><i class="lni lni-layers"></i></span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon"><i class="lni lni-package"></i></span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav show">
                        <li><a href="produtos.php" class="active">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon"><i class="lni lni-users"></i></span>
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
                        <span class="icon"><i class="lni lni-clipboard"></i></span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon"><i class="lni lni-cog"></i></span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon"><i class="lni lni-whatsapp"></i></span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <main class="main-wrapper">
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
                            <div class="header-search d-none d-md-flex" style="display: none !important;">
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar produto..." id="qGlobal" />
                                    <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
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
            <div class="container-fluid">
                <div class="title-wrapper pt-30">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Produtos</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div id="flashBox" class="alert alert-<?= e((string)($flash['type'] ?? 'success')) ?> flash-auto-hide mt-2">
                        <?= e((string)($flash['msg'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <div class="card-style mb-30">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control" id="qProdutos" placeholder="Nome, código, categoria, fornecedor..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" id="fCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        <?= e((string)$c['nome']) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="fStatus">
                                <option value="">Todos</option>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                                <option value="BAIXO">Estoque Baixo</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="toolbar-actions">
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal"
                                    data-bs-target="#modalProduto" id="btnNovo" type="button">
                                    <i class="lni lni-plus me-1"></i> Novo
                                </button>

                                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                                    <i class="lni lni-download me-1"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-style mb-30">
                    <div class="table-responsive">
                        <table class="table text-nowrap" id="tbProdutos">
                            <thead>
                                <tr>
                                    <th class="minw-140">Código</th>
                                    <th class="col-produto">Produto</th>
                                    <th class="minw-140">Categoria</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-140">Preço</th>
                                    <th class="minw-120">Estoque</th>
                                    <th class="minw-120">Mínimo</th>
                                    <th class="minw-140">Status</th>
                                    <th class="minw-140">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <?php
                                    $id      = (int)($p['id'] ?? 0);
                                    $catId   = (int)($p['categoria_id'] ?? 0);
                                    $forId   = (int)($p['fornecedor_id'] ?? 0);
                                    $codigo  = (string)($p['codigo'] ?? '');
                                    $nome    = (string)($p['nome'] ?? '');
                                    $unidade = (string)($p['unidade'] ?? '');
                                    $preco   = (string)($p['preco'] ?? '0');
                                    $estoque = (int)($p['estoque'] ?? 0);
                                    $minimo  = (int)($p['minimo'] ?? 0);
                                    $obs     = (string)($p['obs'] ?? '');
                                    $status  = strtoupper((string)($p['status'] ?? 'ATIVO')) === 'INATIVO' ? 'INATIVO' : 'ATIVO';

                                    $catNome = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
                                    $forNome = trim((string)($p['fornecedor_nome'] ?? '')) ?: '—';

                                    $baixo = ($status !== 'INATIVO') && ($estoque < $minimo);

                                    if ($status === 'INATIVO') {
                                        $badge = '<span class="badge-soft badge-soft-gray">INATIVO</span>';
                                    } elseif ($baixo) {
                                        $badge = '<span class="badge-soft badge-soft-warning">BAIXO</span>';
                                    } else {
                                        $badge = '<span class="badge-soft badge-soft-success">ATIVO</span>';
                                    }
                                    ?>
                                    <tr
                                        data-id="<?= $id ?>"
                                        data-codigo="<?= e($codigo) ?>"
                                        data-nome="<?= e($nome) ?>"
                                        data-status="<?= e($status) ?>"
                                        data-categoria="<?= $catId ?>"
                                        data-cat-id="<?= $catId ?>"
                                        data-for-id="<?= $forId ?>"
                                        data-unidade="<?= e($unidade) ?>"
                                        data-preco="<?= e($preco) ?>"
                                        data-estoque="<?= $estoque ?>"
                                        data-minimo="<?= $minimo ?>"
                                        data-obs="<?= e($obs) ?>"
                                        data-baixo="<?= $baixo ? '1' : '0' ?>">
                                        <td><?= e($codigo) ?></td>
                                        <td class="col-produto">
                                            <div style="font-weight:800;color:#0f172a;line-height:1.1;"><?= e($nome) ?></div>
                                            <div class="muted">Fornecedor: <?= e($forNome) ?></div>
                                        </td>
                                        <td><?= e($catNome) ?></td>
                                        <td><?= e($unidade) ?></td>
                                        <td><?= e(fmtMoney($preco)) ?></td>
                                        <td><?= $estoque ?></td>
                                        <td><?= $minimo ?></td>
                                        <td><?= $badge ?></td>
                                        <td>
                                            <button class="main-btn light-btn btn-hover icon-btn btnEdit" type="button" title="Editar">
                                                <i class="lni lni-pencil"></i>
                                            </button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir">
                                                <i class="lni lni-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-3">
                        <p class="text-sm text-gray mb-0" id="infoCount"></p>

                        <div class="pagination-wrap">
                            <button type="button" class="page-btn" id="btnPrevPage" aria-label="Página anterior">
                                <i class="lni lni-chevron-left"></i>
                            </button>

                            <span class="page-info" id="pageInfo">Página 1/1</span>

                            <button type="button" class="page-btn" id="btnNextPage" aria-label="Próxima página">
                                <i class="lni lni-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6 order-last order-md-first">
                        <div class="copyright text-center text-md-start">
                            <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <form id="frmDelete" action="assets/dados/produtos/excluirProdutos.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="delId" value="">
    </form>

    <div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProdutoTitle">Novo Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formProduto" action="assets/dados/produtos/adicionarProdutos.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" id="pId" value="">

                        <div class="row g-3">
                            <div class="col-12">
                                <hr class="my-2">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="pCodigo" name="codigo" placeholder="Ex: P0005" required />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <input type="text" class="form-control" id="pNome" name="nome" placeholder="Nome do produto" required />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="pStatus" name="status" required>
                                    <option value="ATIVO">Ativo</option>
                                    <option value="INATIVO">Inativo</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" id="pCategoria" name="categoria_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($categorias as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>">
                                            <?= e((string)$c['nome']) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Fornecedor</label>
                                <select class="form-select" id="pFornecedor" name="fornecedor_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <option value="<?= (int)$f['id'] ?>">
                                            <?= e((string)$f['nome']) ?><?= (strtoupper((string)$f['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Unidade</label>
                                <select class="form-select" id="pUnidade" name="unidade" required>
                                    <option value="">Selecione…</option>
                                    <option value="Unidade">Unidade</option>
                                    <option value="Pacote">Pacote</option>
                                    <option value="Caixa">Caixa</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Litro">Litro</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Preço</label>
                                <input type="text" class="form-control" id="pPreco" name="preco" placeholder="0,00" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Estoque</label>
                                <input type="number" class="form-control" id="pEstoque" name="estoque" min="0" value="0" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Estoque mínimo</label>
                                <input type="number" class="form-control" id="pMinimo" name="minimo" min="0" value="0" required />
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Observação</label>
                                <input type="text" class="form-control" id="pObs" name="obs" placeholder="Opcional" />
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formProduto" class="main-btn primary-btn btn-hover btn-compact">
                        <i class="lni lni-save me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        (function() {
            const box = document.getElementById('flashBox');
            if (!box) return;

            setTimeout(() => {
                box.classList.add('hide');
                setTimeout(() => box.remove(), 400);
            }, 1500);
        })();

        const tb = document.getElementById('tbProdutos');
        const tbodyRows = Array.from(tb.querySelectorAll('tbody tr'));

        const qProdutos = document.getElementById('qProdutos');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fStatus = document.getElementById('fStatus');

        const infoCount = document.getElementById('infoCount');
        const pageInfo = document.getElementById('pageInfo');
        const btnPrevPage = document.getElementById('btnPrevPage');
        const btnNextPage = document.getElementById('btnNextPage');

        const PER_PAGE = 5;
        let currentPage = 1;

        function norm(v) {
            return String(v ?? '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        function syncSearch(source, target) {
            if (target.value !== source.value) {
                target.value = source.value;
            }
        }

        function getSearchText() {
            return norm(qProdutos.value);
        }

        function rowMatches(tr) {
            const q = getSearchText();
            const cat = String(fCategoria.value || '').trim();
            const st = String(fStatus.value || '').trim();

            const text = norm(tr.innerText);
            const rCat = String(tr.getAttribute('data-categoria') || '').trim();
            const rStatus = String(tr.getAttribute('data-status') || 'ATIVO').trim();
            const rBaixo = tr.getAttribute('data-baixo') === '1';

            if (q && !text.includes(q)) return false;
            if (cat && rCat !== cat) return false;

            if (st === 'ATIVO' && rStatus !== 'ATIVO') return false;
            if (st === 'INATIVO' && rStatus !== 'INATIVO') return false;
            if (st === 'BAIXO' && !rBaixo) return false;

            return true;
        }

        function getFilteredRows() {
            return tbodyRows.filter(rowMatches);
        }

        function renderTable(resetPage = false) {
            if (resetPage) currentPage = 1;

            const filtered = getFilteredRows();
            const totalItems = filtered.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / PER_PAGE));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            tbodyRows.forEach(tr => {
                tr.style.display = 'none';
            });

            const start = (currentPage - 1) * PER_PAGE;
            const end = start + PER_PAGE;
            const pageRows = filtered.slice(start, end);

            pageRows.forEach(tr => {
                tr.style.display = '';
            });

            if (totalItems > 0) {
                infoCount.textContent = `Mostrando ${pageRows.length} item(ns) nesta página de produtos. Total filtrado: ${totalItems}.`;
            } else {
                infoCount.textContent = 'Nenhum produto encontrado.';
            }

            pageInfo.textContent = `Página ${currentPage}/${totalPages}`;
            btnPrevPage.disabled = currentPage <= 1 || totalItems === 0;
            btnNextPage.disabled = currentPage >= totalPages || totalItems === 0;
        }

        qProdutos.addEventListener('input', function() {
            syncSearch(qProdutos, qGlobal);
            renderTable(true);
        });

        qGlobal.addEventListener('input', function() {
            syncSearch(qGlobal, qProdutos);
            renderTable(true);
        });

        fCategoria.addEventListener('change', function() {
            renderTable(true);
        });

        fStatus.addEventListener('change', function() {
            renderTable(true);
        });

        btnPrevPage.addEventListener('click', function() {
            currentPage--;
            renderTable(false);
        });

        btnNextPage.addEventListener('click', function() {
            currentPage++;
            renderTable(false);
        });

        renderTable(true);

        const modalEl = document.getElementById('modalProduto');
        const modal = new bootstrap.Modal(modalEl);
        const modalTitle = document.getElementById('modalProdutoTitle');

        const pId = document.getElementById('pId');
        const pCodigo = document.getElementById('pCodigo');
        const pNome = document.getElementById('pNome');
        const pStatus = document.getElementById('pStatus');
        const pCategoria = document.getElementById('pCategoria');
        const pFornecedor = document.getElementById('pFornecedor');
        const pUnidade = document.getElementById('pUnidade');
        const pPreco = document.getElementById('pPreco');
        const pEstoque = document.getElementById('pEstoque');
        const pMinimo = document.getElementById('pMinimo');
        const pObs = document.getElementById('pObs');

        function limparForm() {
            pId.value = '';
            pCodigo.value = '';
            pNome.value = '';
            pStatus.value = 'ATIVO';
            pCategoria.value = '';
            pFornecedor.value = '';
            pUnidade.value = '';
            pPreco.value = '';
            pEstoque.value = '0';
            pMinimo.value = '0';
            pObs.value = '';
        }

        document.getElementById('btnNovo').addEventListener('click', function() {
            modalTitle.textContent = 'Novo Produto';
            limparForm();
        });

        tb.addEventListener('click', function(e) {
            const btnEdit = e.target.closest('.btnEdit');
            const btnDel = e.target.closest('.btnDel');
            const tr = e.target.closest('tr');

            if (!tr) return;

            if (btnDel) {
                const id = tr.getAttribute('data-id') || '';
                const nome = tr.getAttribute('data-nome') || '';

                if (confirm(`Deseja remover o produto: "${nome}"?`)) {
                    document.getElementById('delId').value = id;
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            if (btnEdit) {
                modalTitle.textContent = 'Editar Produto';

                pId.value = tr.getAttribute('data-id') || '';
                pCodigo.value = tr.getAttribute('data-codigo') || '';
                pNome.value = tr.getAttribute('data-nome') || '';
                pStatus.value = tr.getAttribute('data-status') || 'ATIVO';
                pCategoria.value = tr.getAttribute('data-cat-id') || '';
                pFornecedor.value = tr.getAttribute('data-for-id') || '';
                pUnidade.value = tr.getAttribute('data-unidade') || '';
                pPreco.value = String(tr.getAttribute('data-preco') || '0').replace('.', ',');
                pEstoque.value = tr.getAttribute('data-estoque') || '0';
                pMinimo.value = tr.getAttribute('data-minimo') || '0';
                pObs.value = tr.getAttribute('data-obs') || '';

                modal.show();
            }
        });

        function exportExcel() {
            const filtered = getFilteredRows();

            if (!filtered.length) {
                alert('Não há produtos para exportar.');
                return;
            }

            const now = new Date();
            const dataHora = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');
            const dataArquivo = now.toISOString().slice(0, 19).replace(/[:T]/g, '-');

            const categoria = fCategoria.value ?
                fCategoria.options[fCategoria.selectedIndex].text :
                'Todas';

            const status = fStatus.value || 'Todos';

            const header = ['Código', 'Produto', 'Categoria', 'Unidade', 'Preço', 'Estoque', 'Mínimo', 'Status'];

            const body = filtered.map(tr => {
                return [
                    tr.children[0].innerText.trim(),
                    tr.getAttribute('data-nome') || '',
                    tr.children[2].innerText.trim(),
                    tr.children[3].innerText.trim(),
                    tr.children[4].innerText.trim(),
                    tr.children[5].innerText.trim(),
                    tr.children[6].innerText.trim(),
                    tr.children[7].innerText.trim()
                ];
            });

            const isCenterCol = (idx) => idx !== 1;

            let html = `
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
                        td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; text-align: center; }
                        th { background: #dbe5f1; font-weight: bold; }
                        .title { font-size: 16px; font-weight: bold; text-align: center; background: #ddebf7; }
                        .left { text-align: left; }
                        .center { text-align: center; }
                    </style>
                </head>
                <body>
                    <table>
            `;

            html += `<tr><td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - PRODUTOS</td></tr>`;
            html += `<tr><td colspan="8">Gerado em: ${dataHora}</td></tr>`;
            html += `<tr><td colspan="8">Categoria: ${categoria} | Status: ${status}</td></tr>`;
            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx) ? 'center' : 'left'}">${h}</th>`).join('')}</tr>`;

            body.forEach(row => {
                html += '<tr>';
                row.forEach((cell, idx) => {
                    const safe = String(cell)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;');

                    const cls = isCenterCol(idx) ? 'center' : 'left';
                    html += `<td class="${cls}">${safe}</td>`;
                });
                html += '</tr>';
            });

            html += `
                    </table>
                </body>
                </html>
            `;

            const blob = new Blob(["\ufeff" + html], {
                type: 'application/vnd.ms-excel;charset=utf-8;'
            });

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `produtos_${dataArquivo}.xls`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        document.getElementById('btnExcel').addEventListener('click', exportExcel);
    </script>
</body>

</html>