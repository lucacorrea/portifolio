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

/* ===========================
   PAGINAÇÃO PADRÃO SIG (REUSO)
   =========================== */
function sig_build_url(string $baseUrl, array $add = [], array $removeKeys = []): string
{
  $cur = $_GET ?? [];

  foreach ($removeKeys as $rk) {
    unset($cur[$rk]);
  }
  foreach ($add as $k => $v) {
    if ($v === null) unset($cur[$k]);
    else $cur[$k] = (string)$v;
  }

  $qs = http_build_query($cur);
  return $qs ? ($baseUrl . '?' . $qs) : $baseUrl;
}

function sig_render_pagination(string $baseUrl, int $pagina, int $totalPaginas): void
{
  if ($totalPaginas <= 1) return;

  $prev = max(1, $pagina - 1);
  $next = min($totalPaginas, $pagina + 1);

  $disabledPrev = ($pagina <= 1) ? 'disabled' : '';
  $disabledNext = ($pagina >= $totalPaginas) ? 'disabled' : '';

  $win = 2; // 2 antes e 2 depois
  $ini = max(1, $pagina - $win);
  $fim = min($totalPaginas, $pagina + $win);

  if (($fim - $ini) < ($win * 2)) {
    $ini = max(1, $fim - ($win * 2));
    $fim = min($totalPaginas, $ini + ($win * 2));
  }
?>
  <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
    <div class="text-muted">
      Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?>
    </div>

    <nav aria-label="Paginação produtos">
      <ul class="pagination mb-0">

        <li class="page-item <?= $disabledPrev ?>">
          <a class="page-link" href="<?= h(sig_build_url($baseUrl, ['p' => $prev])) ?>" tabindex="-1">Anterior</a>
        </li>

        <?php if ($ini > 1): ?>
          <li class="page-item">
            <a class="page-link" href="<?= h(sig_build_url($baseUrl, ['p' => 1])) ?>">1</a>
          </li>
          <?php if ($ini > 2): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $ini; $i <= $fim; $i++): ?>
          <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
            <a class="page-link" href="<?= h(sig_build_url($baseUrl, ['p' => $i])) ?>"><?= (int)$i ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($fim < $totalPaginas): ?>
          <?php if ($fim < $totalPaginas - 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="<?= h(sig_build_url($baseUrl, ['p' => $totalPaginas])) ?>"><?= (int)$totalPaginas ?></a>
          </li>
        <?php endif; ?>

        <li class="page-item <?= $disabledNext ?>">
          <a class="page-link" href="<?= h(sig_build_url($baseUrl, ['p' => $next])) ?>">Próximo</a>
        </li>

      </ul>
    </nav>
  </div>
<?php
}

/* ===== Busca ===== */
$q = trim((string)($_GET['q'] ?? ''));

/* ===== Paginação (8 por página) ===== */
$porPagina = 8;
$pagina = (int)($_GET['p'] ?? 1);
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $porPagina;

/* ===== Ações (Ativar/Desativar e Excluir) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  $acao = (string)($_POST['acao'] ?? '');
  $id = (int)($_POST['id'] ?? 0);
  $returnP = (int)($_POST['return_p'] ?? 1);
  if ($returnP < 1) $returnP = 1;

  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
  }

  if ($id <= 0) {
    $_SESSION['flash_err'] = 'ID inválido.';
    header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
  }

  try {
    if ($acao === 'toggle') {
      $sql = "UPDATE produtos
              SET ativo = CASE WHEN ativo = 1 THEN 0 ELSE 1 END
              WHERE id = :id AND feira_id = :feira
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':id', $id, PDO::PARAM_INT);
      $stmt->bindValue(':feira', $feiraId, PDO::PARAM_INT);
      $stmt->execute();

      $_SESSION['flash_ok'] = ($stmt->rowCount() > 0) ? 'Status do produto atualizado.' : 'Produto não encontrado.';
      header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
      exit;
    }

    if ($acao === 'excluir') {
      $sql = "DELETE FROM produtos
              WHERE id = :id AND feira_id = :feira
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':id', $id, PDO::PARAM_INT);
      $stmt->bindValue(':feira', $feiraId, PDO::PARAM_INT);
      $stmt->execute();

      $_SESSION['flash_ok'] = ($stmt->rowCount() > 0) ? 'Produto excluído com sucesso.' : 'Produto não encontrado.';
      header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
      exit;
    }

    $_SESSION['flash_err'] = 'Ação inválida.';
    header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
  } catch (PDOException $e) {
    $mysqlCode = (int)($e->errorInfo[1] ?? 0);
    if ($mysqlCode === 1451) {
      $_SESSION['flash_err'] = 'Não é possível excluir: existem vendas/itens usando este produto.';
    } else {
      $_SESSION['flash_err'] = 'Não foi possível concluir a ação agora.';
    }
    header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = 'Não foi possível concluir a ação agora.';
    header('Location: ./listaProduto.php?p=' . $returnP . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
  }
}

/* ===== Total de registros (com busca) ===== */
$totalRegistros = 0;
$totalPaginas = 1;

try {
  $where = "p.feira_id = :feira";
  $params = [':feira' => $feiraId];

  if ($q !== '') {
    // placeholders diferentes (PDO MySQL com emulação OFF não permite repetir o mesmo :q)
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
    $params[':q5'] = '%' . $q . '%';

    $where .= " AND (
      p.nome LIKE :q1 OR
      c.nome LIKE :q2 OR
      u.nome LIKE :q3 OR
      u.sigla LIKE :q4 OR
      pr.nome LIKE :q5
    )";
  }

  $sqlCount = "
    SELECT COUNT(*)
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades   u ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
    LEFT JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
    WHERE $where
  ";
  $stmt = $pdo->prepare($sqlCount);
  $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;
    $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
  }

  $stmt->execute();

  $totalRegistros = (int)$stmt->fetchColumn();
  $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

  if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $porPagina;
  }
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível calcular a paginação agora.';
}

/* ===== Listagem (LIMIT/OFFSET) ===== */
$produtos = [];
try {
  $where = "p.feira_id = :feira";
  $params = [':feira' => $feiraId];

  if ($q !== '') {
    // placeholders diferentes (PDO MySQL com emulação OFF não permite repetir o mesmo :q)
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
    $params[':q5'] = '%' . $q . '%';

    $where .= " AND (
      p.nome LIKE :q1 OR
      c.nome LIKE :q2 OR
      u.nome LIKE :q3 OR
      u.sigla LIKE :q4 OR
      pr.nome LIKE :q5
    )";
  }

  $sql = "
    SELECT
      p.id,
      p.nome,
      p.ativo,
      p.preco_referencia,
      c.nome AS categoria_nome,
      u.sigla AS unidade_sigla,
      u.nome  AS unidade_nome,
      pr.nome AS produtor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades   u ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
    LEFT JOIN produtores pr ON pr.id = p.produtor_id AND pr.feira_id = p.feira_id
    WHERE $where
    ORDER BY p.nome ASC
    LIMIT :lim OFFSET :off
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;
    $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
  }

  $stmt->bindValue(':lim', (int)$porPagina, PDO::PARAM_INT);
  $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
  $stmt->execute();

  $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os produtos agora.';
}

/* ===== Texto “Mostrando X–Y de N” ===== */
$inicio = ($totalRegistros === 0) ? 0 : ($offset + 1);
$fim = min($offset + $porPagina, $totalRegistros);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Lista de Produtos</title>

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

    .table td,
    .table th {
      vertical-align: middle !important;
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

    /* Flash “Hostinger style” */
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
      animation:
        sigToastIn .22s ease-out forwards,
        sigToastOut .25s ease-in forwards 5.75s;
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

      <!-- (settings-panel mantido) -->
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

          <li class="nav-item active">
            <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="true" aria-controls="feiraCadastros">
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
                <li class="nav-item active">
                  <a class="nav-link" href="./listaProduto.php" style="color:white !important; background: #231475C5 !important;">
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Produtos</h3>
              <h6 class="font-weight-normal mb-0">Gerencie produtos (ativar/desativar/excluir).</h6>
            </div>
          </div>

          <!-- Toolbar busca -->
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <form method="get" action="./listaProduto.php" class="row align-items-end">
                    <div class="col-md-6">
                      <label class="mb-1">Pesquisa</label>
                      <input type="text" class="form-control" name="q" placeholder="Pesquisar por produto, categoria, unidade, produtor..." value="<?= h($q) ?>">
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                      <div class="d-flex flex-wrap justify-content-md-end" style="gap:8px;">
                        <button type="submit" class="btn btn-primary">
                          <i class="ti-search mr-1"></i> Pesquisar
                        </button>
                        <a class="btn btn-light" href="./listaProduto.php">
                          <i class="ti-close mr-1"></i> Limpar
                        </a>
                        
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
                      <h4 class="card-title mb-0">Lista de Produtos</h4>
                      <p class="card-description mb-0">
                        Mostrando <?= (int)$inicio ?>–<?= (int)$fim ?> de <?= (int)$totalRegistros ?> registro(s).
                      </p>
                    </div>

                    <a href="./adicionarProduto.php" class="btn btn-primary btn-sm mt-2 mt-md-0">
                      <i class="ti-plus"></i> Adicionar
                    </a>
                  </div>

                  <div class="table-responsive pt-3">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th style="width:90px;">ID</th>
                          <th>Produto</th>
                          <th>Categoria</th>
                          <th>Unidade</th>
                          <th>Produtor</th>
                          <th style="width:140px;">Preço</th>
                          <th style="width:160px;">Status</th>
                          <th style="min-width:220px;">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($produtos)): ?>
                          <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                              Nenhum produto encontrado.
                            </td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($produtos as $p): ?>
                            <?php
                            $id = (int)($p['id'] ?? 0);
                            $ativoP = (int)($p['ativo'] ?? 0) === 1;
                            $badgeClass = $ativoP ? 'badge-success' : 'badge-danger';
                            $badgeText  = $ativoP ? 'Ativo' : 'Inativo';

                            $preco = $p['preco_referencia'];
                            $precoFmt = ($preco === null || $preco === '') ? '-' : number_format((float)$preco, 2, ',', '.');

                            $categoriaNome = trim((string)($p['categoria_nome'] ?? ''));
                            if ($categoriaNome === '') $categoriaNome = '-';

                            $unSigla = trim((string)($p['unidade_sigla'] ?? ''));
                            $unNome  = trim((string)($p['unidade_nome'] ?? ''));
                            $unLabel = $unSigla !== '' ? $unSigla : $unNome;
                            if ($unLabel === '') $unLabel = '-';

                            $produtorNome = trim((string)($p['produtor_nome'] ?? ''));
                            if ($produtorNome === '') $produtorNome = '-';
                            ?>
                            <tr>
                              <td><?= $id ?></td>
                              <td class="font-weight-bold"><?= h($p['nome'] ?? '') ?></td>
                              <td><?= h($categoriaNome) ?></td>
                              <td><?= h($unLabel) ?></td>
                              <td><?= h($produtorNome) ?></td>
                              <td>R$ <?= h($precoFmt) ?></td>
                              <td><label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label></td>
                              <td>
                                <div class="acoes-wrap">

                                  <form method="post" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="toggle">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="return_p" value="<?= (int)$pagina ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-xs"
                                      onclick="return confirm('Deseja <?= $ativoP ? 'DESATIVAR' : 'ATIVAR' ?> este produto?');">
                                      <i class="ti-power-off"></i> <?= $ativoP ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                  </form>

                                  <form method="post" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="return_p" value="<?= (int)$pagina ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-xs"
                                      onclick="return confirm('Tem certeza que deseja EXCLUIR este produto?');">
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

                    <!-- PAGINAÇÃO (MESMO PADRÃO) -->
                    <?php sig_render_pagination('./listaProduto.php', (int)$pagina, (int)$totalPaginas); ?>
                    <!-- /PAGINAÇÃO -->

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
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>

</html>
