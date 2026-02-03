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

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
function hasTable(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  return (int)$st->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
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
   TABELAS
====================== */
if (
  !hasTable($pdo, 'vendas') ||
  !hasTable($pdo, 'venda_itens') ||
  !hasTable($pdo, 'produtos') ||
  !hasTable($pdo, 'produtores')
) {
  $err = 'Tabelas obrigatórias não encontradas.';
}

/* ======================
   CAMPOS OPCIONAIS
====================== */
$colDataVenda = hasColumn($pdo, 'vendas', 'data_venda');
$colDataHora  = hasColumn($pdo, 'vendas', 'data_hora');

/* Data base */
if ($colDataVenda) {
  $dateExpr = "v.data_venda";
} elseif ($colDataHora) {
  $dateExpr = "DATE(v.data_hora)";
} else {
  $dateExpr = "DATE(v.criado_em)";
}

/* ======================
   PROCESSAMENTO
====================== */
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';
$gerarRelatorio = isset($_GET['gerar']) && $dataInicio && $dataFim;

$labelPeriodo = '';

// Validar datas
if ($gerarRelatorio) {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $err = 'Datas inválidas';
    $gerarRelatorio = false;
  } elseif (strtotime($dataInicio) > strtotime($dataFim)) {
    $err = 'Data inicial não pode ser maior que data final';
    $gerarRelatorio = false;
  } else {
    $labelPeriodo = date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
  }
}

/* ======================
   BUSCAR DADOS
====================== */
$resumo = ['total_vendas' => 0, 'valor_total' => 0];

if ($gerarRelatorio && !$err) {
  try {
    $params = [
      ':ini' => $dataInicio,
      ':fim' => $dataFim,
      ':f'   => $feiraId,
    ];

    // Total geral
    $st = $pdo->prepare("
      SELECT 
        COUNT(*) as total_vendas,
        SUM(v.total) as valor_total
      FROM vendas v
      WHERE {$dateExpr} BETWEEN :ini AND :fim
        AND v.feira_id = :f
    ");
    $st->execute($params);
    $resumo = $st->fetch();

  } catch (Exception $e) {
    $err = 'Erro ao buscar dados: ' . $e->getMessage();
    $gerarRelatorio = false;
  }
}

/* ======================
   FINAL
====================== */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Resumo Mensal</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
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

    .no-data {
      text-align: center;
      padding: 80px 20px;
      color: #999;
    }

    .no-data i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .generate-report-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 14px;
      padding: 40px;
      color: white;
      text-align: center;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .generate-report-card h4 {
      color: white;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .generate-report-card p {
      color: rgba(255,255,255,0.9);
      margin-bottom: 25px;
    }

    .btn-generate {
      background: white;
      color: #667eea;
      font-weight: 700;
      font-size: 16px;
      padding: 12px 40px;
      border-radius: 8px;
      border: none;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
    }

    .btn-generate:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
      color: #667eea;
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

            <div class="collapse show" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <style>
                  .sub-menu .nav-item .nav-link {
                    color: black !important;
                  }

                  .sub-menu .nav-item .nav-link:hover {
                    color: blue !important;
                  }
                </style>
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
                  <a class="nav-link active" href="./relatorioMensal.php" style="color:white !important; background: #231475C5 !important;">
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- ======================
         CABEÇALHO
         ====================== -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                  <h2 class="font-weight-bold mb-1">Resumo Mensal</h2>
                  <?php if ($gerarRelatorio): ?>
                    <span class="badge badge-primary">
                      Feira do Produtor — <?= h($labelPeriodo) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <hr>
            </div>
          </div>

          <!-- ======================
         FILTRO
         ====================== -->
          <div class="card mb-4">
            <div class="card-body py-3">

              <!-- Botão de período -->
              <div class="row mb-3">
                <div class="col-12">
                  <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-outline-primary active">
                      <input type="radio" name="tipo_filtro" value="periodo" checked>
                      <i class="ti-calendar mr-1"></i> Período Personalizado
                    </label>
                  </div>
                </div>
              </div>

              <form method="GET" action="">
                <div class="row align-items-end">

                  <!-- Data Inicial -->
                  <div class="col-md-4 mb-2">
                    <label class="mb-1">Data Inicial *</label>
                    <input type="date" name="data_inicio" class="form-control" 
                           value="<?= h($dataInicio) ?>" required>
                  </div>

                  <!-- Data Final -->
                  <div class="col-md-4 mb-2">
                    <label class="mb-1">Data Final *</label>
                    <input type="date" name="data_fim" class="form-control" 
                           value="<?= h($dataFim) ?>" required>
                  </div>

                  <!-- Botões -->
                  <div class="col-md-4 mb-2">
                    <div class="row">
                      <div class="col-6">
                        <button type="submit" name="gerar" value="1" class="btn btn-primary w-100">
                          <i class="ti-search mr-1"></i> Buscar
                        </button>
                      </div>
                      <div class="col-6">
                        <a href="./relatorioMensal.php" class="btn btn-outline-secondary w-100">
                          <i class="ti-reload mr-1"></i> Limpar
                        </a>
                      </div>
                    </div>
                  </div>

                </div>
              </form>
            </div>
          </div>

          <?php if (!$gerarRelatorio): ?>
            <!-- SEM DADOS -->
            <div class="no-data">
              <i class="ti-calendar"></i>
              <h4>Selecione um período</h4>
              <p>Escolha as datas inicial e final para consultar os dados e gerar o relatório mensal</p>
            </div>

          <?php else: ?>
            
            <!-- ======================
               KPIs
            ====================== -->
            <div class="row mb-4">
              <div class="col-md-6 mb-3">
                <div class="kpi-card text-success">
                  <p class="kpi-label">Total de vendas no período</p>
                  <p class="kpi-value"><?= number_format((int)$resumo['total_vendas'], 0, ',', '.') ?></p>
                  <div class="mini-kpi">Número de transações realizadas</div>
                </div>
              </div>

              <div class="col-md-6 mb-3">
                <div class="kpi-card text-primary">
                  <p class="kpi-label">Valor total arrecadado</p>
                  <p class="kpi-value">
                    R$ <?= number_format((float)$resumo['valor_total'], 2, ',', '.') ?>
                  </p>
                  <div class="mini-kpi">Receita bruta do período</div>
                </div>
              </div>
            </div>

            <!-- ======================
               GERAR RELATÓRIO ESCRITO
            ====================== -->
            <div class="card mb-4">
              <div class="card-body p-0">
                <div class="generate-report-card">
                  <i class="ti-files" style="font-size: 48px; margin-bottom: 20px;"></i>
                  <h4>Relatório Formatado Pronto!</h4>
                  <p>
                    Clique no botão abaixo para visualizar e imprimir o relatório oficial<br>
                    no formato do documento da prefeitura
                  </p>
                  <a href="gerarRelatorioEscrito.php?data_inicio=<?= h($dataInicio) ?>&data_fim=<?= h($dataFim) ?>" 
                     target="_blank" 
                     class="btn btn-generate">
                    <i class="ti-printer mr-2"></i>
                    Gerar Relatório Escrito
                  </a>
                </div>
              </div>
            </div>

          <?php endif; ?>

        </div>

        <!-- ======================
       FOOTER
       ====================== -->
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

</body>

</html>