<?php
declare(strict_types=1);
session_start();

/* Login */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../index.php');
  exit;
}

/* ADMIN */
if (!in_array('ADMIN', $_SESSION['perfis'] ?? [], true)) {
  header('Location: ../operador/index.php');
  exit;
}

$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';

require_once '../../assets/php/conexao.php';
$pdo = db();

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$TABELA = 'comunidades';

$msgErro = '';
$msgSucesso = '';
$comunidades = [];

/* =========================
   AÇÕES (toggle / excluir)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $msgErro = 'Token de segurança inválido.';
  } else {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
      $msgErro = 'ID inválido.';
    } else {
      try {
        if ($acao === 'toggle') {
          $st = $pdo->prepare("UPDATE {$TABELA} SET ativo = IF(ativo=1,0,1) WHERE id = :id");
          $st->execute([':id' => $id]);
          $msgSucesso = 'Status atualizado com sucesso.';
        } elseif ($acao === 'excluir') {
          $st = $pdo->prepare("DELETE FROM {$TABELA} WHERE id = :id");
          $st->execute([':id' => $id]);
          $msgSucesso = 'Registro excluído com sucesso.';
        } else {
          $msgErro = 'Ação inválida.';
        }
      } catch (Throwable $e) {
        error_log("Erro em localidades.php (acao): " . $e->getMessage());
        $msgErro = 'Erro ao executar ação. Verifique o error_log.';
      }
    }
  }
}

/* =========================
   PAGINAÇÃO (8 por página)
========================= */
$porPagina = 8;
$pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina - 1) * $porPagina;

$totalRegistros = 0;
$totalPaginas = 1;

/* =========================
   LISTAR (JOIN feiras + LIMIT)
========================= */
try {
  $totalRegistros = (int)$pdo->query("SELECT COUNT(*) FROM {$TABELA}")->fetchColumn();
  $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

  if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $porPagina;
  }

  $sql = "
    SELECT
      c.id,
      c.feira_id,
      c.nome,
      c.ativo,
      c.observacao,
      c.criado_em,
      c.atualizado_em,
      f.nome AS feira_nome
    FROM {$TABELA} c
    LEFT JOIN feiras f ON f.id = c.feira_id
    ORDER BY c.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  $st->bindValue(':lim', $porPagina, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();

  $comunidades = $st->fetchAll();
} catch (Throwable $e) {
  error_log("Erro ao listar comunidades: " . $e->getMessage());
  $msgErro = 'Erro ao carregar a lista.';
  $comunidades = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Localidades</title>

  <link rel="stylesheet" href="../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href="../../js/select.dataTables.min.css">

  <link rel="stylesheet" href="../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../images/3.png" />

  <style>
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }
    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }
    .form-control { height: 42px; }
    .form-group label { font-weight: 600; }
  </style>
</head>

<body>
<div class="container-scroller">

  <!-- NAVBAR -->
  <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
      <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
      <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../images/3.png" alt="logo" /></a>
    </div>

    <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
      <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
        <span class="icon-menu"></span>
      </button>

      <ul class="navbar-nav navbar-nav-right">
        <li class="nav-item nav-profile dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
            <i class="ti-user"></i>
            <span class="ml-1"><?= h($nomeTopo) ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
            <a class="dropdown-item" href="../../controle/auth/logout.php">
              <i class="ti-power-off text-primary"></i> Sair
            </a>
          </div>
        </li>
      </ul>

      <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
        <span class="icon-menu"></span>
      </button>
    </div>
  </nav>

  <div class="container-fluid page-body-wrapper">

    <!-- SIDEBAR (mantenha como o seu; deixei mínimo) -->
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">
        <li class="nav-item">
          <a class="nav-link" href="./index.php">
            <i class="icon-grid menu-icon"></i>
            <span class="menu-title">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./localidades.php">
            <i class="ti-map menu-icon"></i>
            <span class="menu-title">Localidades</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="main-panel">
      <div class="content-wrapper">

        <div class="row">
          <div class="col-12 mb-3">
            <h3 class="font-weight-bold">Localidades</h3>
            <h6 class="font-weight-normal mb-0">Comunidades (feira 1/2) e Bairros (feira 3).</h6>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Lista de Localidades</h4>
                    <p class="card-description mb-0">
                      Total: <?= (int)$totalRegistros ?> registro(s) — Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?>.
                    </p>
                  </div>

                  <a href="./adicionarLocalidade.php" class="btn btn-primary btn-sm mt-2 mt-md-0">
                    <i class="ti-plus"></i> Adicionar
                  </a>
                </div>

                <?php if (!empty($msgErro)): ?>
                  <div class="alert alert-danger mt-3 mb-0"><?= h($msgErro) ?></div>
                <?php endif; ?>

                <?php if (!empty($msgSucesso)): ?>
                  <div class="alert alert-success mt-3 mb-0"><?= h($msgSucesso) ?></div>
                <?php endif; ?>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th style="width: 90px;">ID</th>
                        <th>Nome</th>
                        <th style="width: 150px;">Tipo</th>
                        <th style="width: 220px;">Feira</th>
                        <th style="width: 160px;">Status</th>
                        <th style="min-width: 260px;">Ações</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php if (empty($comunidades)): ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($comunidades as $l): ?>
                          <?php
                            $id = (int)($l['id'] ?? 0);
                            $f  = (int)($l['feira_id'] ?? 0);

                            $ativoBool  = (int)($l['ativo'] ?? 0) === 1;
                            $badgeClass = $ativoBool ? 'badge-success' : 'badge-danger';
                            $badgeText  = $ativoBool ? 'Ativo' : 'Inativo';

                            $tipoLabel = ($f === 3) ? 'Bairro' : 'Comunidade';
                            $obs = trim((string)($l['observacao'] ?? ''));

                            $feiraNome = (string)($l['feira_nome'] ?? '');
                            if ($feiraNome === '') $feiraNome = 'Feira ' . $f;
                          ?>

                          <tr>
                            <td><?= $id ?></td>

                            <td>
                              <div class="font-weight-bold"><?= h($l['nome'] ?? '') ?></div>
                              <?php if ($obs !== ''): ?>
                                <small class="text-muted"><?= h($obs) ?></small>
                              <?php endif; ?>
                            </td>

                            <td><?= h($tipoLabel) ?></td>
                            <td><?= h($feiraNome) ?></td>

                            <td>
                              <label class="badge <?= $badgeClass ?>"><?= $badgeText ?></label>
                            </td>

                            <td>
                              <div class="acoes-wrap" style="display:flex; gap:8px; flex-wrap:wrap;">

                                <form method="post" class="m-0">
                                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                  <input type="hidden" name="acao" value="toggle">
                                  <input type="hidden" name="id" value="<?= $id ?>">
                                  <button type="submit" class="btn btn-outline-warning btn-xs"
                                    onclick="return confirm('Deseja <?= $ativoBool ? 'DESATIVAR' : 'ATIVAR' ?> este registro?');">
                                    <i class="ti-power-off"></i> <?= $ativoBool ? 'Desativar' : 'Ativar' ?>
                                  </button>
                                </form>

                                <form method="post" class="m-0">
                                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                  <input type="hidden" name="acao" value="excluir">
                                  <input type="hidden" name="id" value="<?= $id ?>">
                                  <button type="submit" class="btn btn-outline-danger btn-xs"
                                    onclick="return confirm('Tem certeza que deseja EXCLUIR este registro?');">
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

                <!-- PAGINAÇÃO -->
                <?php if ($totalPaginas > 1): ?>
                  <?php
                    $qs = $_GET;
                    unset($qs['p']);
                    $base = '?' . http_build_query($qs);
                    $base = ($base === '?') ? '?' : ($base . '&');

                    $janela = 2;
                    $inicio = max(1, $pagina - $janela);
                    $fim = min($totalPaginas, $pagina + $janela);
                  ?>

                  <div class="d-flex justify-content-between align-items-center flex-wrap mt-3" style="gap:10px;">
                    <div class="text-muted">
                      Mostrando <?= count($comunidades) ?> nesta página (<?= (int)$porPagina ?> por página).
                    </div>

                    <nav aria-label="Paginação">
                      <ul class="pagination mb-0">

                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                          <a class="page-link" href="<?= $base ?>p=1">«</a>
                        </li>

                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                          <a class="page-link" href="<?= $base ?>p=<?= max(1, $pagina - 1) ?>">Anterior</a>
                        </li>

                        <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                          <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $base ?>p=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                          <a class="page-link" href="<?= $base ?>p=<?= min($totalPaginas, $pagina + 1) ?>">Próxima</a>
                        </li>

                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                          <a class="page-link" href="<?= $base ?>p=<?= $totalPaginas ?>">»</a>
                        </li>

                      </ul>
                    </nav>
                  </div>
                <?php endif; ?>

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

<script src="../../vendors/js/vendor.bundle.base.js"></script>
<script src="../../js/off-canvas.js"></script>
<script src="../../js/hoverable-collapse.js"></script>
<script src="../../js/template.js"></script>
<script src="../../js/hoverable-collapse.js"></script>
<script src="../../js/template.js"></script>
<script src="../../js/settings.js"></script>
<script src="../../js/todolist.js"></script>
</body>
</html>
