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

$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));

/* ===== Detecta tabela de fechamento ===== */
$hasFechamentos = false;
try {
  $st = $pdo->prepare("
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'fechamentos_dia'
  ");
  $st->execute();
  $hasFechamentos = ((int)$st->fetchColumn() > 0);
} catch (Throwable $e) {
  $hasFechamentos = false;
}

/* ===== Registrar fechamento (se tabela existir) ===== */
$fechamento = null;

if ($hasFechamentos) {
  try {
    $stF = $pdo->prepare("
      SELECT id, data_fechamento, total_dia, vendas_qtd, produtores_qtd, observacao, criado_em
      FROM fechamentos_dia
      WHERE feira_id = :f AND data_fechamento = :d
      LIMIT 1
    ");
    $stF->bindValue(':f', $feiraId, PDO::PARAM_INT);
    $stF->bindValue(':d', $dia, PDO::PARAM_STR);
    $stF->execute();
    $fechamento = $stF->fetch() ?: null;
  } catch (Throwable $e) {
    $fechamento = null;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasFechamentos) {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./fechamentoDia.php?dia='.urlencode($dia));
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');
  if ($acao === 'fechar') {
    $obs = trim((string)($_POST['observacao'] ?? ''));

    try {
      $stSum = $pdo->prepare("
        SELECT 
          COUNT(*) AS vendas_qtd,
          COALESCE(SUM(total), 0) AS total_dia,
          COUNT(DISTINCT produtor_id) AS produtores_qtd
        FROM vendas
        WHERE feira_id = :f AND data_venda = :d
      ");
      $stSum->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $stSum->bindValue(':d', $dia, PDO::PARAM_STR);
      $stSum->execute();
      $sum = $stSum->fetch() ?: ['vendas_qtd'=>0,'total_dia'=>0,'produtores_qtd'=>0];

      $vendasQtd = (int)($sum['vendas_qtd'] ?? 0);
      $totalDia  = (float)($sum['total_dia'] ?? 0);
      $prodQtd   = (int)($sum['produtores_qtd'] ?? 0);

      $pdo->beginTransaction();

      $ins = $pdo->prepare("
        INSERT INTO fechamentos_dia (feira_id, data_fechamento, total_dia, vendas_qtd, produtores_qtd, observacao, criado_em)
        VALUES (:f, :d, :t, :vq, :pq, :obs, NOW())
        ON DUPLICATE KEY UPDATE
          total_dia = VALUES(total_dia),
          vendas_qtd = VALUES(vendas_qtd),
          produtores_qtd = VALUES(produtores_qtd),
          observacao = VALUES(observacao),
          atualizado_em = NOW()
      ");
      $ins->bindValue(':f', $feiraId, PDO::PARAM_INT);
      $ins->bindValue(':d', $dia, PDO::PARAM_STR);
      $ins->bindValue(':t', $totalDia);
      $ins->bindValue(':vq', $vendasQtd, PDO::PARAM_INT);
      $ins->bindValue(':pq', $prodQtd, PDO::PARAM_INT);
      if ($obs === '') $ins->bindValue(':obs', null, PDO::PARAM_NULL);
      else $ins->bindValue(':obs', $obs, PDO::PARAM_STR);
      $ins->execute();

      $pdo->commit();

      $_SESSION['flash_ok'] = 'Fechamento do dia registrado.';
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $mysqlCode = (int)($e->errorInfo[1] ?? 0);
      if ($mysqlCode === 1146) $_SESSION['flash_err'] = 'A tabela de fechamento não existe. Rode o SQL do fechamento.';
      else $_SESSION['flash_err'] = 'Não foi possível registrar o fechamento agora.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível registrar o fechamento agora.';
    }

    header('Location: ./fechamentoDia.php?dia='.urlencode($dia));
    exit;
  }
}

/* ===== Resumo do dia ===== */
$resumo = [
  'vendas_qtd' => 0,
  'total_dia' => 0.0,
  'produtores_qtd' => 0,
  'ticket_medio' => 0.0,
];
$porProdutor = [];
$topProdutos = [];

try {
  $st = $pdo->prepare("
    SELECT 
      COUNT(*) AS vendas_qtd,
      COALESCE(SUM(total), 0) AS total_dia,
      COUNT(DISTINCT produtor_id) AS produtores_qtd
    FROM vendas
    WHERE feira_id = :f AND data_venda = :d
  ");
  $st->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $st->bindValue(':d', $dia, PDO::PARAM_STR);
  $st->execute();
  $r = $st->fetch() ?: null;

  if ($r) {
    $resumo['vendas_qtd'] = (int)($r['vendas_qtd'] ?? 0);
    $resumo['total_dia'] = (float)($r['total_dia'] ?? 0);
    $resumo['produtores_qtd'] = (int)($r['produtores_qtd'] ?? 0);
    $resumo['ticket_medio'] = $resumo['vendas_qtd'] > 0 ? ($resumo['total_dia'] / $resumo['vendas_qtd']) : 0.0;
  }

  $st2 = $pdo->prepare("
    SELECT p.id, p.nome,
           COUNT(v.id) AS vendas_qtd,
           COALESCE(SUM(v.total), 0) AS total
    FROM vendas v
    JOIN produtores p ON p.id = v.produtor_id
    WHERE v.feira_id = :f AND v.data_venda = :d
    GROUP BY p.id, p.nome
    ORDER BY total DESC, p.nome ASC
  ");
  $st2->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $st2->bindValue(':d', $dia, PDO::PARAM_STR);
  $st2->execute();
  $porProdutor = $st2->fetchAll();

  $st3 = $pdo->prepare("
    SELECT pr.id, pr.nome,
           COALESCE(SUM(vi.qtd), 0) AS qtd_total,
           COALESCE(SUM(vi.subtotal), 0) AS valor_total
    FROM venda_itens vi
    JOIN vendas v   ON v.id = vi.venda_id AND v.feira_id = vi.feira_id
    JOIN produtos pr ON pr.id = vi.produto_id
    WHERE vi.feira_id = :f AND v.data_venda = :d
    GROUP BY pr.id, pr.nome
    ORDER BY valor_total DESC
    LIMIT 15
  ");
  $st3->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $st3->bindValue(':d', $dia, PDO::PARAM_STR);
  $st3->execute();
  $topProdutos = $st3->fetchAll();

} catch (PDOException $e) {
  $mysqlCode = (int)($e->errorInfo[1] ?? 0);
  if ($mysqlCode === 1146) $err = $err ?: 'As tabelas de lançamentos não existem (vendas / venda_itens). Rode o SQL das tabelas.';
  else $err = $err ?: 'Não foi possível carregar o fechamento do dia agora.';
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar o fechamento do dia agora.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Fechamento do Dia</title>

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

    .kpi-card{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
    }
    .kpi-label{ font-size: 12px; color: #6c757d; margin:0; }
    .kpi-value{ font-size: 22px; font-weight: 800; margin:0; }
    .kpi-sub{ font-size: 12px; color: #6c757d; margin-top:6px; }

    .table td, .table th { vertical-align: middle !important; }

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

    <!-- SIDEBAR (NÃO MEXI NO PADRÃO) -->
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

        <!-- MOVIMENTO (ATIVO) -->
        <li class="nav-item active">
          <a class="nav-link open" data-toggle="collapse" href="#feiraMovimento" aria-expanded="true" aria-controls="feiraMovimento">
            <i class="ti-exchange-vertical menu-icon"></i>
            <span class="menu-title">Movimento</span>
            <i class="menu-arrow"></i>
          </a>

          <div class="collapse show" id="feiraMovimento">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item">
                <a class="nav-link" href="./lancamentos.php">
                  <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                </a>
              </li>
              <li class="nav-item active">
                <a class="nav-link" href="./fechamentoDia.php" style="color:white !important; background: #231475C5 !important;">
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
            <h3 class="font-weight-bold">Fechamento do Dia</h3>
            <h6 class="font-weight-normal mb-0">Resumo rápido do dia + (opcional) registrar o fechamento.</h6>
          </div>
        </div>

        <!-- FILTRO (SIMPLES) -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Dia</h4>
                    <p class="card-description mb-0">Selecione a data para ver o resumo.</p>
                  </div>
                </div>

                <div class="row mt-3">
                  <div class="col-md-4 mb-2">
                    <label class="mb-1">Data</label>
                    <input type="date" class="form-control" value="<?= h($dia) ?>"
                      onchange="location.href='?dia='+this.value;">
                  </div>

                  <div class="col-md-8 mb-2 d-flex align-items-end">
                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <a href="./lancamentos.php?dia=<?= h($dia) ?>" class="btn btn-light">
                        <i class="ti-write mr-1"></i> Ver lançamentos do dia
                      </a>
                      <a href="./fechamentoDia.php" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Hoje
                      </a>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row">
          <div class="col-md-3 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Total do dia</p>
              <p class="kpi-value">R$ <?= number_format($resumo['total_dia'], 2, ',', '.') ?></p>
              <div class="kpi-sub">Somatório dos lançamentos</div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Vendas (lanc.)</p>
              <p class="kpi-value"><?= (int)$resumo['vendas_qtd'] ?></p>
              <div class="kpi-sub">Quantidade de lançamentos</div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Produtores</p>
              <p class="kpi-value"><?= (int)$resumo['produtores_qtd'] ?></p>
              <div class="kpi-sub">Produtores com venda no dia</div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Ticket médio</p>
              <p class="kpi-value">R$ <?= number_format($resumo['ticket_medio'], 2, ',', '.') ?></p>
              <div class="kpi-sub">Total ÷ vendas</div>
            </div>
          </div>
        </div>

        <!-- FECHAMENTO (OPCIONAL) -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Registrar Fechamento</h4>
                    <p class="card-description mb-0">Opcional: salva um “carimbo” do fechamento para o dia selecionado.</p>
                  </div>
                </div>

                <hr>

                <?php if (!$hasFechamentos): ?>
                  <div class="alert alert-warning" role="alert" style="border-radius:12px;">
                    <b>Atenção:</b> a tabela <b>fechamentos_dia</b> ainda não existe.  
                    Se quiser registrar o fechamento (salvar), rode o SQL dessa tabela.
                  </div>
                <?php else: ?>

                  <?php if ($fechamento): ?>
                    <div class="alert alert-success" role="alert" style="border-radius:12px;">
                      <b>Fechado!</b> Este dia já tem fechamento registrado.
                      <div class="mt-2">
                        <div><b>Total:</b> R$ <?= number_format((float)($fechamento['total_dia'] ?? 0), 2, ',', '.') ?></div>
                        <div><b>Vendas:</b> <?= (int)($fechamento['vendas_qtd'] ?? 0) ?> • <b>Produtores:</b> <?= (int)($fechamento['produtores_qtd'] ?? 0) ?></div>
                        <?php if (!empty($fechamento['observacao'])): ?>
                          <div class="mt-1"><b>Obs:</b> <?= h($fechamento['observacao']) ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <form method="post" action="./fechamentoDia.php?dia=<?= h($dia) ?>">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="acao" value="fechar">

                    <div class="row">
                      <div class="col-md-9 mb-2">
                        <label class="mb-1">Observação (opcional)</label>
                        <input type="text" class="form-control" name="observacao" placeholder="Ex.: conferido com a equipe / pendência X">
                      </div>
                      <div class="col-md-3 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"
                          onclick="return confirm('Registrar fechamento do dia <?= h($dia) ?>?');">
                          <i class="ti-check mr-1"></i> Registrar
                        </button>
                      </div>
                    </div>

                    <small class="text-muted d-block mt-2">
                      Se já existir, o registro é atualizado (não duplica).
                    </small>
                  </form>

                <?php endif; ?>

              </div>
            </div>
          </div>
        </div>

        <!-- POR PRODUTOR -->
        <div class="row">
          <div class="col-lg-7 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Resumo por Produtor</h4>
                    <p class="card-description mb-0">Quem vendeu mais no dia.</p>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Produtor</th>
                        <th style="width:120px;">Vendas</th>
                        <th style="width:170px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($porProdutor)): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-4">Nenhum dado para este dia.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($porProdutor as $p): ?>
                          <tr>
                            <td><?= h($p['nome'] ?? '') ?></td>
                            <td><?= (int)($p['vendas_qtd'] ?? 0) ?></td>
                            <td><b>R$ <?= number_format((float)($p['total'] ?? 0), 2, ',', '.') ?></b></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>
            </div>
          </div>

          <!-- TOP PRODUTOS -->
          <div class="col-lg-5 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Top Produtos</h4>
                    <p class="card-description mb-0">Os mais vendidos (por valor) no dia.</p>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Produto</th>
                        <th style="width:90px;">Qtd</th>
                        <th style="width:150px;">Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($topProdutos)): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-4">Nenhum dado para este dia.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($topProdutos as $tp): ?>
                          <tr>
                            <td><?= h($tp['nome'] ?? '') ?></td>
                            <td><?= number_format((float)($tp['qtd_total'] ?? 0), 2, ',', '.') ?></td>
                            <td><b>R$ <?= number_format((float)($tp['valor_total'] ?? 0), 2, ',', '.') ?></b></td>
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

<script src="../../../vendors/js/vendor.bundle.base.js"></script>
<script src="../../../vendors/chart.js/Chart.min.js"></script>

<script src="../../../js/off-canvas.js"></script>
<script src="../../../js/hoverable-collapse.js"></script>
<script src="../../../js/template.js"></script>
<script src="../../../js/settings.js"></script>
<script src="../../../js/todolist.js"></script>

<script src="../../../js/dashboard.js"></script>
<script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>
</html>
