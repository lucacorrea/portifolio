<?php

declare(strict_types=1);
session_start();

/* ======================
   SEGURANÇA
====================== */
if (empty($_SESSION['usuario_logado'])) {
    header('Location: ../../../index.php');
    exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
    header('Location: ../../operador/index.php');
    exit;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function to_int($v, int $default = 0): int
{
    if ($v === null) return $default;
    if (is_int($v)) return $v;
    if (is_numeric($v)) return (int)$v;
    return $default;
}

function brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/* ======================
   FLASH
====================== */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* ======================
   CONEXÃO
====================== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ======================
   HELPERS (SCHEMA)
====================== */
function hasTable(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = :t
  ");
    $st->execute([':t' => $table]);
    return (int)$st->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
  ");
    $st->execute([':t' => $table, ':c' => $column]);
    return (int)$st->fetchColumn() > 0;
}

/* ======================
   FEIRA FIXA
====================== */
$feiraId = 1;

/* ======================
   TABELAS OBRIGATÓRIAS
====================== */
if (
    !hasTable($pdo, 'vendas') ||
    !hasTable($pdo, 'venda_itens') ||
    !hasTable($pdo, 'produtos') ||
    !hasTable($pdo, 'produtores')
) {
    $err = 'Tabelas obrigatórias não encontradas (vendas, venda_itens, produtos, produtores).';
}

/* ======================
   CAMPOS (COMPAT)
====================== */
$colDataVenda = hasColumn($pdo, 'vendas', 'data_venda');          // opcional
$colDataHora  = hasColumn($pdo, 'vendas', 'data_hora');           // no seu schema: SIM
$colFormaPgto = hasColumn($pdo, 'vendas', 'forma_pagamento');     // no seu schema: SIM
$colStatus    = hasColumn($pdo, 'vendas', 'status');              // no seu schema: SIM

// campo de data base (datetime ou date)
if ($colDataHora) {
    $dateField = "v.data_hora";
} elseif ($colDataVenda) {
    $dateField = "v.data_venda";
} else {
    $dateField = "v.criado_em";
}
$dateExprDate = "DATE($dateField)";

/* ======================
   FILTRO: MÊS OU DIA
====================== */
$tipoFiltro = ($_GET['tipo'] ?? 'mes') === 'dia' ? 'dia' : 'mes';
$dataRaw = (string)($_GET['data'] ?? '');

$today = date('Y-m-d');

if ($tipoFiltro === 'dia') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) $dataRaw = $today;

    $periodStart = $dataRaw;
    $periodEnd   = $dataRaw;
    $labelPeriodo = date('d/m/Y', strtotime($dataRaw));
} else {
    // aceita YYYY-MM ou YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}$/', $dataRaw)) {
        $mesSel = $dataRaw;
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) {
        $mesSel = substr($dataRaw, 0, 7);
    } else {
        $mesSel = date('Y-m');
    }

    $periodStart = $mesSel . '-01';
    $periodEnd   = date('Y-m-t', strtotime($periodStart));
    $labelPeriodo = date('m/Y', strtotime($periodStart));
}

/* ======================
   PRODUTORES (SELECT)
====================== */
$produtoresList = [];
$produtorId = to_int($_GET['produtor_id'] ?? 0, 0);
$produtorInfo = null;

try {
    if (!$err) {
        $st = $pdo->prepare("
      SELECT p.id, p.nome
      FROM produtores p
      WHERE p.feira_id = :f
        AND p.ativo = 1
      ORDER BY p.nome ASC
    ");
        $st->execute([':f' => $feiraId]);
        $produtoresList = $st->fetchAll(PDO::FETCH_ASSOC);

        if (!$produtorId && !empty($produtoresList)) {
            $produtorId = (int)$produtoresList[0]['id'];
        }

        if ($produtorId) {
            // comunidades é opcional no relatório (mas existe no seu schema). Se não existir, só pega dados do produtor.
            $hasComunidades = hasTable($pdo, 'comunidades') && hasColumn($pdo, 'produtores', 'comunidade_id');

            if ($hasComunidades) {
                $st = $pdo->prepare("
          SELECT
            p.*,
            c.nome AS comunidade_nome
          FROM produtores p
          LEFT JOIN comunidades c ON c.id = p.comunidade_id
          WHERE p.id = :id AND p.feira_id = :f
          LIMIT 1
        ");
            } else {
                $st = $pdo->prepare("
          SELECT p.*
          FROM produtores p
          WHERE p.id = :id AND p.feira_id = :f
          LIMIT 1
        ");
            }

            $st->execute([':id' => $produtorId, ':f' => $feiraId]);
            $produtorInfo = $st->fetch(PDO::FETCH_ASSOC);

            if (!$produtorInfo) {
                $err = 'Produtor não encontrado na feira selecionada.';
            }
        } else {
            $err = 'Nenhum produtor cadastrado/ativo para selecionar.';
        }
    }
} catch (Throwable $e) {
    $err = 'Erro ao carregar produtores: ' . $e->getMessage();
}

/* ======================
   PAGINAÇÃO
====================== */
$PER_PAGE = 10;

$pageProdutos = max(1, to_int($_GET['page_produtos'] ?? 1, 1));
$pageDias     = max(1, to_int($_GET['page_dias'] ?? 1, 1));
$pageVendas   = max(1, to_int($_GET['page_vendas'] ?? 1, 1));

$offsetProdutos = ($pageProdutos - 1) * $PER_PAGE;
$offsetDias     = ($pageDias - 1) * $PER_PAGE;
$offsetVendas   = ($pageVendas - 1) * $PER_PAGE;

/* ======================
   URL HELPER (paginação/links)
====================== */
function url_with(array $overrides = []): string
{
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    $base = strtok($_SERVER['REQUEST_URI'], '?') ?: '';
    return $base . (empty($q) ? '' : '?' . http_build_query($q));
}

/* ======================
   DADOS DO RELATÓRIO (INDIVIDUAL)
====================== */
$resumo = [
    'vendas_qtd' => 0,
    'total' => 0.0,
    'itens_qtd' => 0.0,
    'produtos_distintos' => 0,
    'ticket' => 0.0,
];

$porPagamento = [];
$porProduto   = [];
$porDia       = [];
$vendasRows   = [];

$totalPagesProdutos = 1;
$totalPagesDias     = 1;
$totalPagesVendas   = 1;

$vendaId = to_int($_GET['venda_id'] ?? 0, 0);
$vendaDetalhe = null;
$vendaItens   = [];

try {
    if (!$err && $produtorId) {

        $paramsBase = [
            ':f' => $feiraId,
            ':p' => $produtorId,
            ':ini' => $periodStart,
        ];

        if ($tipoFiltro === 'dia') {
            $whereData = "($dateExprDate = :ini)";
        } else {
            $whereData = "($dateExprDate BETWEEN :ini AND :fim)";
            $paramsBase[':fim'] = $periodEnd;
        }

        // IMPORTANTE:
        // Como uma venda pode (teoricamente) ter itens de vários produtores,
        // o "total do produtor" é calculado por SUM(vi.subtotal) filtrando por produtor.
        $whereJoinProdutor = "
      v.feira_id = :f
      AND vi.feira_id = :f
      AND pr.feira_id = :f
      AND p.feira_id  = :f
      AND p.id = :p
      AND $whereData
    ";

        /* ======================
       RESUMO (KPIs)
    ====================== */
        $st = $pdo->prepare("
      SELECT
        COUNT(DISTINCT v.id) AS vendas_qtd,
        COALESCE(SUM(vi.subtotal),0) AS total,
        COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
        COUNT(DISTINCT pr.id) AS produtos_distintos
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE $whereJoinProdutor
    ");
        $st->execute($paramsBase);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $resumo['vendas_qtd'] = (int)($r['vendas_qtd'] ?? 0);
        $resumo['total'] = (float)($r['total'] ?? 0);
        $resumo['itens_qtd'] = (float)($r['itens_qtd'] ?? 0);
        $resumo['produtos_distintos'] = (int)($r['produtos_distintos'] ?? 0);
        $resumo['ticket'] = $resumo['vendas_qtd'] > 0 ? ($resumo['total'] / $resumo['vendas_qtd']) : 0;

        /* ======================
       POR PAGAMENTO (do produtor)
    ====================== */
        if ($colFormaPgto) {
            $st = $pdo->prepare("
        SELECT
          UPPER(COALESCE(v.forma_pagamento,'N/I')) AS pagamento,
          COUNT(DISTINCT v.id) AS vendas_qtd,
          COALESCE(SUM(vi.subtotal),0) AS total
        FROM vendas v
        JOIN venda_itens vi ON vi.venda_id = v.id
        JOIN produtos pr ON pr.id = vi.produto_id
        JOIN produtores p ON p.id = pr.produtor_id
        WHERE $whereJoinProdutor
        GROUP BY pagamento
        ORDER BY total DESC
      ");
            $st->execute($paramsBase);
            $porPagamento = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ======================
       PRODUTOS VENDIDOS (PAGINADO)
    ====================== */
        // total grupos
        $st = $pdo->prepare("
      SELECT COUNT(DISTINCT pr.id) AS total_grupos
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE $whereJoinProdutor
    ");
        $st->execute($paramsBase);
        $totalProdutos = (int)$st->fetchColumn();
        $totalPagesProdutos = max(1, (int)ceil($totalProdutos / $PER_PAGE));

        $hasUnidades = hasTable($pdo, 'unidades') && hasColumn($pdo, 'produtos', 'unidade_id');
        $hasCategorias = hasTable($pdo, 'categorias') && hasColumn($pdo, 'produtos', 'categoria_id');

        $selectUn = $hasUnidades ? "u.sigla AS unidade_sigla, u.nome AS unidade_nome" : "NULL AS unidade_sigla, NULL AS unidade_nome";
        $joinUn   = $hasUnidades ? "LEFT JOIN unidades u ON u.id = pr.unidade_id" : "";
        $selectCat = $hasCategorias ? "c.nome AS categoria_nome" : "NULL AS categoria_nome";
        $joinCat   = $hasCategorias ? "LEFT JOIN categorias c ON c.id = pr.categoria_id" : "";

        $st = $pdo->prepare("
      SELECT
        pr.id,
        pr.nome,
        $selectCat,
        $selectUn,
        COALESCE(SUM(vi.quantidade),0) AS quantidade,
        COALESCE(SUM(vi.subtotal),0) AS total
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      $joinCat
      $joinUn
      WHERE $whereJoinProdutor
      GROUP BY pr.id
      ORDER BY total DESC
      LIMIT $PER_PAGE OFFSET $offsetProdutos
    ");
        $st->execute($paramsBase);
        $porProduto = $st->fetchAll(PDO::FETCH_ASSOC);

        /* ======================
       POR DIA (PAGINADO) - só faz sentido no filtro mensal
    ====================== */
        if ($tipoFiltro === 'mes') {
            $st = $pdo->prepare("
        SELECT COUNT(DISTINCT $dateExprDate) AS total_dias
        FROM vendas v
        JOIN venda_itens vi ON vi.venda_id = v.id
        JOIN produtos pr ON pr.id = vi.produto_id
        JOIN produtores p ON p.id = pr.produtor_id
        WHERE $whereJoinProdutor
      ");
            $st->execute($paramsBase);
            $totalDias = (int)$st->fetchColumn();
            $totalPagesDias = max(1, (int)ceil($totalDias / $PER_PAGE));

            $st = $pdo->prepare("
        SELECT
          $dateExprDate AS dia,
          COUNT(DISTINCT v.id) AS vendas_qtd,
          COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
          COALESCE(SUM(vi.subtotal),0) AS total
        FROM vendas v
        JOIN venda_itens vi ON vi.venda_id = v.id
        JOIN produtos pr ON pr.id = vi.produto_id
        JOIN produtores p ON p.id = pr.produtor_id
        WHERE $whereJoinProdutor
        GROUP BY dia
        ORDER BY dia DESC
        LIMIT $PER_PAGE OFFSET $offsetDias
      ");
            $st->execute($paramsBase);
            $porDia = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ======================
       VENDAS DO PRODUTOR (PAGINADO)
    ====================== */
        $st = $pdo->prepare("
      SELECT COUNT(DISTINCT v.id) AS total_vendas
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE $whereJoinProdutor
    ");
        $st->execute($paramsBase);
        $totalVendas = (int)$st->fetchColumn();
        $totalPagesVendas = max(1, (int)ceil($totalVendas / $PER_PAGE));

        $selectPagamento = $colFormaPgto ? "v.forma_pagamento" : "NULL AS forma_pagamento";
        $selectStatus    = $colStatus    ? "v.status" : "'N/I' AS status";

        $st = $pdo->prepare("
      SELECT
        v.id,
        $dateField AS data_hora_ref,
        $selectPagamento,
        $selectStatus,
        COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
        COALESCE(SUM(vi.subtotal),0) AS total_produtor
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE $whereJoinProdutor
      GROUP BY v.id
      ORDER BY v.id DESC
      LIMIT $PER_PAGE OFFSET $offsetVendas
    ");
        $st->execute($paramsBase);
        $vendasRows = $st->fetchAll(PDO::FETCH_ASSOC);

        /* ======================
       DETALHE DE UMA VENDA (opcional)
    ====================== */
        if ($vendaId > 0) {
            // garante que a venda pertence ao período e tem itens desse produtor
            $st = $pdo->prepare("
        SELECT
          v.id,
          $dateField AS data_hora_ref,
          " . ($colFormaPgto ? "v.forma_pagamento" : "NULL") . " AS forma_pagamento,
          " . ($colStatus ? "v.status" : "'N/I'") . " AS status,
          COALESCE(SUM(vi.subtotal),0) AS total_produtor
        FROM vendas v
        JOIN venda_itens vi ON vi.venda_id = v.id
        JOIN produtos pr ON pr.id = vi.produto_id
        JOIN produtores p ON p.id = pr.produtor_id
        WHERE v.id = :vid
          AND $whereJoinProdutor
        GROUP BY v.id
        LIMIT 1
      ");
            $paramsDet = $paramsBase;
            $paramsDet[':vid'] = $vendaId;
            $st->execute($paramsDet);
            $vendaDetalhe = $st->fetch(PDO::FETCH_ASSOC);

            if ($vendaDetalhe) {
                $st = $pdo->prepare("
          SELECT
            vi.id,
            COALESCE(pr.nome, vi.descricao_livre) AS item_nome,
            vi.quantidade,
            vi.valor_unitario,
            vi.subtotal,
            vi.observacao
          FROM venda_itens vi
          LEFT JOIN produtos pr ON pr.id = vi.produto_id
          LEFT JOIN produtores p ON p.id = pr.produtor_id
          WHERE vi.venda_id = :vid
            AND vi.feira_id = :f
            AND p.id = :p
          ORDER BY vi.id ASC
        ");
                $st->execute([':vid' => $vendaId, ':f' => $feiraId, ':p' => $produtorId]);
                $vendaItens = $st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // se a venda não pertence ao produtor/período, limpa o venda_id da view
                $vendaId = 0;
            }
        }
    }
} catch (Throwable $e) {
    $err = 'Erro ao carregar relatório: ' . $e->getMessage();
}

/* ======================
   FINAL
====================== */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

$nomeProdutor = $produtorInfo['nome'] ?? '—';
$contatoProdutor = $produtorInfo['contato'] ?? '';
$docProdutor = $produtorInfo['documento'] ?? '';
$comunidadeProdutor = $produtorInfo['comunidade_nome'] ?? ($produtorInfo['comunidade_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SIGRelatórios — Relatório Individual do Produtor</title>

    <link rel="stylesheet" href="../../../vendors/feather/feather.css">
    <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
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

        .btn {
            height: 42px;
        }

        .mini-kpi {
            font-size: 12px;
            color: #6c757d;
        }

        .kpi-card {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 14px;
            padding: 14px;
            background: #fff;
            height: 100%;
        }

        .kpi-label {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }

        .kpi-value {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
        }

        .kpi-sub {
            font-size: 12px;
            color: #6c757d;
            margin: 6px 0 0;
        }

        .table td,
        .table th {
            vertical-align: middle !important;
        }

        .badge-soft {
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.07);
            font-weight: 600;
        }

        /* Flash top-right */
        .sig-flash-wrap {
            position: fixed;
            top: 78px;
            right: 18px;
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

        .btn-group-toggle .btn {
            border-radius: 8px;
            margin-right: 8px;
        }

        .btn-group-toggle .btn.active {
            background: #231475 !important;
            color: white !important;
            border-color: #231475 !important;
        }

        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <!-- NAVBAR -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
                <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>

                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="ti-user mr-1"></i> <?= h($nomeUsuario) ?> (ADMIN)
                        </span>
                    </li>
                </ul>

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
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
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
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="container-fluid page-body-wrapper">

            <!-- SIDEBAR -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">

                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="icon-grid menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>

                    <!-- CADASTROS -->
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#feiraCadastros">
                            <i class="ti-id-badge menu-icon"></i>
                            <span class="menu-title">Cadastros</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <div class="collapse" id="feiraCadastros">
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

                    <!-- MOVIMENTO -->
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

                    <!-- RELATÓRIOS -->
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
                            <i class="ti-clipboard menu-icon"></i>
                            <span class="menu-title">Relatórios</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <div class="collapse text-black show" id="feiraRelatorios">
                            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                                <style>
                                    .sub-menu .nav-item .nav-link {
                                        color: black !important;
                                    }

                                    .sub-menu .nav-item .nav-link:hover {
                                        color: blue !important;
                                    }
                                </style>

                                <!-- ESTE ARQUIVO -->
                                <li class="nav-item active">
                                    <a class="nav-link active" href="./relatorioFinanceiro.php" style="color:white !important; background: #231475C5 !important;">
                                        <i class="ti-user mr-2"></i> Relatório Individual do Produtor
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

                    <!-- Título DIVERSOS -->
                    <li class="nav-item" style="pointer-events:none;">
                        <span style="
                  display:block;
                  padding: 5px 15px 5px;
                  font-size: 11px;
                  font-weight: 600;
                  letter-spacing: 1px;
                  color: #6c757d;
                  text-transform: uppercase;
                ">
                            Links Diversos
                        </span>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="ti-home menu-icon"></i>
                            <span class="menu-title"> Painel Principal</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../alternativa/" class="nav-link">
                            <i class="ti-shopping-cart menu-icon"></i>
                            <span class="menu-title">Feira do Alternativa</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../mercado/" class="nav-link">
                            <i class="ti-shopping-cart menu-icon"></i>
                            <span class="menu-title">Mercado Municipal</span>
                        </a>
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

                    <!-- CABEÇALHO -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div>
                                    <h2 class="font-weight-bold mb-1">Relatório Individual do Produtor</h2>
                                    <span class="badge badge-primary">
                                        Feira do Produtor — <?= h($labelPeriodo) ?>
                                    </span>
                                    <?php if (!$err && $produtorId): ?>
                                        <span class="badge badge-soft ml-2">
                                            <i class="ti-user mr-1"></i> <?= h($nomeProdutor) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                        </div>
                    </div>

                    <!-- FILTRO -->
                    <div class="card mb-4">
                        <div class="card-body py-3">

                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                        <label class="btn btn-outline-primary <?= $tipoFiltro === 'mes' ? 'active' : '' ?>">
                                            <input type="radio" name="tipo_filtro" value="mes" <?= $tipoFiltro === 'mes' ? 'checked' : '' ?>>
                                            <i class="ti-calendar mr-1"></i> Filtrar por Mês
                                        </label>
                                        <label class="btn btn-outline-primary <?= $tipoFiltro === 'dia' ? 'active' : '' ?>">
                                            <input type="radio" name="tipo_filtro" value="dia" <?= $tipoFiltro === 'dia' ? 'checked' : '' ?>>
                                            <i class="ti-time mr-1"></i> Filtrar por Dia
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row align-items-end">
                                <div class="col-md-5 mb-2">
                                    <label class="mb-1" id="label-data"><?= $tipoFiltro === 'dia' ? 'Data' : 'Mês' ?></label>
                                    <input
                                        type="<?= $tipoFiltro === 'dia' ? 'date' : 'month' ?>"
                                        class="form-control"
                                        id="input-data"
                                        value="<?= h($tipoFiltro === 'dia' ? $periodStart : substr($periodStart, 0, 7)) ?>">
                                </div>

                                <div class="col-md-5 mb-2">
                                    <label class="mb-1">Produtor</label>
                                    <select class="form-control" id="select-produtor">
                                        <?php if (empty($produtoresList)): ?>
                                            <option value="0">Nenhum produtor ativo</option>
                                        <?php else: ?>
                                            <?php foreach ($produtoresList as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$produtorId ? 'selected' : '' ?>>
                                                    <?= h($p['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <button type="button" class="btn btn-primary w-100" id="btn-filtrar">
                                        <i class="ti-search mr-1"></i> Filtrar
                                    </button>
                                </div>

                                <div class="col-md-2 mb-2 d-none"></div>

                                <div class="col-md-2 mb-2">
                                    <a href="./relatorioFinanceiro.php" class="btn btn-outline-secondary w-100">
                                        <i class="ti-reload mr-1"></i> Limpar
                                    </a>
                                </div>

                            </div>

                            <div class="mt-2 mini-kpi">
                                * O total do produtor é calculado pelos itens vendidos (SUM(vi.subtotal)) filtrando pelo produtor.
                            </div>

                        </div>
                    </div>

                    <?php if (!$err && $produtorInfo): ?>
                        <!-- DADOS DO PRODUTOR -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mini-kpi">Produtor</div>
                                        <div class="font-weight-bold"><?= h($nomeProdutor) ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mini-kpi">Contato</div>
                                        <div class="font-weight-bold"><?= h($contatoProdutor ?: '—') ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mini-kpi">Documento</div>
                                        <div class="font-weight-bold"><?= h($docProdutor ?: '—') ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mini-kpi">Comunidade</div>
                                        <div class="font-weight-bold"><?= h($comunidadeProdutor ?: '—') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPIs -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="kpi-card">
                                    <p class="kpi-label">Total do produtor</p>
                                    <p class="kpi-value"><?= brl((float)$resumo['total']) ?></p>
                                    <p class="kpi-sub">Período: <?= h($labelPeriodo) ?></p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="kpi-card">
                                    <p class="kpi-label">Vendas (qtd)</p>
                                    <p class="kpi-value"><?= (int)$resumo['vendas_qtd'] ?></p>
                                    <p class="kpi-sub">Ticket médio: <?= brl((float)$resumo['ticket']) ?></p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="kpi-card">
                                    <p class="kpi-label">Itens (quantidade)</p>
                                    <p class="kpi-value"><?= number_format((float)$resumo['itens_qtd'], 3, ',', '.') ?></p>
                                    <p class="kpi-sub">Somatório de vi.quantidade</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="kpi-card">
                                    <p class="kpi-label">Produtos distintos</p>
                                    <p class="kpi-value"><?= (int)$resumo['produtos_distintos'] ?></p>
                                    <p class="kpi-sub">Produtos vendidos no período</p>
                                </div>
                            </div>
                        </div>

                        <!-- POR PAGAMENTO -->
                        <?php if (!empty($porPagamento)): ?>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <h4 class="mb-2 font-weight-bold">Resumo por Forma de Pagamento</h4>
                                        <span class="mini-kpi">Agrupado por v.forma_pagamento (somando itens do produtor)</span>
                                    </div>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Pagamento</th>
                                                    <th class="text-center">Vendas</th>
                                                    <th class="text-right">Total (produtor)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($porPagamento as $pg): ?>
                                                    <tr>
                                                        <td><span class="badge badge-soft"><?= h($pg['pagamento']) ?></span></td>
                                                        <td class="text-center"><?= (int)$pg['vendas_qtd'] ?></td>
                                                        <td class="text-right font-weight-bold"><?= brl((float)$pg['total']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- PRODUTOS VENDIDOS -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <h4 class="mb-2 font-weight-bold">Produtos Vendidos (do produtor)</h4>
                                    <span class="mini-kpi">Página <?= (int)$pageProdutos ?> de <?= (int)$totalPagesProdutos ?></span>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th class="text-center">Unid.</th>
                                                <th class="text-right">Qtd</th>
                                                <th class="text-right">Total</th>
                                                <th class="text-right">Média (R$/unid)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($porProduto)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">Nenhum produto vendido no período.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($porProduto as $pr): ?>
                                                    <?php
                                                    $qtd = (float)$pr['quantidade'];
                                                    $tot = (float)$pr['total'];
                                                    $media = $qtd > 0 ? ($tot / $qtd) : 0;
                                                    $un = $pr['unidade_sigla'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td class="font-weight-bold"><?= h($pr['nome']) ?></td>
                                                        <td><?= h($pr['categoria_nome'] ?? '—') ?></td>
                                                        <td class="text-center"><?= h($un ?: '—') ?></td>
                                                        <td class="text-right"><?= number_format($qtd, 3, ',', '.') ?></td>
                                                        <td class="text-right font-weight-bold"><?= brl($tot) ?></td>
                                                        <td class="text-right"><?= brl($media) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalPagesProdutos > 1): ?>
                                    <nav aria-label="Paginação produtos">
                                        <ul class="pagination mb-0">
                                            <?php
                                            $prev = max(1, $pageProdutos - 1);
                                            $next = min($totalPagesProdutos, $pageProdutos + 1);
                                            ?>
                                            <li class="page-item <?= $pageProdutos <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= h(url_with(['page_produtos' => $prev])) ?>">&laquo;</a>
                                            </li>

                                            <?php
                                            $start = max(1, $pageProdutos - 2);
                                            $end = min($totalPagesProdutos, $pageProdutos + 2);
                                            for ($i = $start; $i <= $end; $i++):
                                            ?>
                                                <li class="page-item <?= $i === $pageProdutos ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= h(url_with(['page_produtos' => $i])) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <li class="page-item <?= $pageProdutos >= $totalPagesProdutos ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= h(url_with(['page_produtos' => $next])) ?>">&raquo;</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- POR DIA (somente no mês) -->
                        <?php if ($tipoFiltro === 'mes'): ?>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <h4 class="mb-2 font-weight-bold">Resumo por Dia (do produtor)</h4>
                                        <span class="mini-kpi">Página <?= (int)$pageDias ?> de <?= (int)$totalPagesDias ?></span>
                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Dia</th>
                                                    <th class="text-center">Vendas</th>
                                                    <th class="text-right">Itens (qtd)</th>
                                                    <th class="text-right">Total (produtor)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($porDia)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">Sem movimentação no período.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($porDia as $d): ?>
                                                        <tr>
                                                            <td class="font-weight-bold"><?= h(date('d/m/Y', strtotime((string)$d['dia']))) ?></td>
                                                            <td class="text-center"><?= (int)$d['vendas_qtd'] ?></td>
                                                            <td class="text-right"><?= number_format((float)$d['itens_qtd'], 3, ',', '.') ?></td>
                                                            <td class="text-right font-weight-bold"><?= brl((float)$d['total']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($totalPagesDias > 1): ?>
                                        <nav aria-label="Paginação dias">
                                            <ul class="pagination mb-0">
                                                <?php
                                                $prev = max(1, $pageDias - 1);
                                                $next = min($totalPagesDias, $pageDias + 1);
                                                ?>
                                                <li class="page-item <?= $pageDias <= 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= h(url_with(['page_dias' => $prev])) ?>">&laquo;</a>
                                                </li>

                                                <?php
                                                $start = max(1, $pageDias - 2);
                                                $end = min($totalPagesDias, $pageDias + 2);
                                                for ($i = $start; $i <= $end; $i++):
                                                ?>
                                                    <li class="page-item <?= $i === $pageDias ? 'active' : '' ?>">
                                                        <a class="page-link" href="<?= h(url_with(['page_dias' => $i])) ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>

                                                <li class="page-item <?= $pageDias >= $totalPagesDias ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= h(url_with(['page_dias' => $next])) ?>">&raquo;</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- VENDAS -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <h4 class="mb-2 font-weight-bold">Vendas do Produtor</h4>
                                    <span class="mini-kpi">Página <?= (int)$pageVendas ?> de <?= (int)$totalPagesVendas ?></span>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Data/Hora</th>
                                                <th class="text-center">Pagamento</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-right">Itens (qtd)</th>
                                                <th class="text-right">Total (produtor)</th>
                                                <th class="text-right">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($vendasRows)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">Nenhuma venda para este produtor no período.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($vendasRows as $v): ?>
                                                    <?php
                                                    $dt = $v['data_hora_ref'] ? date('d/m/Y H:i', strtotime((string)$v['data_hora_ref'])) : '—';
                                                    $pg = (string)($v['forma_pagamento'] ?? 'N/I');
                                                    $stt = (string)($v['status'] ?? 'N/I');
                                                    ?>
                                                    <tr>
                                                        <td class="font-weight-bold"><?= (int)$v['id'] ?></td>
                                                        <td><?= h($dt) ?></td>
                                                        <td class="text-center"><span class="badge badge-soft"><?= h(mb_strtoupper($pg)) ?></span></td>
                                                        <td class="text-center"><?= h($stt) ?></td>
                                                        <td class="text-right"><?= number_format((float)$v['itens_qtd'], 3, ',', '.') ?></td>
                                                        <td class="text-right font-weight-bold"><?= brl((float)$v['total_produtor']) ?></td>
                                                        <td class="text-right">
                                                            <a class="btn btn-sm btn-outline-primary"
                                                                href="<?= h(url_with(['venda_id' => (int)$v['id']])) ?>">
                                                                <i class="ti-eye mr-1"></i> Itens
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalPagesVendas > 1): ?>
                                    <nav aria-label="Paginação vendas">
                                        <ul class="pagination mb-0">
                                            <?php
                                            $prev = max(1, $pageVendas - 1);
                                            $next = min($totalPagesVendas, $pageVendas + 1);
                                            ?>
                                            <li class="page-item <?= $pageVendas <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= h(url_with(['page_vendas' => $prev])) ?>">&laquo;</a>
                                            </li>

                                            <?php
                                            $start = max(1, $pageVendas - 2);
                                            $end = min($totalPagesVendas, $pageVendas + 2);
                                            for ($i = $start; $i <= $end; $i++):
                                            ?>
                                                <li class="page-item <?= $i === $pageVendas ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= h(url_with(['page_vendas' => $i])) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <li class="page-item <?= $pageVendas >= $totalPagesVendas ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= h(url_with(['page_vendas' => $next])) ?>">&raquo;</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DETALHE DE ITENS DA VENDA -->
                        <?php if ($vendaId && $vendaDetalhe): ?>
                            <div class="card mb-5">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <h4 class="mb-1 font-weight-bold">Itens da Venda #<?= (int)$vendaDetalhe['id'] ?> (do produtor)</h4>
                                            <div class="mini-kpi">
                                                Data/Hora: <?= h($vendaDetalhe['data_hora_ref'] ? date('d/m/Y H:i', strtotime((string)$vendaDetalhe['data_hora_ref'])) : '—') ?>
                                                <?php if ($colFormaPgto): ?> • Pagamento: <?= h(mb_strtoupper((string)$vendaDetalhe['forma_pagamento'])) ?><?php endif; ?>
                                                    <?php if ($colStatus): ?> • Status: <?= h((string)$vendaDetalhe['status']) ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="mini-kpi">Total (produtor)</div>
                                            <div class="font-weight-bold" style="font-size:18px;"><?= brl((float)$vendaDetalhe['total_produtor']) ?></div>
                                            <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= h(url_with(['venda_id' => null])) ?>">
                                                <i class="ti-close mr-1"></i> Fechar
                                            </a>
                                        </div>
                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="text-right">Qtd</th>
                                                    <th class="text-right">V. Unit</th>
                                                    <th class="text-right">Subtotal</th>
                                                    <th>Obs.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($vendaItens)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">Sem itens para este produtor nesta venda.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($vendaItens as $it): ?>
                                                        <tr>
                                                            <td class="font-weight-bold"><?= h($it['item_nome']) ?></td>
                                                            <td class="text-right"><?= number_format((float)$it['quantidade'], 3, ',', '.') ?></td>
                                                            <td class="text-right"><?= brl((float)$it['valor_unitario']) ?></td>
                                                            <td class="text-right font-weight-bold"><?= brl((float)$it['subtotal']) ?></td>
                                                            <td><?= h((string)($it['observacao'] ?? '')) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>

                <!-- FOOTER -->
                <footer class="footer">
                    <span class="text-muted">
                        © <?= date('Y') ?> SIGRelatórios —
                        <a href="https://www.lucascorrea.pro/" target="_blank">lucascorrea.pro</a>
                    </span>
                </footer>

            </div>
        </div>
    </div>

    <script src="../../../vendors/js/vendor.bundle.base.js"></script>
    <script src="../../../js/off-canvas.js"></script>
    <script src="../../../js/hoverable-collapse.js"></script>
    <script src="../../../js/template.js"></script>
    <script src="../../../js/settings.js"></script>
    <script src="../../../js/todolist.js"></script>

    <script>
        (function() {
            const btn = document.getElementById('btn-filtrar');
            const inputData = document.getElementById('input-data');
            const selProd = document.getElementById('select-produtor');

            function getTipo() {
                const r = document.querySelector('input[name="tipo_filtro"]:checked');
                return r ? r.value : 'mes';
            }

            btn && btn.addEventListener('click', function() {
                const tipo = getTipo();
                let data = (inputData && inputData.value) ? inputData.value.trim() : '';
                const produtorId = selProd ? selProd.value : '0';

                // para mês, o input type="month" já vem YYYY-MM
                // para dia, vem YYYY-MM-DD
                const params = new URLSearchParams(window.location.search);
                params.set('tipo', tipo);
                params.set('data', data);
                params.set('produtor_id', produtorId);

                // reset páginas ao filtrar
                params.delete('page_produtos');
                params.delete('page_dias');
                params.delete('page_vendas');
                params.delete('venda_id');

                window.location.href = window.location.pathname + '?' + params.toString();
            });
        })();
    </script>
</body>

</html>