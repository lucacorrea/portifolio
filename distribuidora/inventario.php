<?php

declare(strict_types=1);declare(strict_types=1);

require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/inventario/_helpers.php';

$csrf  = csrf_token();
$flash = flash_pop();
$pdo = db();

/**
 * Banco: images/arquivo.png
 * Exibir: ./assets/dados/produtos/images/arquivo.png
 */
function img_url_from_db(string $dbValue): string
{
    $v = trim($dbValue);
    if ($v === '') return '';

    // Se já vier absoluto/URL, mantém
    if (preg_match('~^(https?://|/|assets/)~i', $v)) return $v;

    $v = ltrim($v, '/');
    return 'assets/dados/produtos/' . $v;
}

function to_int($v): int
{
    return (int)($v ?? 0);
}

/** =========================
 *  PAGINAÇÃO
 *  ========================= */
$per = (int)($_GET['per'] ?? 25);
if ($per < 10) $per = 10;
if ($per > 100) $per = 100;

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$total = (int)$pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;

$offset = ($page - 1) * $per;

function url_with_page(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    $self = basename($_SERVER['PHP_SELF'] ?? 'inventario.php');
    return $self . '?' . http_build_query($q);
}

$prevUrl = url_with_page(max(1, $page - 1));
$nextUrl = url_with_page(min($pages, $page + 1));

/** =========================
 *  FILTROS (categorias)
 *  ========================= */
$categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

/**
 * Totais por produto:
 * - estoque: produtos.estoque
 * - vendas:  SUM(venda_itens.qtd)
 * - saidas:  SUM(saidas.qtd)
 * Soma = estoque + vendas + saidas
 */

// Produtos (para modal “Lançar”) - com totais (sem entradas)
$prodList = $pdo->query("
  SELECT 
    p.id, p.codigo, p.nome, p.unidade, p.estoque, p.categoria_id,
    c.nome AS categoria_nome,
    COALESCE(vi.vend,0) AS vendas,
    COALESCE(sa.said,0) AS saidas,
    (p.estoque + COALESCE(vi.vend,0) + COALESCE(sa.said,0)) AS soma
  FROM produtos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
  LEFT JOIN (SELECT produto_id, SUM(qtd) vend FROM venda_itens GROUP BY produto_id) vi ON vi.produto_id = p.id
  LEFT JOIN (SELECT produto_id, SUM(qtd) said FROM saidas GROUP BY produto_id) sa ON sa.produto_id = p.id
  ORDER BY p.nome ASC
  LIMIT 5000
")->fetchAll(PDO::FETCH_ASSOC);

// Itens do inventário (PAGINADO)
$st = $pdo->prepare("
  SELECT 
    p.id AS produto_id, p.codigo, p.nome, p.unidade, p.estoque, p.categoria_id,
    c.nome AS categoria_nome,
    p.imagem,
    i.contagem,

    COALESCE(vi.vend,0) AS vendas,
    COALESCE(sa.said,0) AS saidas,
    (p.estoque + COALESCE(vi.vend,0) + COALESCE(sa.said,0)) AS soma

  FROM produtos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
  LEFT JOIN inventario_itens i ON i.produto_id = p.id

  LEFT JOIN (SELECT produto_id, SUM(qtd) vend FROM venda_itens GROUP BY produto_id) vi ON vi.produto_id = p.id
  LEFT JOIN (SELECT produto_id, SUM(qtd) said FROM saidas GROUP BY produto_id) sa ON sa.produto_id = p.id

  ORDER BY p.nome ASC
  LIMIT :lim OFFSET :off
");
$st->bindValue(':lim', $per, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Inventário</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
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

        .minw-180 {
            min-width: 180px;
        }

        .minw-200 {
            min-width: 200px;
        }

        .minw-220 {
            min-width: 220px;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbInventario {
            width: 100%;
            min-width: 1560px;
        }

        #tbInventario th,
        #tbInventario td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }

        .badge-soft {
            padding: .35rem .6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
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

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
        }

        .count-input {
            height: 34px;
            padding: 6px 10px;
            font-size: 13px;
            width: 120px;
            text-align: center;
            display: inline-block;
        }

        .td-center {
            text-align: center;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        .pager-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 14px;
        }

        .pager-box .page-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
        }

        .btn-disabled {
            opacity: .45;
            pointer-events: none;
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
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php" class="active">Inventário</a></li>
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
                        <div class="col-md-7">
                            <div class="title">
                                <h2>Inventário</h2>
                                <p class="text-sm text-gray mb-0">
                                    Situação calculada por: <b>Soma = Estoque + Vendas + Saídas</b> e comparado com a <b>Contagem</b>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="card-style mb-30">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control" id="qInv" placeholder="Código, produto, categoria..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" id="fCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['nome']) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Situação</label>
                            <select class="form-select" id="fSituacao">
                                <option value="">Todas</option>
                                <option value="OK">OK</option>
                                <option value="DIVERGENTE">DIVERGENTE</option>
                                <option value="NAO_CONFERIDO">NÃO CONFERIDO</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal" data-bs-target="#modalLancamento" id="btnNovo" type="button">
                                    <i class="lni lni-plus me-1"></i> Lançar
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                                    <i class="lni lni-download me-1"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="card-style mb-30">
                    <div class="table-responsive">
                        <table class="table text-nowrap" id="tbInventario">
                            <thead>
                                <tr>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-220">Produto</th>
                                    <th class="minw-160">Categoria</th>
                                    <th class="minw-140">Unidade</th>

                                    <th class="minw-120 td-center">Estoque</th>
                                    <th class="minw-120 td-center">Vendas</th>
                                    <th class="minw-120 td-center">Saídas</th>
                                    <th class="minw-120 td-center">Soma</th>

                                    <th class="minw-160 td-center">Contagem</th>
                                    <th class="minw-140 td-center">Diferença</th>
                                    <th class="minw-160 td-center">Situação</th>
                                    <th class="minw-140 text-end">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $produtoId = (int)$r['produto_id'];
                                    $catId = (int)($r['categoria_id'] ?? 0);
                                    $catNome = trim((string)($r['categoria_nome'] ?? '')) ?: '—';
                                    $unidade = trim((string)($r['unidade'] ?? '')) ?: '—';

                                    $estoque = to_int($r['estoque'] ?? 0);
                                    $vendas  = to_int($r['vendas'] ?? 0);
                                    $saidas  = to_int($r['saidas'] ?? 0);
                                    $soma    = to_int($r['soma'] ?? 0);

                                    $contagem = $r['contagem'];
                                    $hasCount = ($contagem !== null && $contagem !== '');

                                    if (!$hasCount) {
                                        $sit = 'NAO_CONFERIDO';
                                        $diffTxt = '—';
                                        $badgeCls = 'badge-soft badge-soft-gray st';
                                        $badgeTxt = 'NÃO CONFERIDO';
                                        $countVal = '';
                                    } else {
                                        $countVal = (string)(int)$contagem;
                                        $diff = ((int)$contagem) - $soma;
                                        $diffTxt = (string)$diff;

                                        if ($diff === 0) {
                                            $sit = 'OK';
                                            $badgeCls = 'badge-soft badge-soft-success st';
                                            $badgeTxt = 'OK';
                                        } else {
                                            $sit = 'DIVERGENTE';
                                            $badgeCls = 'badge-soft badge-soft-warning st';
                                            $badgeTxt = 'DIVERGENTE';
                                        }
                                    }
                                    ?>
                                    <tr data-produto-id="<?= $produtoId ?>" data-categoria="<?= $catId ?>" data-situacao="<?= e($sit) ?>">
                                        <td class="cod"><?= e((string)$r['codigo']) ?></td>
                                        <td class="prod"><?= e((string)$r['nome']) ?></td>
                                        <td class="cat"><?= e($catNome) ?></td>
                                        <td class="und"><?= e($unidade) ?></td>

                                        <td class="td-center est"><?= e((string)$estoque) ?></td>
                                        <td class="td-center vend"><?= e((string)$vendas) ?></td>
                                        <td class="td-center sai"><?= e((string)$saidas) ?></td>
                                        <td class="td-center soma"><?= e((string)$soma) ?></td>

                                        <td class="td-center">
                                            <input type="number" class="form-control count-input count" min="0" value="<?= e($countVal) ?>" placeholder="—" />
                                        </td>

                                        <td class="td-center diff"><?= e($diffTxt) ?></td>
                                        <td class="td-center"><span class="<?= e($badgeCls) ?>"><?= e($badgeTxt) ?></span></td>

                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnSave" type="button" title="Salvar"><i class="lni lni-save"></i></button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir"><i class="lni lni-trash-can"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-sm text-gray mt-2 mb-0" id="infoCount"></p>

                    <!-- Paginação -->
                    <div class="pager-box">
                        <a class="main-btn light-btn btn-hover btn-compact icon-btn <?= $page <= 1 ? 'btn-disabled' : '' ?>" href="<?= e($prevUrl) ?>" title="Anterior">
                            <i class="lni lni-chevron-left"></i>
                        </a>
                        <span class="page-text">Página <?= (int)$page ?>/<?= (int)$pages ?></span>
                        <a class="main-btn light-btn btn-hover btn-compact icon-btn <?= $page >= $pages ? 'btn-disabled' : '' ?>" href="<?= e($nextUrl) ?>" title="Próxima">
                            <i class="lni lni-chevron-right"></i>
                        </a>
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

    <!-- SAVE FORM -->
    <form id="frmSave" action="assets/dados/inventario/salvarInventario.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="produto_id" id="svProdutoId" value="">
        <input type="hidden" name="contagem" id="svContagem" value="">
    </form>

    <!-- DELETE FORM -->
    <form id="frmDelete" action="assets/dados/inventario/excluirInventario.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="produto_id" id="dlProdutoId" value="">
    </form>

    <!-- Modal Lançamento -->
    <div class="modal fade" id="modalLancamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lançar Contagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formLanc" action="assets/dados/inventario/salvarInventario.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Produto *</label>
                                <select class="form-select" id="mProdutoId" name="produto_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($prodList as $p): ?>
                                        <option
                                            value="<?= (int)$p['id'] ?>"
                                            data-codigo="<?= e((string)$p['codigo']) ?>"
                                            data-nome="<?= e((string)$p['nome']) ?>"
                                            data-categoria="<?= e((string)($p['categoria_nome'] ?? '—')) ?>"
                                            data-unidade="<?= e((string)($p['unidade'] ?? '—')) ?>"
                                            data-estoque="<?= e((string)($p['estoque'] ?? 0)) ?>"
                                            data-vendas="<?= e((string)($p['vendas'] ?? 0)) ?>"
                                            data-saidas="<?= e((string)($p['saidas'] ?? 0)) ?>"
                                            data-soma="<?= e((string)($p['soma'] ?? 0)) ?>">
                                            <?= e((string)$p['nome']) ?> (<?= e((string)$p['codigo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="mCodigo" value="—" readonly />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <input type="text" class="form-control" id="mNome" value="—" readonly />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Unidade</label>
                                <input type="text" class="form-control" id="mUnidade" value="—" readonly />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <input type="text" class="form-control" id="mCategoria" value="—" readonly />
                            </div>

                            <div class="col-md-8">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" id="mEstoque" value="0" readonly />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Vendas</label>
                                        <input type="number" class="form-control" id="mVendas" value="0" readonly />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Saídas</label>
                                        <input type="number" class="form-control" id="mSaidas" value="0" readonly />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Soma</label>
                                        <input type="number" class="form-control" id="mSoma" value="0" readonly />
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Contagem *</label>
                                <input type="number" class="form-control" id="mContagem" name="contagem" min="0" value="" placeholder="Informe a contagem" required />
                            </div>
                        </div>
                    </form>

                    <p class="text-sm text-gray mt-3 mb-0">
                        Situação: <b>OK</b> se <b>Contagem == Soma</b>, senão <b>Divergente</b>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formLanc" class="main-btn primary-btn btn-hover btn-compact">
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

        const DEFAULT_IMG = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96">
  <rect width="100%" height="100%" fill="#f1f5f9"/>
  <path d="M18 68l18-18 12 12 10-10 20 20" fill="none" stroke="#94a3b8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="34" cy="34" r="7" fill="#94a3b8"/>
  <text x="50%" y="86%" text-anchor="middle" font-family="Arial" font-size="10" fill="#64748b">Sem imagem</text>
</svg>
`);

        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = img.getAttribute('src') || '';
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => img.src = DEFAULT_IMG, {
                once: true
            });
        });

        const tb = document.getElementById('tbInventario');
        const qInv = document.getElementById('qInv');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fSituacao = document.getElementById('fSituacao');
        const infoCount = document.getElementById('infoCount');

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function calcularLinha(tr) {
            const soma = Number(tr.querySelector('.soma')?.innerText || 0);
            const inp = tr.querySelector('input.count');
            const diffEl = tr.querySelector('.diff');
            const stEl = tr.querySelector('.st');

            const hasValue = inp && inp.value !== '';
            if (!hasValue) {
                tr.setAttribute('data-situacao', 'NAO_CONFERIDO');
                diffEl.innerText = '—';
                stEl.className = 'badge-soft badge-soft-gray st';
                stEl.innerText = 'NÃO CONFERIDO';
                return;
            }

            const count = Number(inp.value);
            if (Number.isNaN(count)) return;

            const diff = count - soma;
            diffEl.innerText = String(diff);

            if (diff === 0) {
                tr.setAttribute('data-situacao', 'OK');
                stEl.className = 'badge-soft badge-soft-success st';
                stEl.innerText = 'OK';
            } else {
                tr.setAttribute('data-situacao', 'DIVERGENTE');
                stEl.className = 'badge-soft badge-soft-warning st';
                stEl.innerText = 'DIVERGENTE';
            }
        }

        function aplicarFiltros() {
            const q = norm(qInv.value || qGlobal.value);
            const cat = String(fCategoria.value || '').trim();
            const sit = String(fSituacao.value || '').trim();

            const rows = Array.from(tb.querySelectorAll('tbody tr'));
            let shown = 0;

            rows.forEach(tr => {
                const text = norm(tr.innerText);
                const rCat = String(tr.getAttribute('data-categoria') || '').trim();
                const rSit = String(tr.getAttribute('data-situacao') || '').trim();

                let ok = true;
                if (q && !text.includes(q)) ok = false;
                if (cat && rCat !== cat) ok = false;
                if (sit && rSit !== sit) ok = false;

                tr.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });

            infoCount.textContent = `Mostrando ${shown} item(ns) nesta página do inventário.`;
        }

        qInv.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', aplicarFiltros);
        fCategoria.addEventListener('change', aplicarFiltros);
        fSituacao.addEventListener('change', aplicarFiltros);

        tb.addEventListener('input', (e) => {
            const inp = e.target.closest('input.count');
            if (!inp) return;
            const tr = inp.closest('tr');
            if (!tr) return;
            calcularLinha(tr);
            aplicarFiltros();
        });

        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const nome = tr.querySelector('.prod')?.innerText.trim() || '';
                if (confirm(`Remover do inventário: "${nome}"?`)) {
                    document.getElementById('dlProdutoId').value = tr.getAttribute('data-produto-id') || '';
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnSave = e.target.closest('.btnSave');
            if (btnSave) {
                calcularLinha(tr);
                const pid = tr.getAttribute('data-produto-id') || '';
                const count = tr.querySelector('input.count')?.value ?? '';
                document.getElementById('svProdutoId').value = pid;
                document.getElementById('svContagem').value = String(count);
                document.getElementById('frmSave').submit();
                return;
            }
        });

        // modal preencher infos
        const mProdutoId = document.getElementById('mProdutoId');
        const mCodigo = document.getElementById('mCodigo');
        const mNome = document.getElementById('mNome');
        const mCategoria = document.getElementById('mCategoria');
        const mUnidade = document.getElementById('mUnidade');

        const mEstoque = document.getElementById('mEstoque');
        const mVendas = document.getElementById('mVendas');
        const mSaidas = document.getElementById('mSaidas');
        const mSoma = document.getElementById('mSoma');

        const mContagem = document.getElementById('mContagem');

        mProdutoId.addEventListener('change', () => {
            const opt = mProdutoId.options[mProdutoId.selectedIndex];
            if (!opt || !opt.value) {
                mCodigo.value = '—';
                mNome.value = '—';
                mCategoria.value = '—';
                mUnidade.value = '—';
                mEstoque.value = 0;
                mVendas.value = 0;
                mSaidas.value = 0;
                mSoma.value = 0;
                return;
            }

            mCodigo.value = opt.getAttribute('data-codigo') || '—';
            mNome.value = opt.getAttribute('data-nome') || '—';
            mCategoria.value = opt.getAttribute('data-categoria') || '—';
            mUnidade.value = opt.getAttribute('data-unidade') || '—';

            mEstoque.value = Number(opt.getAttribute('data-estoque') || 0);
            mVendas.value = Number(opt.getAttribute('data-vendas') || 0);
            mSaidas.value = Number(opt.getAttribute('data-saidas') || 0);
            mSoma.value = Number(opt.getAttribute('data-soma') || 0);

            mContagem.value = '';
            mContagem.focus();
        });

        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            if (!rows.length) {
                alert('Não há itens para exportar.');
                return;
            }

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');
            const fileDt = now.toISOString().slice(0, 19).replace(/[:T]/g, '-');

            const categoria = fCategoria.value ?
                fCategoria.options[fCategoria.selectedIndex].text :
                'Todas';

            const situacao = fSituacao.value || 'Todas';
            const busca = (qInv.value || qGlobal.value || '').trim() || '—';

            const header = [
                'Código',
                'Produto',
                'Categoria',
                'Unidade',
                'Estoque',
                'Vendas',
                'Saídas',
                'Soma',
                'Contagem',
                'Diferença',
                'Situação'
            ];

            const body = rows.map(tr => {
                const countInput = tr.querySelector('input.count');
                const situacaoText = tr.querySelector('.st')?.innerText.trim() || '';

                return [
                    tr.querySelector('.cod')?.innerText.trim() || '',
                    tr.querySelector('.prod')?.innerText.trim() || '',
                    tr.querySelector('.cat')?.innerText.trim() || '',
                    tr.querySelector('.und')?.innerText.trim() || '',
                    tr.querySelector('.est')?.innerText.trim() || '',
                    tr.querySelector('.vend')?.innerText.trim() || '',
                    tr.querySelector('.sai')?.innerText.trim() || '',
                    tr.querySelector('.soma')?.innerText.trim() || '',
                    countInput ? String(countInput.value || '—') : '—',
                    tr.querySelector('.diff')?.innerText.trim() || '',
                    situacaoText
                ];
            });

            const isCenterCol = (idx) => idx !== 1;

            let html = `
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
                        td, th { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; text-align: center; }
                        th { background: #dbe5f1; font-weight: bold; }
                        .title { font-size: 16px; font-weight: bold; text-align: center; background: #ddebf7; }
                        .left { text-align: left; }
                        .center { text-align: center; }
                    </style>
                </head>
                <body>
                    <table>
            `;

            html += `<tr><td class="title" colspan="11">PAINEL DA DISTRIBUIDORA - INVENTÁRIO</td></tr>`;
            html += `<tr><td colspan="11">Gerado em: ${dt}</td></tr>`;
            html += `<tr><td colspan="11">Categoria: ${categoria} | Situação: ${situacao} | Busca: ${busca}</td></tr>`;
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
            a.download = `inventario_${fileDt}.xls`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        // init
        Array.from(tb.querySelectorAll('tbody tr')).forEach(calcularLinha);
        aplicarFiltros();
    </script>
</body>

</html>