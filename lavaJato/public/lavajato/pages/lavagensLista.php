<?php
// autoErp/public/lavajato/pages/lavagens.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ==== Conexão ==== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

/* ==== Controller ==== */
require_once __DIR__ . '/../controllers/listaLavagens.php';

function h(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* CSRF (para cancelar) */
if (empty($_SESSION['csrf_lavagens'])) {
  $_SESSION['csrf_lavagens'] = bin2hex(random_bytes(32));
}
$csrfLavagens = (string)$_SESSION['csrf_lavagens'];

/** Decide se deve aplicar busca (pra evitar carga em 1 letra) */
function should_search(string $q): bool
{
  if ($q === '') return true;
  if (preg_match('/^\d+$/', $q)) return true; // id/cpf numérico
  return mb_strlen($q) >= 2;                  // texto: mínimo 2
}

function render_tbody(array $rows, string $periodLabel = 'Hoje'): string
{
  if (empty($rows)) {
    return '
      <tr>
        <td colspan="9" class="text-center text-muted py-4">
          Nenhuma lavagem encontrada para ' . h($periodLabel) . '.
        </td>
      </tr>
    ';
  }

  $html = '';
  foreach ($rows as $l) {
    $id     = (int)($l['id'] ?? 0);

    $placa  = h((string)($l['placa'] ?? '-'));
    $modelo = h((string)($l['modelo'] ?? '-'));
    $cor    = h((string)($l['cor'] ?? '-'));
    $cat    = h((string)($l['categoria_nome'] ?? '-'));
    $lavadorNome = h((string)($l['lavador_nome'] ?? ($l['lavador_cpf'] ?? '-')));

    $valorNum = (float)($l['valor'] ?? 0);
    $valorFmt = number_format($valorNum, 2, ',', '.');

    $status = (string)($l['status'] ?? '');
    $statusLower = strtolower($status);
    $statusLabel = h(ucfirst($status ?: '-'));

    $badge =
      ($statusLower === 'aberta') ? 'warning'
      : (($statusLower === 'lavando') ? 'info'
        : (($statusLower === 'concluida') ? 'success'
          : 'secondary'));

    $hora = '-';
    $tsHora = $l['checkin_at'] ?? $l['criado_em'] ?? '';
    if (!empty($tsHora)) {
      try {
        $dt = new DateTime((string)$tsHora);
        $hora = $dt->format('H:i');
      } catch (Throwable $e) {
      }
    }

    $html .= '
      <tr>
        <td class="text-muted small">
          #' . $id . '
          <div class="small text-muted">' . h($hora) . '</div>
        </td>
        <td class="fw-semibold">' . $placa . '</td>
        <td>' . $modelo . '</td>
        <td>' . $cor . '</td>
        <td>' . $cat . '</td>
        <td>' . $lavadorNome . '</td>

        <td class="text-end">R$ ' . $valorFmt . '</td>
        <td class="text-end">
          <button
            type="button"
            class="btn btn-sm btn-info btn-ver"
            data-id="' . $id . '"
            title="Ver"
          >
            <i class="bi bi-eye"></i>
          </button>
    ';

    // Finalizar só se aberta
    if (($l['status'] ?? '') === 'aberta') {
      $html .= '
<form method="POST" action="../actions/lavagensFinalizar.php" style="display:inline;">
  <input type="hidden" name="id" value="' . $id . '">
  <button type="submit" class="btn btn-sm btn-success" title="Finalizar"
    onclick="this.disabled=true; this.form.submit();">
    <i class="bi bi-check-circle"></i>
  </button>
</form>
';
    }

    // Cancelar (aberta ou lavando)
    if (in_array($statusLower, ['aberta', 'lavando'], true)) {
      $html .= '
          <button
            type="button"
            class="btn btn-sm btn-danger btn-cancelar"
            data-id="' . $id . '"
            data-placa="' . $placa . '"
            data-modelo="' . $modelo . '"
            data-cor="' . $cor . '"
            title="Cancelar"
          >
            <i class="bi bi-x-circle"></i>
          </button>
      ';
    }

    $html .= '
        </td>
      </tr>
    ';
  }

  return $html;
}

function render_pagination(int $page, bool $hasPrev, bool $hasNext): string
{
  if (!$hasPrev && !$hasNext) return '';

  $prev = max(1, $page - 1);
  $next = $page + 1;

  $html = '<nav aria-label="Paginação" class="mt-3">
    <ul class="pagination justify-content-end mb-0">';

  $html .= '<li class="page-item ' . (!$hasPrev ? 'disabled' : '') . '">
    <a class="page-link" href="#" data-page="' . $prev . '">Anterior</a>
  </li>';

  $html .= '<li class="page-item disabled">
    <span class="page-link">Página ' . $page . '</span>
  </li>';

  $html .= '<li class="page-item ' . (!$hasNext ? 'disabled' : '') . '">
    <a class="page-link" href="#" data-page="' . $next . '">Próxima</a>
  </li>';

  $html .= '</ul></nav>';

  return $html;
}

function render_resumo(array $vm): string
{
  $qtd = (int)($vm['resumo']['qtd'] ?? 0);
  $total = (float)($vm['resumo']['total'] ?? 0);
  return 'Nesta página: <strong>' . $qtd . '</strong> • Total: <strong>R$ ' .
    number_format($total, 2, ',', '.') . '</strong>';
}

/* ================== params ================== */
$q = trim((string)($_GET['q'] ?? ''));
$p = (int)($_GET['p'] ?? 1);
if ($p < 1) $p = 1;

$perPage = (int)($_GET['pp'] ?? 10);
if ($perPage < 1) $perPage = 10;
if ($perPage > 100) $perPage = 100;

// ✅ período (hoje/semana/mes/ano/todos)
$period = strtolower(trim((string)($_GET['period'] ?? 'hoje')));
if (!in_array($period, ['hoje', 'semana', 'mes', 'ano', 'todos'], true)) {
  $period = 'hoje';
}

/* ================== vm ================== */
try {
  if (!function_exists('lavagens_abertas')) {
    throw new RuntimeException('Função lavagens_abertas() não encontrada.');
  }

  $qForQuery = should_search($q) ? $q : '';

  $vm = lavagens_abertas($pdo, [
    'q' => $qForQuery,
    'page' => $p,
    'per_page' => $perPage,
    'period' => $period,
  ]);
} catch (Throwable $e) {
  $vm = [
    'ok' => false,
    'err' => true,
    'msg' => 'Erro: ' . $e->getMessage(),
    'dados' => [],
    'resumo' => ['qtd' => 0, 'total' => 0],
    'paginacao' => ['page' => 1, 'has_next' => false, 'has_prev' => false, 'per_page' => $perPage]
  ];
}

$rows = $vm['dados'] ?? [];
$pag  = $vm['paginacao'] ?? ['page' => 1, 'has_next' => false, 'has_prev' => false, 'per_page' => $perPage];

$periodLabel = (string)($vm['period_label'] ?? (
  $period === 'semana' ? 'Semana'
  : ($period === 'mes' ? 'Mês'
    : ($period === 'ano' ? 'Ano'
      : ($period === 'todos' ? 'Todos os períodos' : 'Hoje')))
));

/* Notice: se digitou < 2 letras */
$notice = '';
if (isset($_GET['q'])) {
  $qq = trim((string)$_GET['q']);
  if ($qq !== '' && !should_search($qq)) $notice = 'Digite pelo menos 2 letras para buscar.';
}

/* ================== PARTIAL HTML (AJAX) ================== */
if (isset($_GET['partial'])) {
  header('Content-Type: text/html; charset=utf-8');

  if ($q !== '' && !should_search($q)) {
    $vm = ['resumo' => ['qtd' => 0, 'total' => 0]];
    $rows = [];
    $pag = ['page' => 1, 'has_prev' => false, 'has_next' => false];
    $notice = 'Digite pelo menos 2 letras para buscar.';
  }

  echo '<div id="partialRoot">';

  echo '<div id="noticeText">' . h($notice) . '</div>';
  echo '<div id="resumoHtml">' . render_resumo($vm) . '</div>';
  echo '<div id="cardsData" data-qtd="' . (int)($vm['resumo']['qtd'] ?? 0) . '" data-total="' .
    h(number_format((float)($vm['resumo']['total'] ?? 0), 2, ',', '.')) . '"></div>';

  echo '<div id="cardBodyHtml">';
  echo '
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle table-striped mb-0">
          <thead>
            <tr>
              <th style="width:90px">#</th>
              <th>Nome Cliente</th>
              <th>Modelo</th>
              <th>Cor</th>
              <th>Categoria</th>
              <th>Lavador</th>
              <th class="text-end">Valor</th>
              <th class="text-center">Status</th>
              <th class="text-end">Ação</th>
            </tr>
          </thead>
          <tbody id="tbody">
            ' . render_tbody($rows, $periodLabel) . '
          </tbody>
        </table>
      </div>

      <div id="paginationWrap">
        ' . render_pagination(
    (int)($pag['page'] ?? 1),
    !empty($pag['has_prev']),
    !empty($pag['has_next'])
  ) . '
      </div>
    </div>
  ';
  echo '</div>'; // #cardBodyHtml

  echo '</div>'; // #partialRoot
  exit;
}

/* ================== tela normal ================== */
$qtdHoje = (int)($vm['resumo']['qtd'] ?? 0);
$totalHoje = (float)($vm['resumo']['total'] ?? 0);
$emBusca = ($q !== '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavagens</title>
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

  <style>
    .table thead th {
      white-space: nowrap;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    iframe.lavagem-frame {
      width: 100%;
      height: 70vh;
      border: 0;
    }

    /* ✅ botões período */
    .period-buttons .btn {
      min-width: 90px;
      border-radius: 10px;
    }

    /* ✅ quando selecionado: VERDE */
    .period-buttons .btn.is-active {
      background: #198754 !important;
      /* bootstrap success */
      border-color: #198754 !important;
      color: #fff !important;
    }

    @media (max-width: 576px) {
      .period-buttons {
        justify-content: center;
      }
    }

    /* TOAST HOSTINGER STYLE */
    .toast-custom {
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 9999;

      display: flex;
      align-items: center;
      gap: 12px;

      padding: 14px 18px;
      border-radius: 12px;

      font-size: 14px;
      font-weight: 500;

      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);

      opacity: 0;
      transform: translateY(-20px) scale(0.95);
      transition: all 0.4s ease;

      backdrop-filter: blur(6px);
    }

    /* sucesso */
    .toast-custom.success {
      background: linear-gradient(135deg, #16a34a, #22c55e);
      color: #fff;
    }

    /* erro */
    .toast-custom.error {
      background: linear-gradient(135deg, #dc2626, #ef4444);
      color: #fff;
    }

    .toast-icon {
      font-size: 18px;
    }

    .toast-text {
      line-height: 1.4;
    }

    /* ativo */
    .toast-custom.show {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  </style>
</head>

<body>
  <?php
  $menuAtivo = 'lavagemRapidaLista';
  include __DIR__ . '/../../layouts/sidebar.php';
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
            <input id="q" type="search" class="form-control" placeholder="Buscar (ID, placa, CPF lavador, modelo)..." value="<?= h($q) ?>" autocomplete="off">
          </div>
        </div>
      </nav>

      <?php
      $flashOk  = $_SESSION['flash_ok'] ?? '';
      $flashErr = $_SESSION['flash_err'] ?? '';
      unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
      ?>

      <div class="iq-navbar-header" style="height: 170px;">
        <div class="container-fluid iq-container">

          <?php if ($flashOk || $flashErr): ?>
            <div id="toast-msg" class="toast-custom <?= $flashErr ? 'error' : 'success' ?>">
              <div class="toast-icon">
                <?= $flashErr ? '⚠️' : '✔️' ?>
              </div>
              <div class="toast-text">
                <?= h($flashErr ?: $flashOk) ?>
              </div>
            </div>
          <?php endif; ?>


          <div class="row">
            <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h1>Lavagens</h1>
                  <p>Lavagens em aberto (abertas / lavando) com filtro por período, busca automática e paginação.</p>
                </div>
              </div>
              <?php if (!empty($vm['msg'])): ?>
                <div class="alert alert-<?= !empty($vm['err']) ? 'danger' : 'success' ?> py-2 mt-2 mb-0">
                  <?= h((string)$vm['msg']) ?>
                </div>
              <?php endif; ?>
              <div id="notice" class="mt-2 small text-warning"><?= h($notice) ?></div>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n4 py-0">

      <div class="row g-3">
        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1"><?= $emBusca ? 'Resultados (página)' : 'Lavagens (página)' ?></p>
              <h4 class="mb-0" id="cardQtd"><?= (int)$qtdHoje ?></h4>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Total (página)</p>
              <h4 class="mb-0" id="cardTotal">R$ <?= number_format($totalHoje, 2, ',', '.') ?></h4>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Itens por página</p>
              <h4 class="mb-0">
                <select id="pp" class="form-select form-select-sm" style="max-width: 140px;">
                  <?php foreach ([10, 20, 30, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($perPage === $opt ? 'selected' : '') ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </h4>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Ações</p>
              <button id="clearBtn" class="btn btn-outline-secondary btn-sm" type="button" <?= ($q === '' ? 'disabled' : '') ?>>
                Limpar busca
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-12">
          <div class="card" data-aos="fade-up" data-aos-delay="150">

            <div class="card-header">

              <!-- 🔹 LINHA 1: Título + Resumo -->
              <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h4 class="card-title mb-0" id="tituloPeriodo">
                  Lavagens em aberto — <?= h($periodLabel) ?>
                </h4>

                <div class="small text-muted mt-2 mt-md-0" id="resumo">
                  <?= render_resumo($vm) ?>
                </div>
              </div>

              <!-- 🔹 LINHA 2: Botões do período -->
              <div class="d-flex gap-3 mt-3 period-buttons flex-wrap">
                <button type="button"
                  class="btn btn-outline-primary btn-sm js-period <?= $period === 'hoje' ? 'is-active' : '' ?>"
                  data-period="hoje">Hoje</button>

                <button type="button"
                  class="btn btn-outline-primary btn-sm js-period <?= $period === 'semana' ? 'is-active' : '' ?>"
                  data-period="semana">Semana</button>

                <button type="button"
                  class="btn btn-outline-primary btn-sm js-period <?= $period === 'mes' ? 'is-active' : '' ?>"
                  data-period="mes">Mês</button>

                <button type="button"
                  class="btn btn-outline-primary btn-sm js-period <?= $period === 'ano' ? 'is-active' : '' ?>"
                  data-period="ano">Ano</button>

                <button type="button"
                  class="btn btn-outline-primary btn-sm js-period <?= $period === 'todos' ? 'is-active' : '' ?>"
                  data-period="todos">Todos</button>

                <input type="hidden" id="period" value="<?= h($period) ?>">
              </div>

            </div>

            <!-- ✅ WRAPPER para substituir tudo no AJAX -->
            <div id="cardBodyWrap">
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-middle table-striped mb-0">
                    <thead>
                      <tr>
                        <th style="width:90px">#</th>
                        <th>Nome Cliente</th>
                        <th>Modelo</th>
                        <th>Cor</th>
                        <th>Categoria</th>
                        <th>Lavador</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Ação</th>
                      </tr>
                    </thead>

                    <tbody id="tbody">
                      <?= render_tbody($rows, $periodLabel) ?>
                    </tbody>
                  </table>
                </div>

                <div id="paginationWrap">
                  <?= render_pagination((int)($pag['page'] ?? 1), !empty($pag['has_prev']), !empty($pag['has_next'])) ?>
                </div>

              </div>
            </div>
            <!-- ✅ /WRAPPER -->

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

  <!-- MODAL: VER LAVAGEM -->
  <div class="modal fade" id="modalVerLavagem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes da lavagem</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-0">
          <iframe id="verLavagemFrame" class="lavagem-frame" src="about:blank"></iframe>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: FINALIZAR -->


  <!-- MODAL: CANCELAR -->
  <div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="../actions/lavagensCancelar.php" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cancelar lavagem</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= h($csrfLavagens) ?>">
          <input type="hidden" name="id" id="cancelarLavagemId">

          <div class="mb-2">
            <div class="small text-muted">Veículo</div>
            <div class="fw-semibold" id="cancelarVeiculo">-</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Motivo (opcional)</label>
            <textarea name="motivo" id="cancelarMotivo" class="form-control" rows="3"
              placeholder="Ex.: cliente desistiu, erro de lançamento, etc."></textarea>
          </div>

          <div class="alert alert-warning py-2 mb-0">
            Essa ação marca a lavagem como <strong>cancelada</strong> e ela sai da lista.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
          <button type="submit" class="btn btn-danger">Cancelar lavagem</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    const toast = document.getElementById('toast-msg');

    if (toast) {
      // anima entrada
      setTimeout(() => {
        toast.classList.add('show');
      }, 100);

      // sair após 5 segundos
      setTimeout(() => {
        toast.classList.remove('show');

        // remove do DOM depois da animação
        setTimeout(() => {
          toast.remove();
        }, 400);

      }, 5000);
    }
  </script>
  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>

  <script>
    function reinitLayoutAfterAjax() {
      document.body.offsetHeight;

      if (window.AOS && typeof window.AOS.refreshHard === 'function') {
        window.AOS.refreshHard();
      } else if (window.AOS && typeof window.AOS.refresh === 'function') {
        window.AOS.refresh();
      }

      window.dispatchEvent(new Event('resize'));
    }

    (function() {
      const qEl = document.getElementById('q');
      const ppEl = document.getElementById('pp');
      const periodEl = document.getElementById('period'); // hidden
      const tituloPeriodoEl = document.getElementById('tituloPeriodo');

      const resumoEl = document.getElementById('resumo');
      const clearBtn = document.getElementById('clearBtn');
      const noticeEl = document.getElementById('notice');
      const cardQtd = document.getElementById('cardQtd');
      const cardTotal = document.getElementById('cardTotal');

      const cardBodyWrap = document.getElementById('cardBodyWrap');

      const modalVerLavagemEl = document.getElementById('modalVerLavagem');
      const modalFinalizarEl = document.getElementById('modalFinalizar');
      const modalCancelarEl = document.getElementById('modalCancelar');

      const modalVerLavagem =
        (typeof bootstrap !== 'undefined' && modalVerLavagemEl) ? new bootstrap.Modal(modalVerLavagemEl) : null;
      const modalFinalizar =
        (typeof bootstrap !== 'undefined' && modalFinalizarEl) ? new bootstrap.Modal(modalFinalizarEl) : null;
      const modalCancelar =
        (typeof bootstrap !== 'undefined' && modalCancelarEl) ? new bootstrap.Modal(modalCancelarEl) : null;

      const verLavagemFrame = document.getElementById('verLavagemFrame');

      const finalizarLavagemId = document.getElementById('finalizarLavagemId');
      const finalizarValor = document.getElementById('finalizarValor');
      const finalizarVeiculo = document.getElementById('finalizarVeiculo');
      const finalizarForma = document.getElementById('finalizarForma');

      const cancelarLavagemId = document.getElementById('cancelarLavagemId');
      const cancelarVeiculo = document.getElementById('cancelarVeiculo');
      const cancelarMotivo = document.getElementById('cancelarMotivo');

      let timer = null;
      let abortCtrl = null;

      function shouldSearch(q) {
        if (!q) return true;
        if (/^\d+$/.test(q)) return true;
        return q.length >= 2;
      }

      function periodLabel(p) {
        if (p === 'semana') return 'Semana';
        if (p === 'mes') return 'Mês';
        return 'Hoje';
      }

      function setPeriodActive(p) {
        document.querySelectorAll('.js-period').forEach(btn => {
          const val = btn.getAttribute('data-period') || '';
          btn.classList.toggle('is-active', val === p);
        });
      }

      function setLoading() {
        if (!cardBodyWrap) return;
        cardBodyWrap.innerHTML = `
          <div class="card-body">
            <div class="text-center text-muted py-4">Carregando...</div>
          </div>
        `;
      }

      function showError(title, details) {
        if (!cardBodyWrap) return;
        const det = (details || '').toString().slice(0, 2000).replace(/</g, '&lt;');
        cardBodyWrap.innerHTML = `
          <div class="card-body">
            <div class="alert alert-danger mb-0">
              <div class="fw-semibold">${title}</div>
              <pre class="mb-0 mt-2" style="white-space:pre-wrap;font-size:12px;">${det}</pre>
            </div>
          </div>
        `;
      }

      function nextFrame() {
        return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      }

      async function load(page = 1) {
        const q = qEl.value.trim();
        const pp = parseInt(ppEl.value || '10', 10);
        const period = periodEl ? (periodEl.value || 'hoje') : 'hoje';

        clearBtn.disabled = (q === '');

        if (!shouldSearch(q)) {
          noticeEl.textContent = 'Digite pelo menos 2 letras para buscar.';
          if (cardBodyWrap) {
            cardBodyWrap.innerHTML = `
              <div class="card-body">
                <div class="text-center text-muted py-4">
                  Digite pelo menos 2 letras para buscar.
                </div>
              </div>
            `;
          }
          resumoEl.innerHTML = '';
          cardQtd.textContent = '0';
          cardTotal.textContent = 'R$ 0,00';
          return;
        } else {
          noticeEl.textContent = '';
        }

        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();

        const params = new URLSearchParams({
          partial: '1',
          q: q,
          pp: String(pp),
          p: String(page),
          period: period
        });

        setLoading();

        try {
          const url = new URL(window.location.pathname, window.location.origin);
          url.search = params.toString();

          const resp = await fetch(url.toString(), {
            signal: abortCtrl.signal,
            headers: {
              'X-Requested-With': 'fetch'
            }
          });

          const html = await resp.text();

          if (!resp.ok) {
            showError(`Erro HTTP ${resp.status}`, html);
            return;
          }

          const temp = document.createElement('div');
          temp.innerHTML = html;

          const root = temp.querySelector('#partialRoot');
          const noticeText = temp.querySelector('#noticeText');
          const resumoHtml = temp.querySelector('#resumoHtml');
          const cardsData = temp.querySelector('#cardsData');
          const cardBodyHtml = temp.querySelector('#cardBodyHtml');

          if (!root || !resumoHtml || !cardBodyHtml) {
            showError('Partial inválido (faltou #cardBodyHtml / #resumoHtml)', html);
            return;
          }

          if (cardBodyWrap) cardBodyWrap.innerHTML = cardBodyHtml.innerHTML;

          resumoEl.innerHTML = resumoHtml.innerHTML;
          noticeEl.textContent = noticeText ? (noticeText.textContent || '') : '';

          if (cardsData) {
            const qtd = cardsData.getAttribute('data-qtd') || '0';
            const total = cardsData.getAttribute('data-total') || '0,00';
            cardQtd.textContent = qtd;
            cardTotal.textContent = 'R$ ' + total;
          }

          // ✅ título + botões ativos (verde)
          if (tituloPeriodoEl) tituloPeriodoEl.textContent = 'Lavagens em aberto — ' + periodLabel(period);
          setPeriodActive(period);

          // ✅ atualiza URL
          const urlParams = new URLSearchParams(window.location.search);
          urlParams.set('q', q);
          urlParams.set('pp', String(pp));
          urlParams.set('p', String(page));
          urlParams.set('period', period);
          window.history.replaceState({}, '', window.location.pathname + '?' + urlParams.toString());

          await nextFrame();
          reinitLayoutAfterAjax();

        } catch (e) {
          if (e.name !== 'AbortError') {
            showError('Erro no fetch', String(e?.message || e));
          }
        }
      }

      function debounceLoad(page = 1) {
        clearTimeout(timer);
        timer = setTimeout(() => load(page), 650);
      }

      qEl.addEventListener('input', () => debounceLoad(1));
      ppEl.addEventListener('change', () => load(1));

      clearBtn.addEventListener('click', () => {
        qEl.value = '';
        load(1);
        qEl.focus();
      });

      // ✅ clique nos botões de período (funciona e deixa verde)
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-period');
        if (!btn) return;

        const p = btn.getAttribute('data-period') || 'hoje';
        if (periodEl) periodEl.value = p;

        setPeriodActive(p);
        load(1);
      });

      // ✅ paginação (delegação)
      document.addEventListener('click', (e) => {
        const a = e.target.closest('#paginationWrap a[data-page]');
        if (a) {
          e.preventDefault();
          const page = parseInt(a.getAttribute('data-page'), 10);
          if (!isNaN(page)) load(page);
        }
      });

      // ✅ botões da tabela (delegação)
      document.addEventListener('click', (e) => {
        const btnVer = e.target.closest('.btn-ver');
        if (btnVer) {
          const id = btnVer.getAttribute('data-id');
          if (verLavagemFrame) verLavagemFrame.src = 'lavagemVer.php?id=' + encodeURIComponent(id);
          if (modalVerLavagem) modalVerLavagem.show();
          return;
        }

        const btnFin = e.target.closest('.btn-finalizar');
        if (btnFin) {
          const id = btnFin.getAttribute('data-id') || '';
          const valor = btnFin.getAttribute('data-valor') || '0,00';
          const placa = btnFin.getAttribute('data-placa') || '-';
          const modelo = btnFin.getAttribute('data-modelo') || '-';
          const cor = btnFin.getAttribute('data-cor') || '-';

          if (finalizarLavagemId) finalizarLavagemId.value = id;
          if (finalizarValor) finalizarValor.textContent = 'R$ ' + valor;
          if (finalizarVeiculo) finalizarVeiculo.textContent = placa + ' • ' + modelo + ' • ' + cor;
          if (finalizarForma) finalizarForma.value = 'dinheiro';
          if (modalFinalizar) modalFinalizar.show();
          return;
        }

        const btnCanc = e.target.closest('.btn-cancelar');
        if (btnCanc) {
          const id = btnCanc.getAttribute('data-id') || '';
          const placa = btnCanc.getAttribute('data-placa') || '-';
          const modelo = btnCanc.getAttribute('data-modelo') || '-';
          const cor = btnCanc.getAttribute('data-cor') || '-';

          if (cancelarLavagemId) cancelarLavagemId.value = id;
          if (cancelarVeiculo) cancelarVeiculo.textContent = placa + ' • ' + modelo + ' • ' + cor;
          if (cancelarMotivo) cancelarMotivo.value = '';

          if (modalCancelar) modalCancelar.show();
          return;
        }
      });

      if (modalVerLavagemEl && verLavagemFrame) {
        modalVerLavagemEl.addEventListener('hidden.bs.modal', () => {
          verLavagemFrame.src = 'about:blank';
        });
      }

      // ✅ garante cor verde correta ao abrir a página
      setPeriodActive(periodEl ? (periodEl.value || 'hoje') : 'hoje');
    })();
  </script>

</body>

</html>