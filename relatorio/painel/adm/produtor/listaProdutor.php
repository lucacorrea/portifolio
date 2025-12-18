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

/* Conexão (padrão do seu sistema: db(): PDO) */
require '../../../assets/php/conexao.php';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Feira padrão desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor') !== false) $FEIRA_ID = 1;

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* GET (pesquisa/paginação/export) */
$q = trim((string)($_GET['q'] ?? ''));
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;

$perPage = 15;
$offset  = ($page - 1) * $perPage;

$export = (string)($_GET['export'] ?? '') === '1';

/* Helper para montar querystring mantendo q/p */
function buildQuery(array $extra = []): string
{
  $base = [];
  if (isset($_GET['q']) && $_GET['q'] !== '') $base['q'] = (string)$_GET['q'];
  if (isset($_GET['p']) && $_GET['p'] !== '') $base['p'] = (string)$_GET['p'];

  foreach ($extra as $k => $v) {
    if ($v === null || $v === '') unset($base[$k]);
    else $base[$k] = (string)$v;
  }

  return $base ? ('?' . http_build_query($base)) : '';
}

/* AÇÕES (POST): toggle / excluir */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    header('Location: ./listaProdutor.php' . buildQuery());
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');
  $id   = (int)($_POST['id'] ?? 0);

  if ($id <= 0) {
    $_SESSION['flash_err'] = 'ID inválido.';
    header('Location: ./listaProdutor.php' . buildQuery());
    exit;
  }

  try {
    $pdo = db();

    if ($acao === 'toggle') {
      $sql = "UPDATE produtores
              SET ativo = IF(ativo=1,0,1)
              WHERE id = :id AND feira_id = :feira_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':id' => $id, ':feira_id' => $FEIRA_ID]);

      $_SESSION['flash_ok'] = 'Status do produtor atualizado!';
      header('Location: ./listaProdutor.php' . buildQuery());
      exit;
    }

    if ($acao === 'excluir') {
      $sql = "DELETE FROM produtores
              WHERE id = :id AND feira_id = :feira_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':id' => $id, ':feira_id' => $FEIRA_ID]);

      $_SESSION['flash_ok'] = 'Produtor excluído com sucesso!';
      header('Location: ./listaProdutor.php' . buildQuery(['p' => '1']));
      exit;
    }

    $_SESSION['flash_err'] = 'Ação inválida.';
    header('Location: ./listaProdutor.php' . buildQuery());
    exit;
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = 'Erro: ' . $e->getMessage();
    header('Location: ./listaProdutor.php' . buildQuery());
    exit;
  }
}

/* LISTAGEM (DB) */
$rows = [];
$total = 0;

try {
  $pdo = db();

  $where = "WHERE feira_id = :feira_id";
  $params = [':feira_id' => $FEIRA_ID];

  if ($q !== '') {
    $where .= " AND (nome LIKE :q OR contato LIKE :q OR comunidade LIKE :q)";
    $params[':q'] = '%' . $q . '%';
  }

  /* Export CSV */
  if ($export) {
    $sql = "SELECT id, nome, comunidade, contato, ativo, observacao, criado_em
            FROM produtores
            $where
            ORDER BY ativo DESC, nome ASC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produtores_feira_' . $FEIRA_ID . '.csv"');

    echo "\xEF\xBB\xBF"; // BOM UTF-8 (Excel)
    $out = fopen('php://output', 'w');

    fputcsv($out, ['ID', 'Produtor', 'Comunidade', 'Telefone', 'Status', 'Observação', 'Criado em'], ';');

    foreach ($all as $r) {
      $status = ((int)$r['ativo'] === 1) ? 'Ativo' : 'Inativo';
      fputcsv($out, [
        $r['id'] ?? '',
        $r['nome'] ?? '',
        $r['comunidade'] ?? '',
        $r['contato'] ?? '',
        $status,
        $r['observacao'] ?? '',
        $r['criado_em'] ?? '',
      ], ';');
    }

    fclose($out);
    exit;
  }

  /* Total */
  $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM produtores $where");
  $stmt->execute($params);
  $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  /* Página */
  $sql = "SELECT id, nome, comunidade, contato, ativo
          FROM produtores
          $where
          ORDER BY ativo DESC, nome ASC, id DESC
          LIMIT :lim OFFSET :off";
  $stmt = $pdo->prepare($sql);

  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', $offset, PDO::PARAM_INT);

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = 'Erro ao carregar produtores: ' . $e->getMessage();
}

$pages = (int)ceil(max(1, $total) / $perPage);
if ($page > $pages) $page = $pages;
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

    .acoes-wrap { display: flex; gap: 8px; flex-wrap: wrap; }
    .acoes-wrap form { margin: 0; }
    .btn-xs { padding: .35rem .5rem; font-size: .75rem; height: 34px; }
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

      <!-- MENU PADRÃO (Cadastros ativo + Produtores ativo) -->
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
                  <a class="nav-link" href="./adicionarProduto.php">
                    <i class="ti-plus mr-2"></i> Adicionar Produto
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./listaCategoria.php">
                    <i class="ti-layers mr-2"></i> Categorias
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./adicionarCategoria.php">
                    <i class="ti-plus mr-2"></i> Adicionar Categoria
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./listaUnidade.php">
                    <i class="ti-ruler-pencil mr-2"></i> Unidades
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./adicionarUnidade.php">
                    <i class="ti-plus mr-2"></i> Adicionar Unidade
                  </a>
                </li>

                <li class="nav-item active">
                  <a class="nav-link" href="./listaProdutor.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./adicionarProdutor.php">
                    <i class="ti-plus mr-2"></i> Adicionar Produtor
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

          <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= h($msg) ?></div>
          <?php endif; ?>
          <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?= h($err) ?></div>
          <?php endif; ?>

          <!-- PESQUISA / LIMPAR / EXPORTAR -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card toolbar-card">
                <div class="card-body">
                  <form method="get" class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                      <label class="mb-1">Pesquisa</label>
                      <input name="q" value="<?= h($q) ?>" type="text" class="form-control" placeholder="Pesquisar por nome, comunidade, telefone...">
                    </div>

                    <div class="col-md-6">
                      <label class="mb-1 d-none d-md-block">&nbsp;</label>
                      <div class="d-flex flex-wrap justify-content-md-end" style="gap:8px;">
                        <button type="submit" class="btn btn-primary">
                          <i class="ti-search mr-1"></i> Pesquisar
                        </button>

                        <a class="btn btn-light" href="./listaProdutor.php">
                          <i class="ti-close mr-1"></i> Limpar
                        </a>

                        <a class="btn btn-success" href="./listaProdutor.php<?= buildQuery(['export' => '1', 'p' => '1']) ?>">
                          <i class="ti-export mr-1"></i> Exportar
                        </a>
                      </div>
                      <small class="text-muted d-block mt-2">
                        Total: <b><?= (int)$total ?></b> produtor(es)
                      </small>
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
                      <h4 class="card-title mb-0">Lista de Produtores</h4>
                      <p class="card-description mb-0">Listando do banco (feira_id = <?= (int)$FEIRA_ID ?>).</p>
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
                          <th style="min-width: 260px;">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rows)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                              Nenhum produtor encontrado.
                            </td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($rows as $r): ?>
                            <?php
                              $id = (int)($r['id'] ?? 0);
                              $ativo = (int)($r['ativo'] ?? 0) === 1;
                              $badgeClass = $ativo ? 'badge-success' : 'badge-danger';
                              $badgeText  = $ativo ? 'Ativo' : 'Inativo';
                            ?>
                            <tr>
                              <td><?= $id ?></td>
                              <td><?= h($r['nome'] ?? '') ?></td>
                              <td><?= h($r['comunidade'] ?? '') ?></td>
                              <td><?= h($r['contato'] ?? '') ?></td>
                              <td><label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label></td>
                              <td>
                                <div class="acoes-wrap">
                                  <a href="./adicionarProdutor.php?id=<?= $id ?>" class="btn btn-outline-info btn-xs">
                                    <i class="ti-pencil"></i> Editar
                                  </a>

                                  <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="toggle">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-xs">
                                      <i class="ti-power-off"></i> <?= $ativo ? 'Inativar' : 'Ativar' ?>
                                    </button>
                                  </form>

                                  <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-xs">
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

                    <!-- Paginação simples -->
                    <?php if ($pages > 1): ?>
                      <nav class="mt-3">
                        <ul class="pagination pagination-sm mb-0">
                          <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                            <a class="page-link" href="./listaProdutor.php<?= buildQuery(['p' => (string)max(1, $page - 1)]) ?>">Anterior</a>
                          </li>

                          <?php
                            $start = max(1, $page - 2);
                            $end   = min($pages, $page + 2);
                          ?>
                          <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= ($i === $page ? 'active' : '') ?>">
                              <a class="page-link" href="./listaProdutor.php<?= buildQuery(['p' => (string)$i]) ?>"><?= $i ?></a>
                            </li>
                          <?php endfor; ?>

                          <li class="page-item <?= ($page >= $pages ? 'disabled' : '') ?>">
                            <a class="page-link" href="./listaProdutor.php<?= buildQuery(['p' => (string)min($pages, $page + 1)]) ?>">Próxima</a>
                          </li>
                        </ul>
                      </nav>
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
