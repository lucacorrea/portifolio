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
function to_int($v): int {
  $s = trim((string)$v);
  if ($s === '') return 0;
  $s = preg_replace('/\D+/', '', $s) ?? '0';
  return (int)$s;
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

/* ===== Carregar combos ===== */
$produtoresAtivos = [];
$produtosAtivos   = [];
try {
  $stP = $pdo->prepare("SELECT id, nome FROM produtores WHERE feira_id = :f AND ativo = 1 ORDER BY nome ASC");
  $stP->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $stP->execute();
  $produtoresAtivos = $stP->fetchAll();

  $stPr = $pdo->prepare("
    SELECT p.id, p.nome,
           p.categoria_id, p.unidade_id,
           COALESCE(c.nome,'') AS categoria_nome,
           COALESCE(u.sigla,'') AS unidade_sigla,
           p.preco_referencia
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN unidades u   ON u.id = p.unidade_id
    WHERE p.feira_id = :f AND p.ativo = 1
    ORDER BY p.nome ASC
  ");
  $stPr->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $stPr->execute();
  $produtosAtivos = $stPr->fetchAll();
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os cadastros agora.';
}

/* ===== Ações (Salvar / Excluir) ===== */
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
      header('Location: ./lancamentos.php');
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
      header('Location: ./lancamentos.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível excluir o lançamento agora.';
      header('Location: ./lancamentos.php');
      exit;
    }
  }

  if ($acao === 'salvar') {
    $dataVenda   = trim((string)($_POST['data_venda'] ?? ''));
    $produtorId  = (int)($_POST['produtor_id'] ?? 0);
    $obs         = trim((string)($_POST['observacao'] ?? ''));

    $produtoIds  = $_POST['produto_id'] ?? [];
    $qtds        = $_POST['qtd'] ?? [];
    $vunit       = $_POST['valor_unit'] ?? [];

    if ($dataVenda === '') {
      $err = 'Informe a data do lançamento.';
    } elseif ($produtorId <= 0) {
      $err = 'Selecione o produtor.';
    } else {
      $itens = [];
      $total = 0.0;

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
          'qtd'        => $q,
          'valor_unit' => $vu,
          'subtotal'   => $sub
        ];
      }

      if (empty($itens)) {
        $err = 'Adicione pelo menos 1 item (produto + valor).';
      } elseif ($total <= 0) {
        $err = 'Total inválido.';
      } else {
        try {
          $pdo->beginTransaction();

          $ins = $pdo->prepare("
            INSERT INTO vendas (feira_id, produtor_id, data_venda, total, observacao, criado_em)
            VALUES (:f, :prod, :data, :total, :obs, NOW())
          ");
          $ins->bindValue(':f', $feiraId, PDO::PARAM_INT);
          $ins->bindValue(':prod', $produtorId, PDO::PARAM_INT);
          $ins->bindValue(':data', $dataVenda, PDO::PARAM_STR);
          $ins->bindValue(':total', $total);
          if ($obs === '') $ins->bindValue(':obs', null, PDO::PARAM_NULL);
          else $ins->bindValue(':obs', $obs, PDO::PARAM_STR);
          $ins->execute();

          $vendaId = (int)$pdo->lastInsertId();

          $insItem = $pdo->prepare("
            INSERT INTO venda_itens (feira_id, venda_id, produto_id, qtd, valor_unit, subtotal)
            VALUES (:f, :v, :p, :q, :vu, :sub)
          ");

          foreach ($itens as $it) {
            $insItem->bindValue(':f', $feiraId, PDO::PARAM_INT);
            $insItem->bindValue(':v', $vendaId, PDO::PARAM_INT);
            $insItem->bindValue(':p', $it['produto_id'], PDO::PARAM_INT);
            $insItem->bindValue(':q', $it['qtd']);
            $insItem->bindValue(':vu', $it['valor_unit']);
            $insItem->bindValue(':sub', $it['subtotal']);
            $insItem->execute();
          }

          $pdo->commit();
          $_SESSION['flash_ok'] = 'Lançamento registrado com sucesso.';
          header('Location: ./lancamentos.php');
          exit;
        } catch (PDOException $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $mysqlCode = (int)($e->errorInfo[1] ?? 0);
          if ($mysqlCode === 1146) {
            $err = 'As tabelas de lançamentos não existem (vendas / venda_itens). Rode o SQL das tabelas.';
          } else {
            $err = 'Não foi possível salvar o lançamento agora.';
          }
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $err = 'Não foi possível salvar o lançamento agora.';
        }
      }
    }
  }
}

/* ===== Listagem ===== */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
$prodFiltro = (int)($_GET['produtor'] ?? 0);

$vendas = [];
try {
  $sql = "
    SELECT v.id, v.data_venda, v.total, v.observacao,
           p.nome AS produtor_nome
    FROM vendas v
    JOIN produtores p ON p.id = v.produtor_id
    WHERE v.feira_id = :f
  ";
  $params = [':f' => $feiraId];

  if ($dia !== '') {
    $sql .= " AND v.data_venda = :dia";
    $params[':dia'] = $dia;
  }
  if ($prodFiltro > 0) {
    $sql .= " AND v.produtor_id = :prod";
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

    .acoes-wrap{ display:flex; flex-wrap:wrap; gap:8px; }
    .btn-xs{ padding: .25rem .5rem; font-size: .75rem; line-height: 1.2; height:auto; }
    .table td, .table th{ vertical-align: middle !important; }
    .helper{ font-size: 12px; }

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
      animation:
        sigToastIn .22s ease-out forwards,
        sigToastOut .25s ease-in forwards 5.75s;
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

    <div class="main-panel">
      <div class="content-wrapper">

        <div class="row">
          <div class="col-12 mb-3">
            <h3 class="font-weight-bold">Lançamentos (Vendas)</h3>
            <h6 class="font-weight-normal mb-0">Registro “na fala”: sem caixa próprio, sem código — apenas itens e valores.</h6>
          </div>
        </div>

        <!-- FORM NOVO LANÇAMENTO -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Novo Lançamento</h4>
                    <p class="card-description mb-0">Preencha o produtor e os itens vendidos.</p>
                  </div>
                </div>

                <hr>

                <form method="post" action="./lancamentos.php" autocomplete="off">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="acao" value="salvar">

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Data</label>
                      <input type="date" class="form-control" name="data_venda" value="<?= h($dia) ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                      <label class="mb-1">Produtor</label>
                      <select class="form-control" name="produtor_id" required>
                        <option value="" selected disabled>Selecione</option>
                        <?php foreach ($produtoresAtivos as $p): ?>
                          <option value="<?= (int)$p['id'] ?>"><?= h($p['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted helper">Somente produtores ativos aparecem.</small>
                    </div>

                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Observação (opcional)</label>
                      <input type="text" class="form-control" name="observacao" placeholder="Ex.: fiado / desconto / etc.">
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th style="width:140px;">Qtd</th>
                          <th style="width:170px;">Valor (R$)</th>
                          <th style="width:140px;">Unid</th>
                          <th style="width:220px;">Categoria</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php for ($i=0; $i<10; $i++): ?>
                          <tr>
                            <td>
                              <select class="form-control" name="produto_id[]">
                                <option value="0">—</option>
                                <?php foreach ($produtosAtivos as $pr): ?>
                                  <option
                                    value="<?= (int)$pr['id'] ?>"
                                    data-un="<?= h($pr['unidade_sigla'] ?? '') ?>"
                                    data-cat="<?= h($pr['categoria_nome'] ?? '') ?>"
                                    data-preco="<?= h((string)($pr['preco_referencia'] ?? '')) ?>"
                                  >
                                    <?= h($pr['nome'] ?? '') ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                            <td>
                              <input type="text" class="form-control" name="qtd[]" placeholder="1" value="1">
                            </td>
                            <td>
                              <input type="text" class="form-control" name="valor_unit[]" placeholder="Ex.: 10,00">
                            </td>
                            <td>
                              <input type="text" class="form-control" value="" readonly>
                            </td>
                            <td>
                              <input type="text" class="form-control" value="" readonly>
                            </td>
                          </tr>
                        <?php endfor; ?>
                      </tbody>
                    </table>
                  </div>

                  <small class="text-muted d-block mt-2">
                    Dica: preencha somente as linhas necessárias. O valor pode ser o “falado” (não precisa bater com referência).
                  </small>

                  <div class="d-flex flex-wrap mt-3" style="gap:8px;">
                    <button type="submit" class="btn btn-primary">
                      <i class="ti-save mr-1"></i> Salvar Lançamento
                    </button>
                    <a href="./lancamentos.php" class="btn btn-light">
                      <i class="ti-close mr-1"></i> Limpar
                    </a>
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
                    <h4 class="card-title mb-0">Lançamentos do dia</h4>
                    <p class="card-description mb-0">Mostrando <?= (int)count($vendas) ?> registro(s).</p>
                  </div>
                </div>

                <div class="row mt-3">
                  <div class="col-md-3 mb-2">
                    <label class="mb-1">Dia</label>
                    <input type="date" class="form-control" value="<?= h($dia) ?>" onchange="location.href='?dia='+this.value<?= $prodFiltro>0 ? "+'&produtor=".((int)$prodFiltro)."'" : "" ?>;">
                  </div>
                  <div class="col-md-6 mb-2">
                    <label class="mb-1">Produtor</label>
                    <select class="form-control" onchange="location.href='?dia=<?= h($dia) ?>&produtor='+this.value;">
                      <option value="0">Todos</option>
                      <?php foreach ($produtoresAtivos as $p): $pid=(int)$p['id']; ?>
                        <option value="<?= $pid ?>" <?= $prodFiltro===$pid ? 'selected' : '' ?>><?= h($p['nome'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
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
                        <th style="width:140px;">Data</th>
                        <th>Produtor</th>
                        <th style="width:160px;">Total</th>
                        <th style="min-width:210px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($vendas)): ?>
                        <tr>
                          <td colspan="5" class="text-center text-muted py-4">Nenhum lançamento encontrado.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($vendas as $v): ?>
                          <?php
                            $vid = (int)($v['id'] ?? 0);
                            $tot = (float)($v['total'] ?? 0);
                          ?>
                          <tr>
                            <td><?= $vid ?></td>
                            <td><?= h($v['data_venda'] ?? '') ?></td>
                            <td><?= h($v['produtor_nome'] ?? '') ?></td>
                            <td><b>R$ <?= number_format($tot, 2, ',', '.') ?></b></td>
                            <td>
                              <div class="acoes-wrap">
                                <button type="button" class="btn btn-outline-primary btn-xs" disabled>
                                  <i class="ti-eye"></i> Detalhes
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
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <small class="text-muted d-block mt-2">
                  Obs.: “Detalhes” fica habilitado quando você quiser (a gente cria a tela/aba do detalhamento).
                </small>

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
  // Preencher “Unid” e “Categoria” na mesma linha (sem mudar layout)
  (function(){
    const table = document.querySelector('table');
    if (!table) return;

    function refreshRow(sel){
      const tr = sel.closest('tr');
      if (!tr) return;
      const opt = sel.options[sel.selectedIndex];
      const un = (opt && opt.dataset && opt.dataset.un) ? opt.dataset.un : '';
      const cat = (opt && opt.dataset && opt.dataset.cat) ? opt.dataset.cat : '';

      const tds = tr.querySelectorAll('td');
      if (tds.length >= 5) {
        const unInput  = tds[3].querySelector('input');
        const catInput = tds[4].querySelector('input');
        if (unInput) unInput.value = un;
        if (catInput) catInput.value = cat;
      }
    }

    document.querySelectorAll('select[name="produto_id[]"]').forEach(sel=>{
      sel.addEventListener('change', function(){ refreshRow(this); });
      refreshRow(sel);
    });
  })();
</script>
</body>
</html>
