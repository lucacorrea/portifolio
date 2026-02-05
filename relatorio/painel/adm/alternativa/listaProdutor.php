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

require '../../../assets/php/conexao.php';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits(string $s): string
{
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

/* Feira padrão desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta (se você separou em pastas) */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor')   !== false) $FEIRA_ID = 1;

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* ===== Conexão ===== */
$produtores = [];
$comunidades = [];
$totalRows  = 0;
$totalPages = 1;
$errDetail  = '';
$dbName     = '';

/* Paginação */
$perPage = 8;
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

/* Busca (FAZ FUNCIONAR: normaliza e busca por CPF/telefone também) */
$qRaw = trim((string)($_GET['q'] ?? ''));
$qDigits = only_digits($qRaw);

$cleanErr = function (string $m): string {
  $m = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $m) ?? $m;
  $m = preg_replace('/\(SQL:\s*.*\)$/', '', $m) ?? $m;
  return trim((string)$m);
};

function buildUrl(array $add = []): string
{
  $base = strtok($_SERVER['REQUEST_URI'], '?') ?: './listaProdutor.php';
  $cur = $_GET ?? [];
  foreach ($add as $k => $v) {
    if ($v === null) unset($cur[$k]);
    else $cur[$k] = (string)$v;
  }
  $qs = http_build_query($cur);
  return $qs ? ($base . '?' . $qs) : $base;
}

/* Helpers modal: validação simples */
function trunc(string $s, int $max): string
{
  $s = trim($s);
  if ($s === '') return '';
  if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
  return substr($s, 0, $max);
}

try {
  $pdo = db();
  $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

  /* Checa tabelas */
  $tblP = $pdo->query("SHOW TABLES LIKE 'produtores'")->fetchColumn();
  if (!$tblP) throw new RuntimeException("Tabela 'produtores' não existe neste banco.");

  $tblC = $pdo->query("SHOW TABLES LIKE 'comunidades'")->fetchColumn();
  $hasComunidades = (bool)$tblC;

  if ($hasComunidades) {
    $stCom = $pdo->prepare("SELECT id, nome FROM comunidades WHERE feira_id = :f AND ativo = 1 ORDER BY nome ASC");
    $stCom->bindValue(':f', $FEIRA_ID, PDO::PARAM_INT);
    $stCom->execute();
    $comunidades = $stCom->fetchAll(PDO::FETCH_ASSOC);
  }

  /* ===== AÇÕES POST: toggle / update (modal) ===== */
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenPost = (string)($_POST['csrf_token'] ?? '');
    if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
      $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
      header('Location: ' . buildUrl());
      exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id > 0) {
      $st = $pdo->prepare("SELECT ativo FROM produtores WHERE id = :id AND feira_id = :feira");
      $st->bindValue(':id', $id, PDO::PARAM_INT);
      $st->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
      $st->execute();
      $curAtv = $st->fetchColumn();

      if ($curAtv === false) {
        $_SESSION['flash_err'] = 'Produtor não encontrado.';
      } else {
        $newAtv = ((int)$curAtv === 1) ? 0 : 1;
        $up = $pdo->prepare("UPDATE produtores SET ativo = :a WHERE id = :id AND feira_id = :feira");
        $up->bindValue(':a', $newAtv, PDO::PARAM_INT);
        $up->bindValue(':id', $id, PDO::PARAM_INT);
        $up->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
        $up->execute();
        $_SESSION['flash_ok'] = $newAtv ? 'Produtor ativado com sucesso!' : 'Produtor desativado com sucesso!';
      }

      header('Location: ' . buildUrl(['p' => $page]));
      exit;
    }

    if ($action === 'update' && $id > 0) {
      $nome = trunc((string)($_POST['nome'] ?? ''), 160);
      $contato = trunc((string)($_POST['contato'] ?? ''), 60);
      $documento = trunc(only_digits((string)($_POST['documento'] ?? '')), 30);
      $obs = trunc((string)($_POST['observacao'] ?? ''), 255);
      $ativo = ((string)($_POST['ativo'] ?? '1') === '1') ? 1 : 0;

      $comunidade_id = (int)($_POST['comunidade_id'] ?? 0);

      if ($nome === '') {
        $_SESSION['flash_err'] = 'Informe o nome do produtor.';
        header('Location: ' . buildUrl(['p' => $page]));
        exit;
      }

      if ($hasComunidades) {
        if ($comunidade_id <= 0) {
          $_SESSION['flash_err'] = 'Selecione a comunidade.';
          header('Location: ' . buildUrl(['p' => $page]));
          exit;
        }

        $chk = $pdo->prepare("SELECT COUNT(*) FROM comunidades WHERE id = :id AND feira_id = :f AND ativo = 1");
        $chk->bindValue(':id', $comunidade_id, PDO::PARAM_INT);
        $chk->bindValue(':f', $FEIRA_ID, PDO::PARAM_INT);
        $chk->execute();
        if ((int)$chk->fetchColumn() <= 0) {
          $_SESSION['flash_err'] = 'Comunidade inválida (não encontrada ou inativa).';
          header('Location: ' . buildUrl(['p' => $page]));
          exit;
        }
      } else {
        // sem tabela comunidades, mantém comunidade_id como está (não atualiza)
        $comunidade_id = 0;
      }

      // evita duplicidade por feira+nome (se você mantém UNIQUE uq_produtores_feira_nome)
      $dupe = $pdo->prepare("SELECT COUNT(*) FROM produtores WHERE feira_id=:f AND nome=:n AND id<>:id");
      $dupe->bindValue(':f', $FEIRA_ID, PDO::PARAM_INT);
      $dupe->bindValue(':n', $nome, PDO::PARAM_STR);
      $dupe->bindValue(':id', $id, PDO::PARAM_INT);
      $dupe->execute();
      if ((int)$dupe->fetchColumn() > 0) {
        $_SESSION['flash_err'] = 'Já existe um produtor com esse nome nesta feira.';
        header('Location: ' . buildUrl(['p' => $page]));
        exit;
      }

      if ($hasComunidades) {
        $up = $pdo->prepare("UPDATE produtores
                             SET nome=:nome, contato=:contato, documento=:doc, comunidade_id=:cid, ativo=:ativo, observacao=:obs
                             WHERE id=:id AND feira_id=:f");
        $up->execute([
          ':nome' => $nome,
          ':contato' => ($contato !== '' ? $contato : null),
          ':doc' => ($documento !== '' ? $documento : null),
          ':cid' => $comunidade_id,
          ':ativo' => $ativo,
          ':obs' => ($obs !== '' ? $obs : null),
          ':id' => $id,
          ':f' => $FEIRA_ID
        ]);
      } else {
        $up = $pdo->prepare("UPDATE produtores
                             SET nome=:nome, contato=:contato, documento=:doc, ativo=:ativo, observacao=:obs
                             WHERE id=:id AND feira_id=:f");
        $up->execute([
          ':nome' => $nome,
          ':contato' => ($contato !== '' ? $contato : null),
          ':doc' => ($documento !== '' ? $documento : null),
          ':ativo' => $ativo,
          ':obs' => ($obs !== '' ? $obs : null),
          ':id' => $id,
          ':f' => $FEIRA_ID
        ]);
      }

      $_SESSION['flash_ok'] = 'Produtor atualizado com sucesso!';
      header('Location: ' . buildUrl(['p' => $page]));
      exit;
    }
  }

  /* ===== WHERE da listagem (PESQUISA FUNCIONANDO) ===== */

  $where = ["p.feira_id = :feira"];
  $params = [':feira' => $FEIRA_ID];

  if ($qRaw !== '') {
    $parts = [];

    // mesmo valor, placeholders diferentes (PDO MySQL com emulação OFF exige isso)
    $params[':q_nome'] = '%' . $qRaw . '%';
    $parts[] = "p.nome LIKE :q_nome";

    $params[':q_contato'] = '%' . $qRaw . '%';
    $parts[] = "p.contato LIKE :q_contato";

    $params[':q_doc'] = '%' . $qRaw . '%';
    $parts[] = "p.documento LIKE :q_doc";

    if (!empty($hasComunidades)) {
      $params[':q_com'] = '%' . $qRaw . '%';
      $parts[] = "c.nome LIKE :q_com";
    }

    if ($qDigits !== '') {
      $params[':qd_contato'] = '%' . $qDigits . '%';
      $parts[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.contato,' ',''),'-',''),'(',''),')',''),'+','') LIKE :qd_contato";

      $params[':qd_doc'] = '%' . $qDigits . '%';
      $parts[] = "REPLACE(REPLACE(REPLACE(p.documento,'.',''),'-',''),' ','') LIKE :qd_doc";
    }

    $where[] = '(' . implode(' OR ', $parts) . ')';
  }

  $whereSql = ' WHERE ' . implode(' AND ', $where);

  /* ===== COUNT ===== */
  if ($hasComunidades) {
    $sqlCount = "SELECT COUNT(*)
                 FROM produtores p
                 LEFT JOIN comunidades c
                   ON c.id = p.comunidade_id AND c.feira_id = p.feira_id
                 $whereSql";
  } else {
    $sqlCount = "SELECT COUNT(*) FROM produtores p $whereSql";
  }

  $stCount = $pdo->prepare($sqlCount);
  $stCount->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;
    $stCount->bindValue($k, (string)$v, PDO::PARAM_STR);
  }

  $stCount->execute();

  $totalRows = (int)$stCount->fetchColumn();

  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
  }

  /* ===== SELECT ===== */
  if ($hasComunidades) {
    $sql = "SELECT
              p.id,
              p.nome,
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              c.nome AS comunidade
            FROM produtores p
            LEFT JOIN comunidades c
              ON c.id = p.comunidade_id AND c.feira_id = p.feira_id
            $whereSql
            ORDER BY p.nome ASC
            LIMIT :lim OFFSET :off";
  } else {
    $sql = "SELECT
              p.id,
              p.nome,
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              NULL AS comunidade
            FROM produtores p
            $whereSql
            ORDER BY p.nome ASC
            LIMIT :lim OFFSET :off";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;
    $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
  }

  $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
  $stmt->execute();

  $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os produtores agora.';
  $errDetail = $cleanErr($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Produtores</title>

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

    .toolbar-card .form-control {
      height: 42px;
    }

    .toolbar-card .btn {
      height: 42px;
    }

    /* ===== Flash “Hostinger style” ===== */
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

    .acoes-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .btn-xs {
      padding: .25rem .5rem;
      font-size: .75rem;
      line-height: 1.2;
      height: auto;
    }

    .muted-small {
      font-size: 12px;
      color: #6b7280;
    }

    .sig-pager {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: space-between;
      margin-top: 14px;
    }

    .sig-pager .info {
      color: #6c757d;
      font-size: 13px;
    }

    .sig-pager .pagination {
      margin: 0;
    }

    .sig-pager .page-link {
      border-radius: 10px !important;
    }

    /* Modal ajustes leves */
    .modal .form-control {
      height: 42px;
    }

    .modal .btn {
      height: 42px;
    }

    .modal .modal-header {
      border-bottom: 1px solid rgba(0, 0, 0, .06);
    }

    .modal .modal-footer {
      border-top: 1px solid rgba(0, 0, 0, .06);
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>

        <?php if ($err): ?>
          <div class="alert sig-toast sig-toast--danger alert-dismissible" role="alert">
            <div class="sig-toast__row">
              <div class="sig-toast__icon"><i class="ti-alert"></i></div>
              <div>
                <div class="sig-toast__title">Atenção!</div>
                <p class="sig-toast__text">
                  <?= h($err) ?>
                  <?php if (!empty($debug) && !empty($errDetail)): ?>
                    <br><small style="opacity:.75; display:block; margin-top:4px;"><?= h($errDetail) ?></small>
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="container-fluid page-body-wrapper">

      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close ti-close"></i>
        <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
          <li class="nav-item"><a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab">TO DO LIST</a></li>
          <li class="nav-item"><a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab">CHATS</a></li>
        </ul>
      </div>

      <!-- SIDEBAR -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i><span class="menu-title">Dashboard</span>
            </a>
          </li>

          <li class="nav-item active">
            <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="true" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i><span class="menu-title">Cadastros</span><i class="menu-arrow"></i>
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
                <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>

                <li class="nav-item active">
                  <a class="nav-link" href="./listaProdutor.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>

                
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i><span class="menu-title">Movimento</span><i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item"><a class="nav-link" href="./lancamentos.php"><i class="ti-write mr-2"></i> Lançamentos (Vendas)</a></li>
                <li class="nav-item"><a class="nav-link" href="./fechamentoDia.php"><i class="ti-check-box mr-2"></i> Fechamento do Dia</a></li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i><span class="menu-title">Relatórios</span><i class="menu-arrow"></i>
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
            <a href="../alternativa/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira Alternativa</span>

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

        </ul>
      </nav>

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Produtores</h3>
              <h6 class="font-weight-normal mb-0">Pesquisa funciona por nome, comunidade, contato e documento.</h6>
            </div>
          </div>

          <?php if ($debug): ?>
            <div class="alert alert-info">
              <b>Debug:</b> feira_id = <?= (int)$FEIRA_ID ?> • banco = <code><?= h($dbName) ?></code>
            </div>
          <?php endif; ?>

          <!-- Toolbar -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card toolbar-card">
                <div class="card-body">
                  <form method="get" class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                      <label class="mb-1">Pesquisa</label>
                      <input type="text" name="q" class="form-control"
                        placeholder="Ex.: João / São Francisco / 9299... / CPF..." value="<?= h($qRaw) ?>">
                      <?php if ($debug): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
                    </div>

                    <div class="col-md-6">
                      <label class="mb-1 d-none d-md-block">&nbsp;</label>
                      <div class="d-flex flex-wrap justify-content-md-end" style="gap:8px;">
                        <button type="submit" class="btn btn-primary"><i class="ti-search mr-1"></i> Pesquisar</button>
                        <a class="btn btn-light" href="<?= h(buildUrl(['q' => null, 'p' => null])) ?>"><i class="ti-close mr-1"></i> Limpar</a>
                       
                      </div>
                      
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabela -->
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                      <h4 class="card-title mb-0">Lista de Produtores</h4>
                      <p class="card-description mb-0">
                        Total: <?= (int)$totalRows ?> — Página <?= (int)$page ?> de <?= (int)$totalPages ?>.
                      </p>
                    </div>
                      <a href="./adicionarProdutor.php" class="btn btn-primary btn-sm mt-2 mt-md-0">
                      <i class="ti-plus"></i> Adicionar
                    </a>
                  </div>

                  <div class="table-responsive pt-3">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th style="width:90px;">ID</th>
                          <th>Produtor</th>
                          <th>Comunidade</th>
                          <th>Contato</th>
                          <th>Status</th>
                          <th style="min-width: 320px;">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($produtores)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum produtor encontrado.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($produtores as $p): ?>
                            <?php
                            $id = (int)($p['id'] ?? 0);
                            $ativo = ((int)($p['ativo'] ?? 0) === 1);
                            $badgeClass = $ativo ? 'badge-success' : 'badge-danger';
                            $badgeText  = $ativo ? 'Ativo' : 'Inativo';

                            $comunidadeNome = (string)($p['comunidade'] ?? '');
                            $comId = (int)($p['comunidade_id'] ?? 0);
                            if ($comunidadeNome === '' && $comId > 0) $comunidadeNome = 'ID ' . $comId;

                            $contato = trim((string)($p['contato'] ?? ''));
                            $doc = trim((string)($p['documento'] ?? ''));
                            $obs = trim((string)($p['observacao'] ?? ''));
                            ?>
                            <tr>
                              <td><?= $id ?></td>
                              <td>
                                <div class="font-weight-bold"><?= h($p['nome'] ?? '') ?></div>
                                <?php if ($doc !== ''): ?><div class="muted-small">CPF/Doc: <?= h($doc) ?></div><?php endif; ?>
                              </td>
                              <td><?= h($comunidadeNome) ?></td>
                              <td><?= h($contato) ?></td>
                              <td><label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label></td>
                              <td>
                                <div class="acoes-wrap">

                                  <!-- EDITAR (abre modal e preenche) -->
                                  <button
                                    type="button"
                                    class="btn btn-outline-primary btn-xs js-edit"
                                    data-toggle="modal"
                                    data-target="#modalEditProdutor"
                                    data-id="<?= (int)$id ?>"
                                    data-nome="<?= h($p['nome'] ?? '') ?>"
                                    data-contato="<?= h($contato) ?>"
                                    data-documento="<?= h($doc) ?>"
                                    data-observacao="<?= h($obs) ?>"
                                    data-ativo="<?= $ativo ? '1' : '0' ?>"
                                    data-comunidade_id="<?= (int)$comId ?>">
                                    <i class="ti-pencil"></i> Editar
                                  </button>

                                  <!-- Toggle ativo -->
                                  <form method="post" action="<?= h(buildUrl(['p' => $page])) ?>" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button
                                      type="submit"
                                      class="btn btn-outline-<?= $ativo ? 'warning' : 'success' ?> btn-xs"
                                      onclick="return confirm('Confirma <?= $ativo ? 'desativar' : 'ativar' ?> este produtor?');">
                                      <i class="ti-power-off"></i> <?= $ativo ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                  </form>

                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>

                    <!-- Paginação -->
                    <?php if ($totalPages > 1): ?>
                      <?php
                      $prev = max(1, $page - 1);
                      $next = min($totalPages, $page + 1);

                      $start = max(1, $page - 2);
                      $end   = min($totalPages, $page + 2);
                      if ($end - $start < 4) {
                        $start = max(1, $end - 4);
                        $end   = min($totalPages, $start + 4);
                      }
                      ?>
                      <div class="sig-pager">
                        <div class="info">
                          Mostrando <?= (int)count($produtores) ?> de <?= (int)$totalRows ?> (<?= (int)$perPage ?> por página)
                        </div>
                        <nav aria-label="Paginação produtores">
                          <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(buildUrl(['p' => 1])) ?>">«</a></li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(buildUrl(['p' => $prev])) ?>">Anterior</a></li>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= h(buildUrl(['p' => $i])) ?>"><?= $i ?></a>
                              </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(buildUrl(['p' => $next])) ?>">Próxima</a></li>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(buildUrl(['p' => $totalPages])) ?>">»</a></li>
                          </ul>
                        </nav>
                      </div>
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
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
              Todos os direitos reservados.
            </span>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <!-- ===================== MODAL EDITAR PRODUTOR ===================== -->
  <div class="modal fade" id="modalEditProdutor" tabindex="-1" role="dialog" aria-labelledby="modalEditProdutorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <form method="post" action="<?= h(buildUrl(['p' => $page])) ?>" class="modal-content">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="modalEditProdutorLabel"><i class="ti-pencil"></i> Editar Produtor</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Nome <span class="text-danger">*</span></label>
              <input type="text" name="nome" id="edit_nome" class="form-control" required>
              <small class="text-muted">Até 160 caracteres.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>CPF / Documento</label>
              <input type="text" name="documento" id="edit_documento" class="form-control" placeholder="Somente números">
              <small class="text-muted">Opcional.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Contato</label>
              <input type="text" name="contato" id="edit_contato" class="form-control" placeholder="Ex.: 9299...">
              <small class="text-muted">Opcional.</small>
            </div>
          </div>

          <div class="row">
            <?php if (!empty($comunidades)): ?>
              <div class="col-md-6 mb-3">
                <label>Comunidade <span class="text-danger">*</span></label>
                <select name="comunidade_id" id="edit_comunidade_id" class="form-control" required>
                  <option value="">Selecione</option>
                  <?php foreach ($comunidades as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Somente comunidades ativas.</small>
              </div>
            <?php else: ?>
              <div class="col-md-6 mb-3">
                <label>Comunidade</label>
                <input type="text" class="form-control" value="Tabela comunidades não encontrada" disabled>
                <small class="text-muted">Cadastre comunidades para habilitar este campo.</small>
              </div>
            <?php endif; ?>

            <div class="col-md-3 mb-3">
              <label>Status</label>
              <select name="ativo" id="edit_ativo" class="form-control">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 mb-2">
              <label>Observação</label>
              <textarea name="observacao" id="edit_observacao" class="form-control" rows="4" placeholder="Ex.: produtor de farinha..."></textarea>
              <small class="text-muted">Até 255 caracteres.</small>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="ti-close mr-1"></i> Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="ti-save mr-1"></i> Salvar alterações</button>
        </div>
      </form>
    </div>
  </div>
  <!-- ================================================================ -->

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
    // Preenche modal com dados do botão
    (function() {
      var modal = document.getElementById('modalEditProdutor');
      if (!modal) return;

      // jQuery está presente no vendor.bundle.base.js
      $('#modalEditProdutor').on('show.bs.modal', function(event) {
        var btn = $(event.relatedTarget);
        $('#edit_id').val(btn.data('id') || '');
        $('#edit_nome').val(btn.data('nome') || '');
        $('#edit_contato').val(btn.data('contato') || '');
        $('#edit_documento').val(btn.data('documento') || '');
        $('#edit_observacao').val(btn.data('observacao') || '');
        $('#edit_ativo').val(String(btn.data('ativo') ?? '1'));

        var cid = btn.data('comunidade_id');
        if (cid !== undefined && cid !== null && cid !== '') {
          $('#edit_comunidade_id').val(String(cid));
        }
      });

      // Limpa ao fechar (evita “vazar” dados de um produtor pra outro)
      $('#modalEditProdutor').on('hidden.bs.modal', function() {
        $('#edit_id').val('');
        $('#edit_nome').val('');
        $('#edit_contato').val('');
        $('#edit_documento').val('');
        $('#edit_observacao').val('');
        $('#edit_ativo').val('1');
        $('#edit_comunidade_id').val('');
      });
    })();
  </script>
</body>

</html>