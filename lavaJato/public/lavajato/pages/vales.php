<?php
// public/lavajato/pages/vales.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['super_admin', 'dono', 'administrativo', 'caixa']);

// conexão (procedural) - mantém seu padrão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!($pdo instanceof PDO)) die('Conexão indisponível.');

// controller (usa o MESMO $pdo desta página)
require_once __DIR__ . '/../controllers/valesController.php';

function h($v): string
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function money($n): string
{
  return 'R$ ' . number_format((float)$n, 2, ',', '.');
}
function cpf_fmt(string $cpf): string
{
  $cpf = preg_replace('/\D+/', '', $cpf);
  if (strlen($cpf) !== 11) return $cpf;
  return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}
function dia_fmt(string $ymd): string
{
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return $ymd;
  return date('d/m/Y', strtotime($ymd));
}

$empresaNome = (string)($vm['empresaNome'] ?? 'Sua empresa');
$porDia = $vm['porDia'] ?? [];

/*
  IMPORTANTE:
  - O controller NÃO deve abrir outra conexão.
  - Ele deve usar o $pdo já definido aqui.
*/

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Vales</title>

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
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Vales</h1>
              <p>Separado por data (somando por CPF em cada dia).</p>
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

          <?php if (!empty($vm['err'])): ?>
            <div class="alert alert-danger mb-3"><?= h($vm['msg'] ?? 'Ocorreu um erro.') ?></div>
          <?php elseif (!empty($vm['ok'])): ?>
            <div class="alert alert-success mb-3"><?= h($vm['msg'] ?? 'Operação realizada com sucesso.') ?></div>
          <?php endif; ?>

          <form class="row g-2 mb-3" method="get">
            <div class="col-md-3">
              <label class="form-label">De</label>
              <input type="date" name="de" class="form-control" value="<?= h($vm['de'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Até</label>
              <input type="date" name="ate" class="form-control" value="<?= h($vm['ate'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">CPF</label>
              <input type="text" name="cpf" class="form-control" value="<?= h($vm['cpf'] ?? '') ?>" placeholder="Somente números">
            </div>
            <div class="col-md-3 d-flex align-items-end justify-content-end gap-2">
              <a class="btn btn-outline-secondary" href="./vales.php"><i class="bi bi-x-circle me-1"></i> Limpar</a>
              <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Filtrar</button>
              <a class="btn btn-outline-secondary" href="./valesNovo.php"><i class="bi bi-plus-circle me-1"></i> Novo</a>
            </div>
          </form>

          <?php if (empty($porDia)): ?>
            <div class="text-center text-muted py-4">Nenhum vale encontrado.</div>
          <?php else: ?>

            <?php foreach ($porDia as $dia => $lista): ?>
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h4 class="mb-0">DÉBITOS — <?= h(dia_fmt($dia)) ?></h4>
                </div>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th class="text-end">Total do dia</th>
                        <th>Último lançamento</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $totalGeralDia = 0.0;
                      foreach ($lista as $r) {
                        $totalGeralDia += (float)($r['total_dia'] ?? 0);
                      }
                      ?>

                      <?php foreach ($lista as $r): ?>
                        <tr>
                          <td><?= h($r['lavador_nome'] ?? '-') ?></td>
                          <td><?= h(cpf_fmt((string)($r['cpf'] ?? ''))) ?></td>

                          <td class="text-end"><?= money($r['total_dia'] ?? 0) ?></td>
                          <td><?= h((string)($r['ultimo_em'] ?? '-')) ?></td>
                        </tr>
                      <?php endforeach; ?>

                      <tr>
                        <td colspan="2" class="text-end fw-bold">TOTAL DO DIA:</td>
                        <td class="text-end fw-bold"><?= money($totalGeralDia) ?></td>
                        <td></td>
                      </tr>

                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>

          <?php endif; ?>

        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= h($empresaNome) ?></div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
</body>

</html>