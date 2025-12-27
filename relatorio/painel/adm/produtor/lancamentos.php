<?php
declare(strict_types=1);
session_start();

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

/* Obrigatório ser ADMIN */
$perfis = $_SESSION['perfis'] ?? [];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function to_decimal($v): float {
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '0';
  if ($s === '' || $s === '-' || $s === '.') return 0.0;
  return (float)$s;
}

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* ===== Conexão ===== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* Feira do Produtor = 1 (na Feira Alternativa use 2) */
$feiraId = 1;

/* ===== Filtros ===== */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
if ($dia === '') $dia = date('Y-m-d');
$prodFiltro = (int)($_GET['produtor'] ?? 0);

/* ===== Combos ===== */
$produtoresAtivos = [];
$produtosAtivos   = [];

try {
  $stP = $pdo->prepare("SELECT id, nome FROM produtores WHERE feira_id = :f AND ativo = 1 ORDER BY nome ASC");
  $stP->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $stP->execute();
  $produtoresAtivos = $stP->fetchAll();

  $stPr = $pdo->prepare("
    SELECT
      p.id,
      p.nome,
      COALESCE(c.nome,'')  AS categoria_nome,
      COALESCE(u.sigla,'') AS unidade_sigla,
      COALESCE(pr.nome,'') AS produtor_nome,
      p.preco_referencia
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades u   ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
    LEFT JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
    WHERE p.feira_id = :f AND p.ativo = 1
    ORDER BY p.nome ASC
  ");
  $stPr->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $stPr->execute();
  $produtosAtivos = $stPr->fetchAll();
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os cadastros agora.';
}

/* ===== POST (Salvar / Excluir) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./lancamentos.php');
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');

  /* EXCLUIR VENDA */
  if ($acao === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      $_SESSION['flash_err'] = 'Lançamento inválido.';
      header('Location: ./lancamentos.php?dia='.urlencode($dia).'&produtor='.(int)$prodFiltro);
      exit;
    }

    try {
      $pdo->beginTransaction();

      $d1 = $pdo->prepare("DELETE FROM venda_itens WHERE feira_id = :f AND venda_id = :id");
      $d1->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $d1->bindValue(':id', $id, PDO::PARAM_INT);
      $d1->execute();

      $d2 = $pdo->prepare("DELETE FROM vendas WHERE feira_id = :f AND id = :id");
      $d2->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $d2->bindValue(':id', $id, PDO::PARAM_INT);
      $d2->execute();

      $pdo->commit();
      $_SESSION['flash_ok'] = 'Lançamento excluído com sucesso.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível excluir o lançamento agora.';
    }

    header('Location: ./lancamentos.php?dia='.urlencode($dia).'&produtor='.(int)$prodFiltro);
    exit;
  }

  /* SALVAR VENDA */
  if ($acao === 'salvar') {
    $dataVenda  = trim((string)($_POST['data_venda'] ?? ''));
    $horaVenda  = trim((string)($_POST['hora_venda'] ?? ''));
    $formaPag   = trim((string)($_POST['forma_pagamento'] ?? ''));
    $obs        = trim((string)($_POST['observacao'] ?? ''));

    $produtoIds = $_POST['produto_id'] ?? [];
    $qtds       = $_POST['qtd'] ?? [];
    $vunit      = $_POST['valor_unit'] ?? [];

    $localErr = '';
    if ($dataVenda === '') $localErr = 'Informe a data.';
    if ($localErr === '' && $horaVenda === '') $localErr = 'Informe a hora.';
    if ($localErr === '' && $formaPag === '') $localErr = 'Selecione a forma de pagamento.';

    $dataHora = '';
    if ($localErr === '') {
      // yyyy-mm-dd + hh:mm
      $horaVenda = preg_replace('/[^0-9:]/', '', $horaVenda) ?? '';
      if (!preg_match('/^\d{2}:\d{2}$/', $horaVenda)) $localErr = 'Hora inválida (use HH:MM).';
      else $dataHora = $dataVenda.' '.$horaVenda.':00';
    }

    $itens = [];
    $total = 0.0;

    if ($localErr === '') {
      $n = max(count((array)$produtoIds), count((array)$qtds), count((array)$vunit));
      for ($i = 0; $i < $n; $i++) {
        $pid = (int)($produtoIds[$i] ?? 0);
        if ($pid <= 0) continue;

        $q = to_decimal($qtds[$i] ?? '1');
        if ($q <= 0) $q = 1.0;

        $vu = to_decimal($vunit[$i] ?? '0');
        if ($vu <= 0) continue;

        $sub = $q * $vu;
        $total += $sub;

        $itens[] = [
          'produto_id' => $pid,
          'quantidade' => $q,
          'valor_unit' => $vu,
          'subtotal'   => $sub
        ];
      }

      if (empty($itens)) $localErr = 'Adicione pelo menos 1 item (produto + valor).';
      elseif ($total <= 0) $localErr = 'Total inválido.';
    }

    if ($localErr !== '') {
      $_SESSION['flash_err'] = $localErr;
      header('Location: ./lancamentos.php?dia='.urlencode($dia).'&produtor='.(int)$prodFiltro);
      exit;
    }

    try {
      $pdo->beginTransaction();

      $ins = $pdo->prepare("
        INSERT INTO vendas (feira_id, data_hora, forma_pagamento, total, status, observacao)
        VALUES (:f, :dh, :fp, :total, 'FECHADA', :obs)
      ");
      $ins->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $ins->bindValue(':dh', $dataHora, PDO::PARAM_STR);
      $ins->bindValue(':fp', $formaPag, PDO::PARAM_STR);
      $ins->bindValue(':total', number_format($total, 2, '.', ''), PDO::PARAM_STR);
      if ($obs === '') $ins->bindValue(':obs', null, PDO::PARAM_NULL);
      else $ins->bindValue(':obs', $obs, PDO::PARAM_STR);
      $ins->execute();

      $vendaId = (int)$pdo->lastInsertId();

      $insItem = $pdo->prepare("
        INSERT INTO venda_itens (feira_id, venda_id, produto_id, quantidade, valor_unitario, subtotal)
        VALUES (:f, :v, :p, :q, :vu, :sub)
      ");

      foreach ($itens as $it) {
        $insItem->bindValue(':f', $feiraId, PDO::PARAM_INT);
        $insItem->bindValue(':v', $vendaId, PDO::PARAM_INT);
        $insItem->bindValue(':p', (int)$it['produto_id'], PDO::PARAM_INT);
        $insItem->bindValue(':q', number_format((float)$it['quantidade'], 3, '.', ''), PDO::PARAM_STR);
        $insItem->bindValue(':vu', number_format((float)$it['valor_unit'], 2, '.', ''), PDO::PARAM_STR);
        $insItem->bindValue(':sub', number_format((float)$it['subtotal'], 2, '.', ''), PDO::PARAM_STR);
        $insItem->execute();
      }

      $pdo->commit();
      $_SESSION['flash_ok'] = 'Lançamento registrado com sucesso.';
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $mysqlCode = (int)($e->errorInfo[1] ?? 0);
      if ($mysqlCode === 1146) $_SESSION['flash_err'] = 'As tabelas de lançamentos não existem (vendas / venda_itens). Rode o SQL das tabelas.';
      else $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
    }

    header('Location: ./lancamentos.php?dia='.urlencode($dia).'&produtor='.(int)$prodFiltro);
    exit;
  }
}

/* ===== Listagem (VENDAS) ===== */
$vendas = [];
$itensPorVenda = []; // [venda_id => [itens...]]
try {
  $sql = "
    SELECT
      v.id,
      v.data_hora,
      v.forma_pagamento,
      v.total,
      v.status,
      v.observacao,
      GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR '||') AS produtores_lista
    FROM vendas v
    LEFT JOIN venda_itens vi
      ON vi.feira_id = v.feira_id AND vi.venda_id = v.id
    LEFT JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    LEFT JOIN produtores pr
      ON pr.feira_id = p.feira_id AND pr.id = p.produtor_id
    WHERE v.feira_id = :f
      AND DATE(v.data_hora) = :dia
  ";
  $params = [':f' => $feiraId, ':dia' => $dia];

  if ($prodFiltro > 0) {
    $sql .= "
      AND EXISTS (
        SELECT 1
        FROM venda_itens vi2
        JOIN produtos p2 ON p2.feira_id = vi2.feira_id AND p2.id = vi2.produto_id
        WHERE vi2.feira_id = v.feira_id
          AND vi2.venda_id = v.id
          AND p2.produtor_id = :prod
      )
    ";
    $params[':prod'] = $prodFiltro;
  }

  $sql .= " GROUP BY v.id ORDER BY v.id DESC";

  $st = $pdo->prepare($sql);
  $st->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $st->bindValue(':dia', $dia, PDO::PARAM_STR);
  if (isset($params[':prod'])) $st->bindValue(':prod', (int)$params[':prod'], PDO::PARAM_INT);
  $st->execute();
  $vendas = $st->fetchAll();

  /* Carregar itens das vendas (para o Visualizar funcionar) */
  $ids = array_map(fn($r) => (int)($r['id'] ?? 0), $vendas);
  $ids = array_values(array_filter($ids, fn($x) => $x > 0));
  if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stItens = $pdo->prepare("
      SELECT
        vi.venda_id,
        vi.id AS item_id,
        vi.quantidade,
        vi.valor_unitario,
        vi.subtotal,
        COALESCE(p.nome,'')   AS produto_nome,
        COALESCE(u.sigla,'')  AS unidade_sigla,
        COALESCE(c.nome,'')   AS categoria_nome,
        COALESCE(pr.nome,'')  AS produtor_nome
      FROM venda_itens vi
      LEFT JOIN produtos p
        ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
      LEFT JOIN unidades u
        ON u.feira_id = p.feira_id AND u.id = p.unidade_id
      LEFT JOIN categorias c
        ON c.feira_id = p.feira_id AND c.id = p.categoria_id
      LEFT JOIN produtores pr
        ON pr.feira_id = p.feira_id AND pr.id = p.produtor_id
      WHERE vi.feira_id = ?
        AND vi.venda_id IN ($placeholders)
      ORDER BY vi.venda_id DESC, vi.id ASC
    ");

    $bind = array_merge([$feiraId], $ids);
    $stItens->execute($bind);
    $rowsItens = $stItens->fetchAll();

    foreach ($rowsItens as $it) {
      $vid = (int)($it['venda_id'] ?? 0);
      if ($vid <= 0) continue;
      if (!isset($itensPorVenda[$vid])) $itensPorVenda[$vid] = [];
      $itensPorVenda[$vid][] = $it;
    }
  }

} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os lançamentos agora.';
}

/* defaults de hora no form */
$horaPadrao = date('H:i');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Lançamentos (Vendas)</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }

    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .form-control { height: 42px; }
    .btn { height: 42px; }
    .helper{ font-size: 12px; }

    .acoes-wrap{ display:flex; flex-wrap:wrap; gap:8px; }
    .btn-xs{ padding: .25rem .5rem; font-size: .75rem; line-height: 1.2; height:auto; }
    .table td, .table th{ vertical-align: middle !important; }

    .totbox{
      border: 1px solid rgba(0,0,0,.08);
      background: #fff;
      border-radius: 12px;
      padding: 10px 12px;
    }
    .totlabel{ font-size: 12px; color: #6c757d; margin:0; }
    .totvalue{ font-size: 20px; font-weight: 800; margin:0; }

    /* ===== Flash “Hostinger style” (top-right, menor, ~6s) ===== */
    .sig-flash-wrap{
      position: fixed;
      top: 78px;
      right: 18px;
      left: auto;
      width: min(420px, calc(100vw - 36px));
      z-index: 9999;
      pointer-events: none;
    }
    .sig-toast.alert{
      pointer-events: auto;
      border: 0 !important;
      border-left: 6px solid !important;
      border-radius: 14px !important;
      padding: 10px 12px !important;
      box-shadow: 0 10px 28px rgba(0,0,0,.10) !important;
      font-size: 13px !important;
      margin-bottom: 10px !important;

      opacity: 0;
      transform: translateX(10px);
      animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }
    .sig-toast--success{ background:#f1fff6 !important; border-left-color:#22c55e !important; }
    .sig-toast--danger { background:#fff1f2 !important; border-left-color:#ef4444 !important; }

    .sig-toast__row{ display:flex; align-items:flex-start; gap:10px; }
    .sig-toast__icon i{ font-size:16px; margin-top:2px; }
    .sig-toast__title{ font-weight:800; margin-bottom:1px; line-height: 1.1; }
    .sig-toast__text{ margin:0; line-height: 1.25; }

    .sig-toast .close{ opacity:.55; font-size: 18px; line-height: 1; padding: 0 6px; }
    .sig-toast .close:hover{ opacity:1; }

    @keyframes sigToastIn{ to{ opacity:1; transform: translateX(0); } }
    @keyframes sigToastOut{ to{ opacity:0; transform: translateX(12px); visibility:hidden; } }

    /* Feirantes em linhas */
    .feirantes-lines{ line-height: 1.35; }
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

    <!-- SIDEBAR (mantida) -->
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">

        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="icon-grid menu-icon"></i>
            <span class="menu-title">Dashboard</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
            <i class="ti-id-badge menu-icon"></i>
            <span class="menu-title">Cadastros</span>
            <i class="menu-arrow"></i>
          </a>

          <div class="collapse" id="feiraCadastros">
            <style>
              .sub-menu .nav-item .nav-link { color: black !important; }
              .sub-menu .nav-item .nav-link:hover { color: blue !important; }
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

        <!-- MOVIMENTO ATIVO -->
        <li class="nav-item active">
          <a class="nav-link open" data-toggle="collapse" href="#feiraMovimento" aria-expanded="true" aria-controls="feiraMovimento">
            <i class="ti-exchange-vertical menu-icon"></i>
            <span class="menu-title">Movimento</span>
            <i class="menu-arrow"></i>
          </a>

          <div class="collapse show" id="feiraMovimento">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item active">
                <a class="nav-link" href="./lancamentos.php" style="color:white !important; background: #231475C5 !important;">
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

    <!-- MAIN -->
    <div class="main-panel">
      <div class="content-wrapper">

        <div class="row">
          <div class="col-12 mb-3">
            <h3 class="font-weight-bold">Lançamentos (Vendas)</h3>
            <h6 class="font-weight-normal mb-0">Página simplificada: menos campos, mais rápido de lançar.</h6>
          </div>
        </div>

        <!-- NOVO LANÇAMENTO -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Novo Lançamento</h4>
                    <p class="card-description mb-0">Selecione os itens (cada item já vem com feirante do produto).</p>
                  </div>
                  <div class="totbox mt-2 mt-md-0">
                    <p class="totlabel">Total</p>
                    <p class="totvalue" id="jsTotal">R$ 0,00</p>
                  </div>
                </div>

                <hr>

                <?php if (empty($produtosAtivos)): ?>
                  <div class="alert alert-warning mb-3" role="alert" style="border-radius:12px;">
                    <b>Atenção:</b> para lançar vendas, você precisa ter <b>produtos</b> ativos cadastrados.
                  </div>
                <?php endif; ?>

                <form method="post" action="./lancamentos.php?dia=<?= h($dia) ?>&produtor=<?= (int)$prodFiltro ?>" autocomplete="off">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="acao" value="salvar">

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Data</label>
                      <input type="date" class="form-control" name="data_venda" value="<?= h($dia) ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Hora</label>
                      <input type="time" class="form-control" name="hora_venda" value="<?= h($horaPadrao) ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Pagamento</label>
                      <select class="form-control" name="forma_pagamento" required>
                        <option value="" selected disabled>Selecione</option>
                        <option value="DINHEIRO">Dinheiro</option>
                        <option value="PIX">Pix</option>
                        <option value="CARTAO">Cartão</option>
                        <option value="OUTROS">Outros</option>
                      </select>
                      <small class="text-muted helper">Como foi pago.</small>
                    </div>

                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Observação</label>
                      <input type="text" class="form-control" name="observacao" placeholder="Opcional">
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="itensTable">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th style="width:160px;">Feirante</th>
                          <th style="width:110px;">Qtd</th>
                          <th style="width:150px;">Valor (R$)</th>
                          <th style="width:110px;">Unid</th>
                          <th style="width:220px;">Categoria</th>
                          <th style="width:90px;" class="text-right">—</th>
                        </tr>
                      </thead>
                      <tbody id="itensBody">
                        <tr class="js-item-row">
                          <td>
                            <select class="form-control js-prod" name="produto_id[]">
                              <option value="0">—</option>
                              <?php foreach ($produtosAtivos as $pr): ?>
                                <option
                                  value="<?= (int)$pr['id'] ?>"
                                  data-un="<?= h($pr['unidade_sigla'] ?? '') ?>"
                                  data-cat="<?= h($pr['categoria_nome'] ?? '') ?>"
                                  data-fei="<?= h($pr['produtor_nome'] ?? '') ?>"
                                  data-preco="<?= h((string)($pr['preco_referencia'] ?? '')) ?>"
                                >
                                  <?= h($pr['nome'] ?? '') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </td>
                          <td>
                            <input type="text" class="form-control js-fei" value="" readonly>
                          </td>
                          <td>
                            <input type="text" class="form-control js-qtd" name="qtd[]" value="1">
                          </td>
                          <td>
                            <input type="text" class="form-control js-vu" name="valor_unit[]" placeholder="0,00">
                          </td>
                          <td>
                            <input type="text" class="form-control js-un" value="" readonly>
                          </td>
                          <td>
                            <input type="text" class="form-control js-cat" value="" readonly>
                          </td>
                          <td class="text-right">
                            <button type="button" class="btn btn-light btn-xs js-remove" title="Remover linha" disabled>
                              <i class="ti-trash"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="d-flex flex-wrap justify-content-between align-items-center mt-3" style="gap:8px;">
                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="button" class="btn btn-light" id="btnAddLinha">
                        <i class="ti-plus mr-1"></i> Adicionar linha
                      </button>
                      <button type="button" class="btn btn-light" id="btnPrecoRef">
                        <i class="ti-tag mr-1"></i> Preencher valor ref.
                      </button>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary">
                        <i class="ti-save mr-1"></i> Salvar
                      </button>
                      <a href="./lancamentos.php?dia=<?= h($dia) ?>&produtor=<?= (int)$prodFiltro ?>" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Limpar
                      </a>
                    </div>
                  </div>

                  <small class="text-muted d-block mt-2">
                    Dica: o feirante aparece automaticamente conforme o produto escolhido.
                  </small>

                </form>

              </div>
            </div>
          </div>
        </div>

        <!-- LISTAGEM -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Lançamentos</h4>
                    <p class="card-description mb-0">Mostrando <?= (int)count($vendas) ?> registro(s).</p>
                  </div>
                </div>

                <div class="row mt-3">
                  <div class="col-md-3 mb-2">
                    <label class="mb-1">Dia</label>
                    <input type="date" class="form-control" value="<?= h($dia) ?>" onchange="location.href='?dia='+this.value+'&produtor=<?= (int)$prodFiltro ?>';">
                  </div>
                  <div class="col-md-6 mb-2">
                    <label class="mb-1">Filtrar por Feirante</label>
                    <select class="form-control" onchange="location.href='?dia=<?= h($dia) ?>&produtor='+this.value;">
                      <option value="0">Todos</option>
                      <?php foreach ($produtoresAtivos as $p): $pid=(int)$p['id']; ?>
                        <option value="<?= $pid ?>" <?= $prodFiltro===$pid ? 'selected' : '' ?>><?= h($p['nome'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <small class="text-muted helper">Mostra vendas que tenham itens daquele feirante.</small>
                  </div>
                  <div class="col-md-3 mb-2 d-flex align-items-end">
                    <a class="btn btn-light w-100" href="./lancamentos.php">
                      <i class="ti-close mr-1"></i> Limpar filtros
                    </a>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th style="width:90px;">ID</th>
                        <th style="width:160px;">Data/Hora</th>
                        <th style="width:130px;">Pagamento</th>
                        <th>Feirante(s)</th>
                        <th style="width:150px;">Total</th>
                        <th style="width:120px;">Status</th>
                        <th style="min-width:250px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($vendas)): ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted py-4">Nenhum lançamento encontrado.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($vendas as $v): ?>
                          <?php
                            $vid = (int)($v['id'] ?? 0);
                            $tot = (float)($v['total'] ?? 0);
                            $dt  = (string)($v['data_hora'] ?? '');
                            $fp  = (string)($v['forma_pagamento'] ?? '');
                            $st  = (string)($v['status'] ?? '');

                            $prodStr = (string)($v['produtores_lista'] ?? '');
                            $plist = [];
                            if ($prodStr !== '') {
                              $plist = array_values(array_filter(array_map('trim', explode('||', $prodStr)), fn($x)=>$x!==''));
                            }
                            if (empty($plist)) $plist = ['—'];

                            $badge = 'badge-secondary';
                            if (strtoupper($st) === 'FECHADA') $badge = 'badge-success';
                            if (strtoupper($st) === 'ABERTA')  $badge = 'badge-warning';
                            if (strtoupper($st) === 'CANCELADA') $badge = 'badge-danger';
                          ?>
                          <tr>
                            <td><?= $vid ?></td>
                            <td><?= h($dt) ?></td>
                            <td><?= h($fp) ?></td>

                            <!-- FEIRANTES EM LINHAS (cada um em uma linha) -->
                            <td class="feirantes-lines">
                              <?php if (empty($plist) || (count($plist) === 1 && trim((string)$plist[0]) === '—')): ?>
                                —
                              <?php else: ?>
                                <?= implode('<br>', array_map(fn($x) => h((string)$x), $plist)) ?>
                              <?php endif; ?>
                            </td>

                            <td><b>R$ <?= number_format($tot, 2, ',', '.') ?></b></td>
                            <td><label class="badge <?= $badge ?>"><?= h($st) ?></label></td>
                            <td>
                              <div class="acoes-wrap">
                                <button type="button" class="btn btn-outline-primary btn-xs" data-toggle="modal" data-target="#modalVenda<?= $vid ?>">
                                  <i class="ti-eye"></i> Visualizar
                                </button>

                                <form method="post" class="m-0">
                                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                  <input type="hidden" name="acao" value="excluir">
                                  <input type="hidden" name="id" value="<?= $vid ?>">
                                  <button type="submit" class="btn btn-outline-danger btn-xs"
                                    onclick="return confirm('Excluir este lançamento? Essa ação não pode ser desfeita.');">
                                    <i class="ti-trash"></i> Excluir
                                  </button>
                                </form>
                              </div>
                            </td>
                          </tr>

                          <!-- MODAL VISUALIZAR (FUNCIONANDO) -->
                          <div class="modal fade" id="modalVenda<?= $vid ?>" tabindex="-1" role="dialog" aria-labelledby="modalVendaLbl<?= $vid ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                              <div class="modal-content" style="border-radius:14px;">
                                <div class="modal-header">
                                  <h5 class="modal-title" id="modalVendaLbl<?= $vid ?>">
                                    Venda #<?= $vid ?>
                                  </h5>
                                  <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                                    <span aria-hidden="true">&times;</span>
                                  </button>
                                </div>

                                <div class="modal-body">
                                  <div class="row">
                                    <div class="col-md-4 mb-2">
                                      <small class="text-muted">Data/Hora</small>
                                      <div><b><?= h($dt) ?></b></div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                      <small class="text-muted">Pagamento</small>
                                      <div><b><?= h($fp) ?></b></div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                      <small class="text-muted">Status</small>
                                      <div><span class="badge <?= $badge ?>"><?= h($st) ?></span></div>
                                    </div>
                                  </div>

                                  <div class="row mt-2">
                                    <div class="col-md-8 mb-2">
                                      <small class="text-muted">Feirante(s)</small>
                                      <div class="feirantes-lines">
                                        <?php if (empty($plist) || (count($plist)===1 && trim((string)$plist[0])==='—')): ?>
                                          —
                                        <?php else: ?>
                                          <?= implode('<br>', array_map(fn($x) => h((string)$x), $plist)) ?>
                                        <?php endif; ?>
                                      </div>
                                    </div>
                                    <div class="col-md-4 mb-2 text-md-right">
                                      <small class="text-muted">Total</small>
                                      <div style="font-size:20px;font-weight:800;">R$ <?= number_format($tot, 2, ',', '.') ?></div>
                                    </div>
                                  </div>

                                  <?php $obsVenda = trim((string)($v['observacao'] ?? '')); ?>
                                  <?php if ($obsVenda !== ''): ?>
                                    <div class="mt-2">
                                      <small class="text-muted">Observação</small>
                                      <div><?= h($obsVenda) ?></div>
                                    </div>
                                  <?php endif; ?>

                                  <hr>

                                  <h6 class="font-weight-bold mb-2">Itens</h6>
                                  <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                      <thead>
                                        <tr>
                                          <th>Produto</th>
                                          <th>Feirante</th>
                                          <th style="width:110px;">Qtd</th>
                                          <th style="width:140px;">V. Unit</th>
                                          <th style="width:140px;">Subtotal</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        <?php $its = $itensPorVenda[$vid] ?? []; ?>
                                        <?php if (empty($its)): ?>
                                          <tr>
                                            <td colspan="5" class="text-center text-muted py-3">Nenhum item encontrado.</td>
                                          </tr>
                                        <?php else: ?>
                                          <?php foreach ($its as $it): ?>
                                            <?php
                                              $q  = (float)($it['quantidade'] ?? 0);
                                              $vu = (float)($it['valor_unitario'] ?? 0);
                                              $sb = (float)($it['subtotal'] ?? 0);
                                              $un = (string)($it['unidade_sigla'] ?? '');
                                              $pn = (string)($it['produto_nome'] ?? '');
                                              $fn = (string)($it['produtor_nome'] ?? '');
                                            ?>
                                            <tr>
                                              <td><?= h($pn) ?> <?= $un !== '' ? '<span class="text-muted">('.h($un).')</span>' : '' ?></td>
                                              <td><?= h($fn) ?></td>
                                              <td><?= number_format($q, 3, ',', '.') ?></td>
                                              <td>R$ <?= number_format($vu, 2, ',', '.') ?></td>
                                              <td><b>R$ <?= number_format($sb, 2, ',', '.') ?></b></td>
                                            </tr>
                                          <?php endforeach; ?>
                                        <?php endif; ?>
                                      </tbody>
                                    </table>
                                  </div>

                                </div>

                                <div class="modal-footer">
                                  <button type="button" class="btn btn-light" data-dismiss="modal">
                                    <i class="ti-close mr-1"></i> Fechar
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                          <!-- /modal -->
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

<script>
(function(){
  const body = document.getElementById('itensBody');
  const btnAdd = document.getElementById('btnAddLinha');
  const btnRef = document.getElementById('btnPrecoRef');
  const totalEl = document.getElementById('jsTotal');

  function brMoney(n){
    try { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    catch(e){ const x = Math.round(n*100)/100; return String(x).replace('.', ','); }
  }

  function toNum(s){
    s = String(s || '').trim();
    if (!s) return 0;
    s = s.replace(/R\$/g,'').replace(/\s/g,'');
    s = s.replace(/\./g,'').replace(',', '.');
    s = s.replace(/[^0-9.\-]/g,'');
    const v = parseFloat(s);
    return isNaN(v) ? 0 : v;
  }

  function syncInfo(tr){
    const sel = tr.querySelector('.js-prod');
    const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;

    const un  = (opt && opt.dataset) ? (opt.dataset.un || '') : '';
    const cat = (opt && opt.dataset) ? (opt.dataset.cat || '') : '';
    const fei = (opt && opt.dataset) ? (opt.dataset.fei || '') : '';

    const unIn  = tr.querySelector('.js-un');
    const catIn = tr.querySelector('.js-cat');
    const feiIn = tr.querySelector('.js-fei');

    if (unIn)  unIn.value = un;
    if (catIn) catIn.value = cat;
    if (feiIn) feiIn.value = fei;
  }

  function calcTotal(){
    let tot = 0;
    document.querySelectorAll('.js-item-row').forEach(tr=>{
      const pid = parseInt((tr.querySelector('.js-prod')||{}).value || '0', 10);
      if (!pid) return;
      const qtd = toNum((tr.querySelector('.js-qtd')||{}).value || '1') || 0;
      const vu  = toNum((tr.querySelector('.js-vu')||{}).value || '0') || 0;
      if (qtd > 0 && vu > 0) tot += (qtd * vu);
    });
    totalEl.textContent = 'R$ ' + brMoney(tot);
  }

  function updateRemoveButtons(){
    const rows = document.querySelectorAll('.js-item-row');
    rows.forEach((tr)=>{
      const btn = tr.querySelector('.js-remove');
      if (!btn) return;
      btn.disabled = (rows.length <= 1);
      btn.onclick = function(){
        if (rows.length <= 1) return;
        tr.remove();
        updateRemoveButtons();
        calcTotal();
      };
    });
  }

  function wireRow(tr){
    const sel = tr.querySelector('.js-prod');
    const qtd = tr.querySelector('.js-qtd');
    const vu  = tr.querySelector('.js-vu');

    if (sel) sel.addEventListener('change', ()=>{ syncInfo(tr); calcTotal(); });
    if (qtd) qtd.addEventListener('input', calcTotal);
    if (vu)  vu.addEventListener('input', calcTotal);

    syncInfo(tr);
  }

  btnAdd && btnAdd.addEventListener('click', function(){
    const base = document.querySelector('.js-item-row');
    if (!base) return;
    const clone = base.cloneNode(true);

    (clone.querySelector('.js-prod')||{}).value = '0';
    (clone.querySelector('.js-qtd')||{}).value = '1';
    (clone.querySelector('.js-vu')||{}).value = '';
    (clone.querySelector('.js-un')||{}).value = '';
    (clone.querySelector('.js-cat')||{}).value = '';
    (clone.querySelector('.js-fei')||{}).value = '';

    body.appendChild(clone);
    wireRow(clone);
    updateRemoveButtons();
    calcTotal();
  });

  btnRef && btnRef.addEventListener('click', function(){
    document.querySelectorAll('.js-item-row').forEach(tr=>{
      const sel = tr.querySelector('.js-prod');
      const vu  = tr.querySelector('.js-vu');
      if (!sel || !vu) return;

      const pid = parseInt(sel.value || '0', 10);
      if (!pid) return;

      const opt = sel.options[sel.selectedIndex];
      const ref = (opt && opt.dataset) ? (opt.dataset.preco || '') : '';
      if (!vu.value && ref) {
        const n = toNum(ref);
        if (n > 0) vu.value = brMoney(n);
      }
    });
    calcTotal();
  });

  document.querySelectorAll('.js-item-row').forEach(wireRow);
  updateRemoveButtons();
  calcTotal();
})();
</script>
</body>
</html>
