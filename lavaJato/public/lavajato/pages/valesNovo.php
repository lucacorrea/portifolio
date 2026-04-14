<?php
// public/lavajato/pages/valesNovo.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['super_admin', 'dono', 'administrativo', 'caixa']);

// Conexão (procedural)
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

if ($perfil !== 'super_admin' && !preg_match('/^\d{14}$/', $empresaCnpj)) {
  die('Empresa não vinculada ao usuário.');
}

$empresaNome = empresa_nome_logada($pdo) ?: 'Sua empresa';

$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// CSRF
if (empty($_SESSION['csrf_vales'])) {
  $_SESSION['csrf_vales'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_vales'];

// Lavadores (para selecionar)
$lavadores = [];
try {
  $sql = "
    SELECT nome, cpf
    FROM lavadores_peca
    WHERE ativo = 1
  ";
  $params = [];

  if ($perfil !== 'super_admin') {
    $sql .= " AND empresa_cnpj = :c";
    $params[':c'] = $empresaCnpj;
  }

  $sql .= " ORDER BY nome";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $lavadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $lavadores = [];
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Novo Vale</title>

  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
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
  $menuAtivo = 'lavajato-vales';
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
              </svg>
            </span>
            <input type="search" class="form-control" placeholder="Pesquisar...">
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Novo Vale</h1>
              <p>Lançar vale (desconto imediato).</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="card">
        <div class="card-body">

          <?php if ($err): ?>
            <div class="alert alert-danger mb-3">
              <?= htmlspecialchars($msg ?: 'Ocorreu um erro.', ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php elseif ($ok): ?>
            <div class="alert alert-success mb-3">
              <?= htmlspecialchars($msg ?: 'Operação realizada com sucesso.', ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <form method="post" action="../actions/valeSalvar.php" class="row g-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="col-md-6">
              <label class="form-label">Lavador</label>
              <select class="form-select" name="lavador_cpf" required>
                <option value="">Selecione...</option>
                <?php foreach ($lavadores as $l): ?>
                  <?php
                  $cpfOpt = preg_replace('/\D+/', '', (string)($l['cpf'] ?? ''));
                  $nomeOpt = (string)($l['nome'] ?? '');
                  ?>
                  <option value="<?= htmlspecialchars($cpfOpt, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($nomeOpt . ' — ' . $cpfOpt, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Valor (R$)</label>
              <input type="number" step="0.01" min="0.01" class="form-control" name="valor" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Forma</label>
              <select class="form-select" name="forma_pagamento" required>
                <option value="dinheiro">Dinheiro</option>
                <option value="pix">Pix</option>
                <option value="cartao">Cartão</option>
                <option value="transferencia">Transferência</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Motivo (opcional)</label>
              <input type="text" maxlength="255" class="form-control" name="motivo" placeholder="Ex.: adiantamento">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
              <a class="btn btn-outline-secondary" href="./vales.php">
                <i class="bi bi-arrow-left me-1"></i> Voltar
              </a>
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle me-1"></i> Lançar Vale
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars((string)$empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
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