<?php
// autoErp/public/lavajato/pages/lavagens.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ==== Conexão ==== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) {
  require_once $pathCon;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

/* ==== Controller semanal real ==== */
require_once __DIR__ . '/../controllers/lavagensSemanaController.php';

/* ==== Filtros ==== */
$range = (string)($_GET['range'] ?? '365'); // 30|90|180|365
$q     = trim((string)($_GET['q'] ?? ''));

try {
  if (!function_exists('lavagens_por_semana_viewmodel')) {
    throw new RuntimeException('Função lavagens_por_semana_viewmodel() não encontrada.');
  }

  $vm = lavagens_por_semana_viewmodel($pdo, [
    'range' => $range,
    'q'     => $q,
  ]);
} catch (Throwable $e) {
  $vm = [
    'ok' => false,
    'err' => true,
    'msg' => 'Erro: ' . $e->getMessage(),
    'semanas' => [],
    'resumo' => [
      'qtd'       => 0,
      'total'     => 0.0,
      'lavadores' => 0,
      'semanas'   => 0,
    ],
    'range' => $range,
    'q' => $q,
  ];
}

/* ==== Helpers ==== */
function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

$btn = function (string $r, string $label) use ($range, $q) {
  $active = ($range === $r) ? 'active' : '';
  $qs = http_build_query(['range' => $r, 'q' => $q]);
  return '<a href="?' . $qs . '" class="btn btn-sm btn-outline-primary ' . $active . '">' . $label . '</a>';
};

$currentUrl = function () use ($range) {
  return 'lavagens.php?range=' . urlencode($range);
};
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavagens por Semana</title>

  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    .table thead th {
      white-space: nowrap;
    }

    .search-input-compact {
      max-width: 360px;
    }

    .stat {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .55rem .75rem;
      border-radius: 999px;
      background: #f3f6fb;
      border: 1px solid #e8eef7;
      font-weight: 600;
    }

    .stat i {
      opacity: .9;
    }

    .card-soft {
      border: 1px solid #edf1f7;
      box-shadow: 0 8px 24px rgba(18, 38, 63, .04);
    }
  </style>
</head>

<body>
  <?php
  $menuAtivo = 'lavajato-lavagens';
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

      <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <h1 class="mb-0">Lavagens por Semana</h1>
          <p>Selecione uma semana real de 7 dias para visualizar lavadores, resumo financeiro e vales.</p>

          <?php if (!empty($vm['msg'])): ?>
            <div class="mt-2">
              <div class="alert alert-<?= !empty($vm['err']) ? 'danger' : 'success' ?> py-2 mb-0">
                <?= h((string)$vm['msg']) ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="row">
        <div class="col-12">

          <div class="card card-soft">
            <div class="card-header">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                  <h4 class="card-title mb-1">Resumo semanal</h4>
                  <div class="text-muted small">
                    Escolha uma semana real para abrir o detalhamento dos lavadores.
                  </div>
                </div>
              </div>
            </div>

            <div class="card-body">

              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div class="d-flex flex-wrap gap-2">
                  <?= $btn('30', '30 dias') ?>
                  <?= $btn('90', '90 dias') ?>
                  <?= $btn('180', '180 dias') ?>
                  <?= $btn('365', '365 dias') ?>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <span class="stat">
                    <i class="bi bi-calendar-week"></i>
                    Semanas: <strong><?= (int)($vm['resumo']['semanas'] ?? 0) ?></strong>
                  </span>
                  <span class="stat">
                    <i class="bi bi-cart-check"></i>
                    Lavagens: <strong><?= (int)($vm['resumo']['qtd'] ?? 0) ?></strong>
                  </span>
                  <span class="stat">
                    <i class="bi bi-people"></i>
                    Lavadores: <strong><?= (int)($vm['resumo']['lavadores'] ?? 0) ?></strong>
                  </span>
                  <span class="stat">
                    <i class="bi bi-cash-coin"></i>
                    Total: <strong><?= money((float)($vm['resumo']['total'] ?? 0)) ?></strong>
                  </span>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Semana</th>
                      <th class="text-center" style="width: 140px;">Lavagens</th>
                      <th class="text-center" style="width: 140px;">Lavadores</th>
                      <th class="text-end" style="width: 160px;">Faturado</th>
                      <th class="text-end" style="width: 140px;">Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($vm['semanas'])): ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                          Nenhum resultado encontrado.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($vm['semanas'] as $semana): ?>
                        <?php
                        $weekArgs = [
                          'week_ref' => (string)$semana['week_ref'],
                        ];
                        if ($q !== '') {
                          $weekArgs['q'] = $q;
                        }
                        $weekUrl = 'lavagensSemana.php?' . http_build_query($weekArgs);
                        ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?= h((string)$semana['label']) ?></div>
                            <div class="small text-muted">
                              Início: <?= h((string)$semana['ini']) ?> • Fim: <?= h((string)$semana['fim']) ?>
                            </div>
                          </td>
                          <td class="text-center"><?= (int)($semana['qtd'] ?? 0) ?></td>
                          <td class="text-center"><?= (int)($semana['lavadores'] ?? 0) ?></td>
                          <td class="text-end"><?= money((float)($semana['total'] ?? 0)) ?></td>
                          <td class="text-end">
                            <a href="<?= h($weekUrl) ?>" class="btn btn-sm btn-outline-primary">
                              <i class="bi bi-eye"></i> Ver semana
                            </a>
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