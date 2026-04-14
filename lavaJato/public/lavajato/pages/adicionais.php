<?php
// autoErp/public/lavajato/pages/adicionais.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

/* ===== CONEXÃO ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Conexão indisponível.');
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_adicionais'])) {
  $_SESSION['csrf_adicionais'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_adicionais'];

/* ===== UTIL ===== */
require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

/* ===== EMPRESA ===== */
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

/* ===== LISTA ADICIONAIS ===== */
$rows = [];
if (preg_match('/^\d{14}$/', $empresaCnpj)) {
  $st = $pdo->prepare("
    SELECT 
      id,
      nome,
      valor,
      ativo
    FROM adicionais_peca
    ORDER BY nome
  ");
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Adicionais Lava Jato</title>

  <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../assets/images/favicon.ico">

  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>

<?php
$menuAtivo = 'lavajato-adicionais';
include '../../layouts/sidebar.php';
?>

<main class="main-content">

  <!-- ===== TOPO ===== -->
  <div class="position-relative iq-banner">
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand">
          <h4 class="logo-title">AutoERP</h4>
        </a>

        <div class="input-group search-input">
          <span class="input-group-text">
            <i class="bi bi-search"></i>
          </span>
          <input type="search" class="form-control" placeholder="Pesquisar adicional...">
        </div>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:140px;margin-bottom:50px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-12">
            <h1 class="mb-0">Lista de Adicionais</h1>
            <p>Gerencie os adicionais do Lava Jato.</p>
          </div>
        </div>
      </div>
      <div class="iq-header-img">
        <img src="../../assets/images/dashboard/top-header.png"
             class="img-fluid w-100 h-100 animated-scaleX" alt="">
      </div>
    </div>
  </div>

  <!-- ===== CONTEÚDO ===== -->
  <div class="container-fluid content-inner mt-n3 py-0">
    <div class="card">
      <div class="card-body">

        <div class="d-flex justify-content-end mb-3">
          <a href="./adicionaisNovo.php" class="btn btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i> Novo Adicional
          </a>
        </div>

        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Valor</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>

            <?php if (!$rows): ?>
              <tr>
                <td colspan="4" class="text-center text-muted">
                  Nenhum adicional cadastrado.
                </td>
              </tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>R$ <?= number_format((float)$r['valor'], 2, ',', '.') ?></td>
                <td>
                  <span class="badge bg-<?= ((int)$r['ativo'] === 1) ? 'success' : 'secondary' ?>">
                    <?= ((int)$r['ativo'] === 1) ? 'Ativo' : 'Inativo' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap">
                  <form method="post" action="../actions/adicionaisExcluir.php"
                        onsubmit="return confirm('Excluir este adicional?');"
                        class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>

            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-body d-flex justify-content-between align-items-center">
      <div class="left-panel">
        © <script>document.write(new Date().getFullYear())</script>
        <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
      </div>
        <div class="right-panel">Desenvolvido por L&J Soluções Tecnológicas.</div>
    </div>
  </footer>

</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/core/external.min.js"></script>
<script src="../../assets/vendor/aos/dist/aos.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>
</body>
</html>
