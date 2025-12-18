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

$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

/* ===== Conexão + Listagem ===== */
require '../../../assets/php/conexao.php';

if (!function_exists('db')) {
  $err = $err ?: 'Função db() não encontrada em conexao.php';
  $produtores = [];
} else {
  $pdo = db();

  /* Feira do Produtor = 1 (na pasta da Feira Alternativa você coloca 2) */
  $feiraId = 1;

  $q = trim((string)($_GET['q'] ?? ''));
  $produtores = [];
  $errDetail = '';

  $pickCol = function(array $byLower, array $candidates): ?string {
    foreach ($candidates as $c) {
      $k = strtolower($c);
      if (isset($byLower[$k])) return $byLower[$k];
    }
    return null;
  };

  $cleanErr = function(string $m): string {
    $m = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $m) ?? $m;
    $m = preg_replace('/\(SQL:\s*.*\)$/', '', $m) ?? $m;
    return trim((string)$m);
  };

  try {
    $tbl = $pdo->query("SHOW TABLES LIKE 'produtores'")->fetchColumn();
    if (!$tbl) {
      throw new RuntimeException("Tabela 'produtores' não existe neste banco.");
    }

    $cols = $pdo->query("SHOW COLUMNS FROM `produtores`")->fetchAll(PDO::FETCH_ASSOC);
    $byLower = [];
    foreach ($cols as $c) {
      if (!empty($c['Field'])) $byLower[strtolower((string)$c['Field'])] = (string)$c['Field'];
    }

    $colId   = $pickCol($byLower, ['id', 'produtor_id']);
    $colNome = $pickCol($byLower, ['nome', 'produtor', 'nome_produtor', 'razao_social', 'nome_completo']);
    $colCom  = $pickCol($byLower, ['comunidade', 'localidade', 'endereco', 'bairro', 'comunidade_localidade']);
    $colTel  = $pickCol($byLower, ['telefone', 'celular', 'whatsapp', 'fone']);
    $colAtv  = $pickCol($byLower, ['ativo', 'status', 'is_ativo']);
    $colFei  = $pickCol($byLower, ['feira_id', 'feira', 'id_feira', 'tipo_feira']);

    if (!$colId || !$colNome) {
      throw new RuntimeException("A tabela 'produtores' precisa ter pelo menos colunas de ID e NOME. (Ex.: id, nome)");
    }

    $select = [];
    $select[] = "`$colId` AS id";
    $select[] = "`$colNome` AS nome";
    $select[] = $colCom ? "`$colCom` AS comunidade" : "NULL AS comunidade";
    $select[] = $colTel ? "`$colTel` AS telefone" : "NULL AS telefone";
    $select[] = $colAtv ? "`$colAtv` AS ativo" : "1 AS ativo";

    $where = [];
    $params = [];

    if ($colFei) {
      $where[] = "`$colFei` = :feira";
      $params[':feira'] = $feiraId;
    }

    if ($q !== '') {
      $likeParts = ["`$colNome` LIKE :q"];
      if ($colCom) $likeParts[] = "`$colCom` LIKE :q";
      if ($colTel) $likeParts[] = "`$colTel` LIKE :q";
      $where[] = '(' . implode(' OR ', $likeParts) . ')';
      $params[':q'] = '%' . $q . '%';
    }

    $sql = "SELECT " . implode(', ', $select) . "
            FROM `produtores`"
            . (count($where) ? " WHERE " . implode(' AND ', $where) : "")
            . " ORDER BY `$colNome` ASC";

    $stmt = $pdo->prepare($sql);

    if (isset($params[':feira'])) $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);
    if (isset($params[':q']))     $stmt->bindValue(':q', (string)$params[':q'], PDO::PARAM_STR);

    $stmt->execute();
    $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);

  } catch (Throwable $e) {
    $err = $err ?: 'Não foi possível carregar os produtores agora.';
    $errDetail = $cleanErr($e->getMessage());
  }
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
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }

    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .toolbar-card .form-control { height: 42px; }
    .toolbar-card .btn { height: 42px; }

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

    .acoes-wrap{ display:flex; flex-wrap:wrap; gap:8px; }
    .btn-xs{ padding: .25rem .5rem; font-size: .75rem; line-height: 1.2; height:auto; }
  </style>
</head>

<body>
<div class="container-scroller">

  <!-- NAVBAR (padrão) -->
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
              <p class="sig-toast__text">
                <?= h($err) ?>
                <?php if (!empty($debug) && !empty($errDetail)): ?>
                  <br><small style="opacity:.75; display:block; margin-top:4px;"><?= h($errDetail) ?></small>
                <?php endif; ?>
              </p>
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

    <!-- SIDEBAR (padrão) -->
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
            <h3 class="font-weight-bold">Produtores</h3>
            <h6 class="font-weight-normal mb-0">Cadastro de produtores rurais (feirantes). Sem caixa próprio — vendas são registradas “na fala”.</h6>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12 grid-margin stretch-card">
            <div class="card toolbar-card">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-md-6 mb-2 mb-md-0">
                    <label class="mb-1">Pesquisa</label>
                    <input type="text" class="form-control" placeholder="Pesquisar por nome, comunidade, telefone..." value="<?= h($q ?? '') ?>">
                  </div>

                  <div class="col-md-6">
                    <label class="mb-1 d-none d-md-block">&nbsp;</label>
                    <div class="d-flex flex-wrap justify-content-md-end" style="gap:8px;">
                      <button type="button" class="btn btn-primary">
                        <i class="ti-search mr-1"></i> Pesquisar
                      </button>
                      <a class="btn btn-light" href="./listaProdutor.php">
                        <i class="ti-close mr-1"></i> Limpar
                      </a>
                      <button type="button" class="btn btn-success">
                        <i class="ti-export mr-1"></i> Exportar
                      </button>
                    </div>
                    <small class="text-muted d-block mt-2">Pesquisa por URL: <b>?q=texto</b> (depois a gente liga o botão).</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Lista de Produtores</h4>
                    <p class="card-description mb-0">Mostrando <?= isset($produtores) ? (int)count($produtores) : 0 ?> registro(s).</p>
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
                        <th>Comunidade / Localidade</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th style="min-width: 210px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($produtores)): ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">
                            Nenhum produtor encontrado.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($produtores as $p): ?>
                          <?php
                            $id = (int)($p['id'] ?? 0);
                            $ativo = (int)($p['ativo'] ?? 0) === 1;
                            $badgeClass = $ativo ? 'badge-success' : 'badge-danger';
                            $badgeText  = $ativo ? 'Ativo' : 'Inativo';
                          ?>
                          <tr>
                            <td><?= $id ?></td>
                            <td><?= h($p['nome'] ?? '') ?></td>
                            <td><?= h($p['comunidade'] ?? '') ?></td>
                            <td><?= h($p['telefone'] ?? '') ?></td>
                            <td><label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label></td>
                            <td>
                              <div class="acoes-wrap">
                                <button type="button" class="btn btn-outline-primary btn-xs" disabled>
                                  <i class="ti-pencil"></i> Editar
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-xs" disabled>
                                  <i class="ti-power-off"></i> <?= $ativo ? 'Desativar' : 'Ativar' ?>
                                </button>
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
