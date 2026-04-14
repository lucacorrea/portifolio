<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo']);

require_once __DIR__ . '/../../../lib/util.php';

/* ===== CONEXÃO ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$empresaNome = empresa_nome_logada($pdo);

/* ===== CNPJ DA EMPRESA ===== */
$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) {
  die('Empresa não vinculada ao usuário.');
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_adicional_novo'])) {
  $_SESSION['csrf_adicional_novo'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_adicional_novo'];

/* ===== FLASH ===== */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Novo Adicional</title>

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
$menuAtivo = 'lavajato-add-adicional';
include '../../layouts/sidebar.php';
?>

<main class="main-content">

  <!-- TOAST (3,4s) -->
  <?php if ($ok || $err): ?>
    <div id="toastMsg"
      class="toast show align-items-center border-0 position-fixed top-0 end-0 m-3 shadow-lg <?= $ok ? 'bg-success' : 'bg-danger' ?>"
      role="alert" aria-live="assertive" aria-atomic="true"
      style="z-index:2000;min-width:340px;border-radius:12px;overflow:hidden;">

      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2 text-white fw-semibold">
          <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> fs-4"></i>
          <?= htmlspecialchars(
            $msg ?: ($ok
              ? 'Adicional cadastrado com sucesso!'
              : 'Falha ao cadastrar adicional.'
            ),
            ENT_QUOTES,
            'UTF-8'
          ) ?>
        </div>
        <button id="toastClose" type="button"
          class="btn-close btn-close-white me-2 m-auto"
          data-bs-dismiss="toast" aria-label="Fechar"></button>
      </div>

      <div class="progress" style="height:3px;">
        <div id="toastProgress"
          class="progress-bar <?= $ok ? 'bg-success' : 'bg-danger' ?>"
          style="width:100%"></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="position-relative iq-banner">
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand">
          <h4 class="logo-title">AutoERP</h4>
        </a>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:150px;margin-bottom:50px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-md-12">
            <h1 class="mb-0">
              <i class="bi bi-plus-circle me-1"></i> Cadastrar Adicional
            </h1>
            <p>Informe os dados do adicional do Lava Jato.</p>
          </div>
        </div>
      </div>

      <div class="iq-header-img">
        <img src="../../assets/images/dashboard/top-header.png"
          class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX"
          alt="">
      </div>
    </div>
  </div>

  <div class="container-fluid content-inner mt-n3 py-0">
    <div class="row">
      <div class="col-12">
        <div class="card" data-aos="fade-up" data-aos-delay="150">
          <div class="card-header">
            <h4 class="card-title mb-0">Dados do Adicional</h4>
          </div>

          <div class="card-body">
            <form method="post" action="../actions/adicionaisSalvar.php">
              <input type="hidden" name="op" value="adicional_novo">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

              <div class="col-md-4 mb-3">
                <label class="form-label">Ativo?</label>
                <select name="ativo" class="form-select">
                  <option value="1" selected>Sim</option>
                  <option value="0">Não</option>
                </select>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Nome do Adicional</label>
                  <input type="text" name="nome" class="form-control" required maxlength="120">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Valor Padrão</label>
                  <input type="number" name="valor" step="0.01" min="0" class="form-control" required>
                </div>
              </div>

              <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i> Salvar
                </button>
              </div>

            </form>
          </div>
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
      <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
    </div>
  </footer>

</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/core/external.min.js"></script>
<script src="../../assets/vendor/aos/dist/aos.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>

<script>
(function () {
  const toast = document.getElementById('toastMsg');
  const bar   = document.getElementById('toastProgress');
  if (!toast || !bar) return;

  const total = 3400;
  let start = Date.now();

  const timer = setInterval(() => {
    const elapsed = Date.now() - start;
    const percent = Math.max(0, 100 - (elapsed / total) * 100);
    bar.style.width = percent + '%';

    if (elapsed >= total) {
      clearInterval(timer);
      toast.remove();
    }
  }, 30);

  document.getElementById('toastClose')?.addEventListener('click', () => {
    clearInterval(timer);
    toast.remove();
  });
})();
</script>

</body>
</html>
