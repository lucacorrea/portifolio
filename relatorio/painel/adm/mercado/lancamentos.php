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

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Converte "1.234,56" -> 1234.56 */
function to_decimal($v): float
{
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '0';
  if ($s === '' || $s === '-' || $s === '.') return 0.0;
  return (float)$s;
}

/* ✅ agora sem hora: mostra só data (d/m/Y) */
function fmt_dt(string $s): string
{
  if ($s === '') return '';
  try {
    $dt = new DateTime($s);
    return $dt->format('d/m/Y');
  } catch (Throwable $e) {
    return $s;
  }
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
$feiraId = 3;

/* ===== Filtros ===== */
$dia        = trim((string)($_GET['dia'] ?? date('Y-m-d'))); // filtra por DATE(v.data_hora)
$prodFiltro = (int)($_GET['produtor'] ?? 0);
$verId      = (int)($_GET['ver'] ?? 0);

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
      p.id, p.nome,
      p.produtor_id,
      COALESCE(pr.nome,'') AS produtor_nome,
      COALESCE(c.nome,'')  AS categoria_nome,
      COALESCE(u.sigla,'') AS unidade_sigla,
      p.preco_referencia
    FROM produtos p
    LEFT JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades   u ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
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

  if ($acao === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      $_SESSION['flash_err'] = 'Lançamento inválido.';
      header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
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

    header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
    exit;
  }

  if ($acao === 'salvar') {
    // vendas: feira_id, data_hora, forma_pagamento, total, status, observacao...
    // venda_itens: feira_id, venda_id, produto_id, quantidade, valor_unitario, subtotal...

    $dataVenda = trim((string)($_POST['data_venda'] ?? ''));
    $pagamento = trim((string)($_POST['forma_pagamento'] ?? ''));
    $obs       = trim((string)($_POST['observacao'] ?? ''));

    // ✅ sem hora no form: grava fixo meio-dia
    $dataHora = $dataVenda . ' 12:00:00';

    $produtoIds = $_POST['produto_id'] ?? [];
    $qtds       = $_POST['quantidade'] ?? [];
    $vunit      = $_POST['valor_unitario'] ?? [];

    $localErr = '';

    if ($dataVenda === '') $localErr = 'Informe a data do lançamento.';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVenda)) $localErr = 'Data inválida.';
    elseif ($pagamento === '') $localErr = 'Selecione a forma de pagamento.';

    $itens = [];
    $total = 0.0;

    if ($localErr === '') {
      $n = max(count((array)$produtoIds), count((array)$qtds), count((array)$vunit));
      for ($i = 0; $i < $n; $i++) {
        $pid = (int)($produtoIds[$i] ?? 0);
        if ($pid <= 0) continue;

        $q = to_decimal($qtds[$i] ?? '1');
        if ($q <= 0) $q = 1.0;
        $q = round($q, 3);

        $vu = to_decimal($vunit[$i] ?? '0');
        if ($vu <= 0) continue;
        $vu = round($vu, 2);

        $sub = round($q * $vu, 2);
        if ($sub <= 0) continue;

        $total += $sub;

        $itens[] = [
          'produto_id'     => $pid,
          'quantidade'     => $q,
          'valor_unitario' => $vu,
          'subtotal'       => $sub
        ];
      }

      $total = round($total, 2);

      if (empty($itens)) $localErr = 'Adicione pelo menos 1 item (produto + valor).';
      elseif ($total <= 0) $localErr = 'Total inválido.';
    }

    if ($localErr !== '') {
      $_SESSION['flash_err'] = $localErr;
      header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
      exit;
    }

    try {
      $pdo->beginTransaction();

      $ins = $pdo->prepare("
        INSERT INTO vendas (feira_id, data_hora, forma_pagamento, total, status, observacao, criado_em)
        VALUES (:f, :dh, :fp, :total, 'FECHADA', :obs, NOW())
      ");
      $ins->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $ins->bindValue(':dh', $dataHora, PDO::PARAM_STR);
      $ins->bindValue(':fp', $pagamento, PDO::PARAM_STR);
      $ins->bindValue(':total', $total);
      if ($obs === '') $ins->bindValue(':obs', null, PDO::PARAM_NULL);
      else $ins->bindValue(':obs', $obs, PDO::PARAM_STR);
      $ins->execute();

      $vendaId = (int)$pdo->lastInsertId();

      $insItem = $pdo->prepare("
        INSERT INTO venda_itens
          (feira_id, venda_id, produto_id, descricao_livre, quantidade, valor_unitario, subtotal, observacao, criado_em)
        VALUES
          (:f, :v, :p, NULL, :q, :vu, :sub, NULL, NOW())
      ");
      foreach ($itens as $it) {
        $insItem->bindValue(':f', $feiraId, PDO::PARAM_INT);
        $insItem->bindValue(':v', $vendaId, PDO::PARAM_INT);
        $insItem->bindValue(':p', $it['produto_id'], PDO::PARAM_INT);
        $insItem->bindValue(':q', $it['quantidade']);
        $insItem->bindValue(':vu', $it['valor_unitario']);
        $insItem->bindValue(':sub', $it['subtotal']);
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

    header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
    exit;
  }
}

/* ===== Listagem ===== */
$vendas = [];
try {
  $sql = "
    SELECT
      v.id,
      v.data_hora,
      v.forma_pagamento,
      v.total,
      v.status,
      v.observacao,
      COALESCE((
        SELECT GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR '||')
        FROM venda_itens vi
        JOIN produtos p   ON p.id = vi.produto_id AND p.feira_id = vi.feira_id
        JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
        WHERE vi.feira_id = v.feira_id AND vi.venda_id = v.id
      ), '') AS produtores_list
    FROM vendas v
    WHERE v.feira_id = :f
  ";
  $params = [':f' => $feiraId];

  if ($dia !== '') {
    $sql .= " AND DATE(v.data_hora) = :dia";
    $params[':dia'] = $dia;
  }

  if ($prodFiltro > 0) {
    $sql .= "
      AND EXISTS (
        SELECT 1
        FROM venda_itens vi2
        JOIN produtos p2 ON p2.id = vi2.produto_id AND p2.feira_id = vi2.feira_id
        WHERE vi2.feira_id = v.feira_id
          AND vi2.venda_id = v.id
          AND p2.produtor_id = :prod
      )
    ";
    $params[':prod'] = $prodFiltro;
  }

  $sql .= " ORDER BY v.id DESC";

  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    if ($k === ':f' || $k === ':prod') $st->bindValue($k, (int)$v, PDO::PARAM_INT);
    else $st->bindValue($k, (string)$v, PDO::PARAM_STR);
  }
  $st->execute();
  $vendas = $st->fetchAll();
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os lançamentos agora.';
}

/* ===== Visualizar (DETALHES) ===== */
$detalheVenda = null;
$detalheItens = [];
$detalheProdutores = [];

if ($verId > 0) {
  try {
    $stV = $pdo->prepare("
      SELECT id, data_hora, forma_pagamento, total, status, observacao
      FROM vendas
      WHERE feira_id = :f AND id = :id
      LIMIT 1
    ");
    $stV->bindValue(':f', $feiraId, PDO::PARAM_INT);
    $stV->bindValue(':id', $verId, PDO::PARAM_INT);
    $stV->execute();
    $detalheVenda = $stV->fetch();

    if (!$detalheVenda) {
      $_SESSION['flash_err'] = 'Lançamento não encontrado.';
      header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
      exit;
    }

    $stI = $pdo->prepare("
      SELECT
        vi.id,
        vi.quantidade,
        vi.valor_unitario,
        vi.subtotal,
        p.nome AS produto_nome,
        COALESCE(u.sigla,'') AS unidade_sigla,
        COALESCE(c.nome,'')  AS categoria_nome,
        COALESCE(pr.nome,'') AS produtor_nome
      FROM venda_itens vi
      LEFT JOIN produtos p    ON p.id = vi.produto_id AND p.feira_id = vi.feira_id
      LEFT JOIN unidades u    ON u.id = p.unidade_id  AND u.feira_id = p.feira_id
      LEFT JOIN categorias c  ON c.id = p.categoria_id AND c.feira_id = p.feira_id
      LEFT JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
      WHERE vi.feira_id = :f AND vi.venda_id = :v
      ORDER BY vi.id ASC
    ");
    $stI->bindValue(':f', $feiraId, PDO::PARAM_INT);
    $stI->bindValue(':v', $verId, PDO::PARAM_INT);
    $stI->execute();
    $detalheItens = $stI->fetchAll();

    // produtores distintos (lista)
    $map = [];
    foreach ($detalheItens as $it) {
      $pn = trim((string)($it['produtor_nome'] ?? ''));
      if ($pn !== '') $map[$pn] = true;
    }
    $detalheProdutores = array_keys($map);
    sort($detalheProdutores, SORT_NATURAL | SORT_FLAG_CASE);
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = 'Não foi possível carregar os detalhes agora.';
    header('Location: ./lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira Alternativa — Lançamentos (Vendas)</title>

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

    .btn {
      height: 42px;
    }

    .helper {
      font-size: 12px;
    }

    .acoes-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .btn-xs {
      padding: .25rem .5rem;
      font-size: .75rem;
      line-height: 1.2;
      height: auto;
    }

    .table td,
    .table th {
      vertical-align: middle !important;
    }

    .totbox {
      border: 1px solid rgba(0, 0, 0, .08);
      background: #fff;
      border-radius: 12px;
      padding: 10px 12px;
    }

    .totlabel {
      font-size: 12px;
      color: #6c757d;
      margin: 0;
    }

    .totvalue {
      font-size: 20px;
      font-weight: 800;
      margin: 0;
    }

    /* produtores em lista (compacto) */
    .plist {
      margin: 0;
      padding-left: 18px;
    }

    .plist li {
      line-height: 1.1;
      margin: 2px 0;
    }

    /* ===== Flash “Hostinger style” (top-right, ~6s) ===== */
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

    /* modal */
    .modal-content {
      border-radius: 14px;
    }

    .modal-header {
      border-top-left-radius: 14px;
      border-top-right-radius: 14px;
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

      <!-- SIDEBAR -->
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
                .sub-menu .nav-item .nav-link {
                  color: black !important;
                }

                .sub-menu .nav-item .nav-link:hover {
                  color: blue !important;
                }
              </style>

              <ul class="nav flex-column sub-menu" style="background: white !important;">
                <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>
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
                <li class="nav-item"><a class="nav-link" href="./relatorioFinanceiro.php"><i class="ti-bar-chart mr-2"></i> Relatório Financeiro</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
                <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
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

          <!-- Linha abaixo do título -->
          <li class="nav-item">
            <a class="nav-link" href="../index.php">
              <i class="ti-home menu-icon"></i>
              <span class="menu-title"> Painel Principal</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="../produtor/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Produtor</span>

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

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Lançamentos (Vendas)</h3>
              <h6 class="font-weight-normal mb-0">Sem hora no formulário e “Feirante(s)” sempre em linhas separadas quando tiver mais de um.</h6>
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
                      <p class="card-description mb-0">Escolha a data, forma de pagamento, adicione itens e salve.</p>
                    </div>
                    <div class="totbox mt-2 mt-md-0">
                      <p class="totlabel">Total</p>
                      <p class="totvalue" id="jsTotal">R$ 0,00</p>
                    </div>
                  </div>

                  <hr>

                  <form method="post" action="./lancamentos.php?dia=<?= h($dia) ?>&produtor=<?= (int)$prodFiltro ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="acao" value="salvar">

                    <div class="row">
                      <div class="col-md-3 mb-3">
                        <label class="mb-1">Data</label>
                        <input type="date" class="form-control" name="data_venda" value="<?= h($dia) ?>" required>
                      </div>

                      <!-- ✅ HORA REMOVIDA -->

                      <div class="col-md-3 mb-3">
                        <label class="mb-1">Forma de Pagamento</label>
                        <select class="form-control" name="forma_pagamento" required>
                          <option value="" selected disabled>Selecione</option>
                          <option value="DINHEIRO">Dinheiro</option>
                          <option value="PIX">Pix</option>
                          <option value="CARTAO">Cartão</option>
                          <option value="OUTROS">Outros</option>
                        </select>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Observação</label>
                        <input type="text" class="form-control" name="observacao" placeholder="Opcional">
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Filtrar produtos por feirante (opcional)</label>
                        <select class="form-control" id="jsFiltroProd">
                          <option value="0">Todos</option>
                          <?php foreach ($produtoresAtivos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= h($p['nome'] ?? '') ?></option>
                          <?php endforeach; ?>
                        </select>
                        <small class="text-muted helper">Ajuda a achar o produto mais rápido. A venda pode ter itens de vários feirantes.</small>
                      </div>
                    </div>

                    <div class="table-responsive">
                      <table class="table table-striped table-hover mb-0" id="itensTable">
                        <thead>
                          <tr>
                            <th>Produto</th>
                            <th style="width:120px;">Qtd</th>
                            <th style="width:160px;">Valor (R$)</th>
                            <th style="width:120px;">Unid</th>
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
                                    data-prod="<?= (int)($pr['produtor_id'] ?? 0) ?>"
                                    data-un="<?= h($pr['unidade_sigla'] ?? '') ?>"
                                    data-cat="<?= h($pr['categoria_nome'] ?? '') ?>"
                                    data-preco="<?= h((string)($pr['preco_referencia'] ?? '')) ?>">
                                    <?= h($pr['nome'] ?? '') ?><?= ($pr['produtor_nome'] ?? '') ? ' — ' . h($pr['produtor_nome']) : '' ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                            <td><input type="text" class="form-control js-qtd" name="quantidade[]" value="1"></td>
                            <td><input type="text" class="form-control js-vu" name="valor_unitario[]" placeholder="0,00"></td>
                            <td><input type="text" class="form-control js-un" value="" readonly></td>
                            <td><input type="text" class="form-control js-cat" value="" readonly></td>
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
                      <label class="mb-1">Feirante (filtra por itens)</label>
                      <select class="form-control" onchange="location.href='?dia=<?= h($dia) ?>&produtor='+this.value;">
                        <option value="0">Todos</option>
                        <?php foreach ($produtoresAtivos as $p): $pid = (int)$p['id']; ?>
                          <option value="<?= $pid ?>" <?= $prodFiltro === $pid ? 'selected' : '' ?>><?= h($p['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted helper">Mostra vendas que tenham itens de produtos desse feirante.</small>
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
                          <th style="width:170px;">Data</th>
                          <th style="width:150px;">Pagamento</th>
                          <th>Feirante(s)</th>
                          <th style="width:160px;">Total</th>
                          <th style="min-width:260px;">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($vendas)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum lançamento encontrado.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($vendas as $v): ?>
                            <?php
                            $vid = (int)($v['id'] ?? 0);
                            $tot = (float)($v['total'] ?? 0);

                            // ✅ feirantes em LINHAS separadas (sempre que houver mais de um)
                            $plistRaw = (string)($v['produtores_list'] ?? '');
                            $plist = array_values(array_filter(array_map('trim', $plistRaw !== '' ? explode('||', $plistRaw) : [])));
                            if (empty($plist)) $plist = ['—'];

                            $urlView = './lancamentos.php?dia=' . urlencode($dia) . '&produtor=' . (int)$prodFiltro . '&ver=' . $vid;
                            ?>
                            <tr>
                              <td><?= $vid ?></td>
                              <td><?= h(fmt_dt((string)($v['data_hora'] ?? ''))) ?></td>
                              <td><?= h((string)($v['forma_pagamento'] ?? '')) ?></td>
                              <td>
                                <?php if (count($plist) <= 1): ?>
                                  <?= h($plist[0] ?? '—') ?>
                                <?php else: ?>
                                  <ul class="plist">
                                    <?php foreach ($plist as $pn): ?>
                                      <li><?= h($pn) ?></li>
                                    <?php endforeach; ?>
                                  </ul>
                                <?php endif; ?>
                              </td>
                              <td><b>R$ <?= number_format($tot, 2, ',', '.') ?></b></td>
                              <td>
                                <div class="acoes-wrap">
                                  <a class="btn btn-outline-primary btn-xs" href="<?= h($urlView) ?>">
                                    <i class="ti-eye"></i> Visualizar
                                  </a>

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

  <!-- MODAL: VISUALIZAR -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">
            Detalhes do Lançamento <?= $detalheVenda ? '#' . (int)$detalheVenda['id'] : '' ?>
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <?php if (!$detalheVenda): ?>
            <div class="text-muted">Nenhum detalhe para mostrar.</div>
          <?php else: ?>
            <?php
            $vTot = (float)($detalheVenda['total'] ?? 0);
            $vObs = trim((string)($detalheVenda['observacao'] ?? ''));
            ?>
            <div class="row">
              <div class="col-md-3 mb-2">
                <div class="text-muted" style="font-size:12px;">Data</div>
                <div style="font-weight:800;"><?= h(fmt_dt((string)$detalheVenda['data_hora'])) ?></div>
              </div>
              <div class="col-md-3 mb-2">
                <div class="text-muted" style="font-size:12px;">Pagamento</div>
                <div style="font-weight:800;"><?= h((string)$detalheVenda['forma_pagamento']) ?></div>
              </div>
              <div class="col-md-3 mb-2">
                <div class="text-muted" style="font-size:12px;">Status</div>
                <div style="font-weight:800;"><?= h((string)$detalheVenda['status']) ?></div>
              </div>
              <div class="col-md-3 mb-2">
                <div class="text-muted" style="font-size:12px;">Total</div>
                <div style="font-weight:900; font-size:18px;">R$ <?= number_format($vTot, 2, ',', '.') ?></div>
              </div>
            </div>

            <hr class="my-2">

            <div class="row">
              <div class="col-md-6 mb-2">
                <div class="text-muted" style="font-size:12px;">Feirante(s)</div>

                <?php if (empty($detalheProdutores)): ?>
                  <div>—</div>
                <?php elseif (count($detalheProdutores) === 1): ?>
                  <div><?= h($detalheProdutores[0]) ?></div>
                <?php else: ?>
                  <ul class="plist">
                    <?php foreach ($detalheProdutores as $pn): ?>
                      <li><?= h($pn) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="col-md-6 mb-2">
                <div class="text-muted" style="font-size:12px;">Observação</div>
                <div><?= $vObs !== '' ? h($vObs) : '—' ?></div>
              </div>
            </div>

            <hr class="my-2">

            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th style="width:220px;">Feirante</th>
                    <th style="width:110px;">Unid</th>
                    <th style="width:220px;">Categoria</th>
                    <th style="width:120px;">Qtd</th>
                    <th style="width:140px;">Valor</th>
                    <th style="width:140px;">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($detalheItens)): ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-4">Sem itens.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($detalheItens as $it): ?>
                      <?php
                      $q  = (float)($it['quantidade'] ?? 0);
                      $vu = (float)($it['valor_unitario'] ?? 0);
                      $sb = (float)($it['subtotal'] ?? 0);
                      ?>
                      <tr>
                        <td><?= h((string)($it['produto_nome'] ?? '')) ?></td>
                        <td><?= h((string)($it['produtor_nome'] ?? '')) ?></td>
                        <td><?= h((string)($it['unidade_sigla'] ?? '')) ?></td>
                        <td><?= h((string)($it['categoria_nome'] ?? '')) ?></td>
                        <td><?= number_format($q, 3, ',', '.') ?></td>
                        <td>R$ <?= number_format($vu, 2, ',', '.') ?></td>
                        <td><b>R$ <?= number_format($sb, 2, ',', '.') ?></b></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer">
          <a class="btn btn-light" href="./lancamentos.php?dia=<?= h($dia) ?>&produtor=<?= (int)$prodFiltro ?>">
            <i class="ti-arrow-left mr-1"></i> Voltar
          </a>
          <button type="button" class="btn btn-primary" data-dismiss="modal">
            <i class="ti-check mr-1"></i> Fechar
          </button>
        </div>

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
    (function() {
      const body = document.getElementById('itensBody');
      const btnAdd = document.getElementById('btnAddLinha');
      const btnRef = document.getElementById('btnPrecoRef');
      const totalEl = document.getElementById('jsTotal');
      const filtroProd = document.getElementById('jsFiltroProd');

      function brMoney(n) {
        try {
          return n.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        } catch (e) {
          const x = Math.round(n * 100) / 100;
          return String(x).replace('.', ',');
        }
      }

      function toNum(s) {
        s = String(s || '').trim();
        if (!s) return 0;
        s = s.replace(/R\$/g, '').replace(/\s/g, '');
        s = s.replace(/\./g, '').replace(',', '.');
        s = s.replace(/[^0-9.\-]/g, '');
        const v = parseFloat(s);
        return isNaN(v) ? 0 : v;
      }

      function syncInfo(tr) {
        const sel = tr.querySelector('.js-prod');
        const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
        const un = (opt && opt.dataset) ? (opt.dataset.un || '') : '';
        const cat = (opt && opt.dataset) ? (opt.dataset.cat || '') : '';
        const unIn = tr.querySelector('.js-un');
        const catIn = tr.querySelector('.js-cat');
        if (unIn) unIn.value = un;
        if (catIn) catIn.value = cat;
      }

      function calcTotal() {
        let tot = 0;
        document.querySelectorAll('.js-item-row').forEach(tr => {
          const pid = parseInt((tr.querySelector('.js-prod') || {}).value || '0', 10);
          if (!pid) return;
          const qtd = toNum((tr.querySelector('.js-qtd') || {}).value || '1') || 0;
          const vu = toNum((tr.querySelector('.js-vu') || {}).value || '0') || 0;
          if (qtd > 0 && vu > 0) tot += (qtd * vu);
        });
        totalEl.textContent = 'R$ ' + brMoney(tot);
      }

      function updateRemoveButtons() {
        const rows = document.querySelectorAll('.js-item-row');
        rows.forEach((tr) => {
          const btn = tr.querySelector('.js-remove');
          if (!btn) return;
          btn.disabled = (rows.length <= 1);
          btn.onclick = function() {
            if (rows.length <= 1) return;
            tr.remove();
            updateRemoveButtons();
            calcTotal();
          };
        });
      }

      function wireRow(tr) {
        const sel = tr.querySelector('.js-prod');
        const qtd = tr.querySelector('.js-qtd');
        const vu = tr.querySelector('.js-vu');

        if (sel) sel.addEventListener('change', () => {
          syncInfo(tr);
          calcTotal();
        });
        if (qtd) qtd.addEventListener('input', calcTotal);
        if (vu) vu.addEventListener('input', calcTotal);

        syncInfo(tr);
      }

      function applyFiltroProdutos() {
        const prodId = parseInt((filtroProd && filtroProd.value) ? filtroProd.value : '0', 10) || 0;
        document.querySelectorAll('.js-item-row .js-prod').forEach(sel => {
          const current = sel.value;
          let hasCurrentVisible = false;

          Array.from(sel.options).forEach(opt => {
            if (!opt.value || opt.value === '0') {
              opt.hidden = false;
              return;
            }
            const p = parseInt(opt.dataset.prod || '0', 10) || 0;
            const show = (prodId === 0) || (p === prodId);
            opt.hidden = !show;
            if (opt.value === current && show) hasCurrentVisible = true;
          });

          if (!hasCurrentVisible && current !== '0') {
            sel.value = '0';
            syncInfo(sel.closest('tr'));
          }
        });

        calcTotal();
      }

      btnAdd && btnAdd.addEventListener('click', function() {
        const base = document.querySelector('.js-item-row');
        if (!base) return;
        const clone = base.cloneNode(true);

        (clone.querySelector('.js-prod') || {}).value = '0';
        (clone.querySelector('.js-qtd') || {}).value = '1';
        (clone.querySelector('.js-vu') || {}).value = '';
        (clone.querySelector('.js-un') || {}).value = '';
        (clone.querySelector('.js-cat') || {}).value = '';

        body.appendChild(clone);
        wireRow(clone);
        updateRemoveButtons();
        applyFiltroProdutos();
        calcTotal();
      });

      btnRef && btnRef.addEventListener('click', function() {
        document.querySelectorAll('.js-item-row').forEach(tr => {
          const sel = tr.querySelector('.js-prod');
          const vu = tr.querySelector('.js-vu');
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

      filtroProd && filtroProd.addEventListener('change', applyFiltroProdutos);

      document.querySelectorAll('.js-item-row').forEach(wireRow);
      updateRemoveButtons();
      applyFiltroProdutos();
      calcTotal();

      // auto abrir modal se veio com ?ver=
      <?php if ($verId > 0 && $detalheVenda): ?>
        if (window.jQuery && jQuery.fn.modal) {
          jQuery(function() {
            jQuery('#modalDetalhes').modal('show');
          });
        }
      <?php endif; ?>
    })();
  </script>
</body>

</html>