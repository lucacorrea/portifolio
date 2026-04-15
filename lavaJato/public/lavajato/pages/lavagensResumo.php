<?php
// autoErp/public/lavajato/pages/lavagensResumo.php
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
$ctrlSemana = __DIR__ . '/../controllers/lavagensSemanaController.php';
if (!file_exists($ctrlSemana)) {
  http_response_code(500);
  die('Controller semanal não encontrado.');
}
require_once $ctrlSemana;

/* ==== Inputs ==== */
$weekRef = trim((string)($_GET['week_ref'] ?? ''));
$iniGet  = trim((string)($_GET['ini'] ?? ''));
$fimGet  = trim((string)($_GET['fim'] ?? ''));
$q       = trim((string)($_GET['q'] ?? ''));
$lav     = trim((string)($_GET['lav'] ?? ''));

if ($lav === '' || ($weekRef === '' && ($iniGet === '' || $fimGet === ''))) {
  header('Location: lavagens.php?msg=Par%C3%A2metros%20inv%C3%A1lidos.&err=1');
  exit;
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

function pct($p): string
{
  $p = (float)$p;
  $s = number_format($p, 2, ',', '.');
  $s = rtrim(rtrim($s, '0'), ',');
  return $s . '%';
}

function only_time(string $dt): string
{
  $ts = strtotime($dt);
  return $ts ? date('H:i', $ts) : $dt;
}

/* ==== ViewModel da semana ==== */
try {
  if (!function_exists('lavagens_semana_por_lavador_viewmodel')) {
    throw new RuntimeException('Função lavagens_semana_por_lavador_viewmodel() não encontrada.');
  }

  $args = [
    'lav' => $lav,
    'q'   => $q,
  ];

  if ($weekRef !== '') {
    $args['week_ref'] = $weekRef;
  } else {
    $args['ini'] = $iniGet;
    $args['fim'] = $fimGet;
  }

  $vmBase = lavagens_semana_por_lavador_viewmodel($pdo, $args);

  if (empty($vmBase['detalhe'])) {
    throw new RuntimeException('Lavador não encontrado para esta semana.');
  }

  $det = $vmBase['detalhe'];
  $period = (string)($vmBase['periodo_label'] ?? '');
  $weekRefOut = (string)($vmBase['week_ref'] ?? $weekRef);
  $ini = (string)($vmBase['periodo_ini'] ?? '');
  $fim = (string)($vmBase['periodo_fim'] ?? '');
  $iniDate = (string)($vmBase['ini'] ?? '');
  $fimDate = (string)($vmBase['fim'] ?? '');
} catch (Throwable $e) {
  $vmBase = ['ok' => false, 'err' => true, 'msg' => 'Erro: ' . $e->getMessage()];
  $det = ['lavador' => '—', 'qtd' => 0, 'total' => 0.0, 'items' => []];
  $period = '';
  $weekRefOut = $weekRef;
  $ini = '';
  $fim = '';
  $iniDate = $iniGet;
  $fimDate = $fimGet;
}

/* ==== Empresa ==== */
$cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $cnpj)) {
  http_response_code(403);
  die('Empresa inválida.');
}

/* ==== Resolver lavador ==== */
$lavadorId = 0;
$lavadorNome = (string)($det['lavador'] ?? '—');
$lavCpf = '';
$lavKey = (string)($det['lav_key'] ?? $lav);

try {
  if (stripos($lavKey, 'CPF:') === 0) {
    $lavCpf = preg_replace('/\D+/', '', substr($lavKey, 4));
  } elseif (stripos($lavKey, 'N:') === 0) {
    $lavadorNome = trim(substr($lavKey, 2));
  }

  if ($lavCpf !== '') {
    $st = $pdo->prepare("
            SELECT id, nome, cpf
              FROM lavadores_peca
             WHERE empresa_cnpj = :c
               AND REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),'/','') = :cpf
             LIMIT 1
        ");
    $st->execute([':c' => $cnpj, ':cpf' => $lavCpf]);

    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $lavadorId = (int)$r['id'];
      if (!empty($r['nome'])) {
        $lavadorNome = (string)$r['nome'];
      }
    }
  }

  if ($lavadorId <= 0 && $lavadorNome !== '' && $lavadorNome !== '—') {
    $st = $pdo->prepare("
            SELECT id, nome, cpf
              FROM lavadores_peca
             WHERE empresa_cnpj = :c
               AND nome = :n
             LIMIT 1
        ");
    $st->execute([':c' => $cnpj, ':n' => $lavadorNome]);

    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $lavadorId = (int)$r['id'];
      if (!empty($r['cpf']) && $lavCpf === '') {
        $lavCpf = preg_replace('/\D+/', '', (string)$r['cpf']);
      }
    }
  }
} catch (Throwable $e) {
  // segue sem vale
}

/* ==== Config ==== */
$cfg = [
  'utilidades_pct'         => 0.00,
  'comissao_lavador_pct'   => 0.00,
  'permitir_publico_qr'    => 1,
  'imprimir_auto'          => 0,
  'forma_pagamento_padrao' => 'dinheiro',
  'obs'                    => '',
];

try {
  $stCfg = $pdo->prepare("
        SELECT utilidades_pct, comissao_lavador_pct, permitir_publico_qr, imprimir_auto, forma_pagamento_padrao, obs
          FROM lavjato_config_peca
         WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         LIMIT 1
    ");
  $stCfg->execute([':c' => $cnpj]);

  if ($rowCfg = $stCfg->fetch(PDO::FETCH_ASSOC)) {
    $cfg['utilidades_pct']         = (float)($rowCfg['utilidades_pct'] ?? 0);
    $cfg['comissao_lavador_pct']   = (float)($rowCfg['comissao_lavador_pct'] ?? 0);
    $cfg['permitir_publico_qr']    = (int)($rowCfg['permitir_publico_qr'] ?? 1);
    $cfg['imprimir_auto']          = (int)($rowCfg['imprimir_auto'] ?? 0);
    $cfg['forma_pagamento_padrao'] = (string)($rowCfg['forma_pagamento_padrao'] ?? 'dinheiro');
    $cfg['obs']                    = (string)($rowCfg['obs'] ?? '');
  }
} catch (Throwable $e) {
}

$uPct = max(0.0, min(100.0, (float)$cfg['utilidades_pct']));
$cPct = max(0.0, min(100.0, (float)$cfg['comissao_lavador_pct']));

/* ==== Cálculos ==== */
$bruto     = (float)($det['total'] ?? 0.0);
$descU     = round($bruto * ($uPct / 100.0), 2);
$liq       = round($bruto - $descU, 2);
$comissao  = round($liq * ($cPct / 100.0), 2);
$aPagarLav = $comissao;

/* ==== Vales da semana real ==== */
$vales = [];
$valesTotal = 0.0;

try {
  if ($lavadorId > 0 && $ini !== '' && $fim !== '') {
    $sqlV = "
            SELECT id, valor, motivo, forma_pagamento, criado_em, criado_por_cpf
              FROM vales_lavadores_peca
             WHERE empresa_cnpj = :c
               AND lavador_id   = :l
               AND criado_em BETWEEN :ini AND :fim
             ORDER BY criado_em DESC, id DESC
             LIMIT 200
        ";
    $st = $pdo->prepare($sqlV);
    $st->execute([
      ':c'   => $cnpj,
      ':l'   => $lavadorId,
      ':ini' => $ini,
      ':fim' => $fim,
    ]);

    $vales = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($vales as $vv) {
      $valesTotal += (float)($vv['valor'] ?? 0);
    }
    $valesTotal = round($valesTotal, 2);
  }
} catch (Throwable $e) {
  $vales = [];
  $valesTotal = 0.0;
}

$saldoARepasse = round($aPagarLav - $valesTotal, 2);

/* ==== CSRF ==== */
if (empty($_SESSION['csrf_vale_lavador'])) {
  $_SESSION['csrf_vale_lavador'] = bin2hex(random_bytes(32));
}
$csrfVale = $_SESSION['csrf_vale_lavador'];

/* ==== Alerts ==== */
$ok  = isset($_GET['ok']) && (int)$_GET['ok'] === 1;
$err = isset($_GET['err']) && (int)$_GET['err'] === 1;
$msg = (string)($_GET['msg'] ?? '');

/* ==== URLs ==== */
$backSemana = function () use ($weekRefOut, $iniDate, $fimDate, $q) {
  $args = [];
  if ($weekRefOut !== '') {
    $args['week_ref'] = $weekRefOut;
  } else {
    $args['ini'] = $iniDate;
    $args['fim'] = $fimDate;
  }
  if ($q !== '') {
    $args['q'] = $q;
  }
  return 'lavagensSemana.php?' . http_build_query($args);
};

$notaArgs = ['lav' => $lav];
if ($weekRefOut !== '') {
  $notaArgs['week_ref'] = $weekRefOut;
} else {
  $notaArgs['ini'] = $iniDate;
  $notaArgs['fim'] = $fimDate;
}
if ($q !== '') {
  $notaArgs['q'] = $q;
}
$notaUrl = 'lavagensNota.php?' . http_build_query($notaArgs);

/* ==== Agrupamento por dia dentro da semana ==== */
$valesPorDia = [];
if (!empty($vales)) {
  foreach ($vales as $v) {
    $criadoEm = (string)($v['criado_em'] ?? '');
    $ts = strtotime($criadoEm);
    if ($ts === false) {
      continue;
    }
    $diaKey = date('Y-m-d', $ts);
    if (!isset($valesPorDia[$diaKey])) {
      $valesPorDia[$diaKey] = [
        'total' => 0.0,
        'items' => [],
      ];
    }
    $valesPorDia[$diaKey]['items'][] = $v;
    $valesPorDia[$diaKey]['total'] += (float)($v['valor'] ?? 0);
  }
}

$porDia = [];
$diasPt = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

foreach ((array)($det['items'] ?? []) as $r) {
  $quandoStr = (string)($r['quando'] ?? '');
  $dt = DateTimeImmutable::createFromFormat('d/m/Y H:i', $quandoStr);
  if (!$dt) {
    $ts = strtotime($quandoStr);
    $dt = $ts ? (new DateTimeImmutable())->setTimestamp($ts) : null;
  }

  if (!$dt) {
    $diaKey = '0000-00-00';
    $diaLabel = '—';
  } else {
    $diaKey = $dt->format('Y-m-d');
    $dow = (int)$dt->format('w');
    $diaLabel = ($diasPt[$dow] ?? '') . ', ' . $dt->format('d/m/Y');
  }

  if (!isset($porDia[$diaKey])) {
    $porDia[$diaKey] = [
      'label'         => $diaLabel,
      'items'         => [],
      'total'         => 0.0,
      'qtd'           => 0,
      'abertas_total' => 0.0,
      'abertas_qtd'   => 0,
    ];
  }

  $valor = (float)($r['valor'] ?? 0);
  $st = strtolower(trim((string)($r['status'] ?? '')));
  $isAberta = ($st !== 'concluida' && $st !== 'cancelada');

  $porDia[$diaKey]['items'][] = $r;
  $porDia[$diaKey]['qtd']++;
  $porDia[$diaKey]['total'] += $valor;

  if ($isAberta) {
    $porDia[$diaKey]['abertas_qtd']++;
    $porDia[$diaKey]['abertas_total'] += $valor;
  }
}

ksort($porDia);
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Resumo do Lavador • <?= h($period) ?></title>

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

  <style>
    .form-control,
    .form-select {
      border-radius: 10px;
    }

    .form-label {
      color: black !important;
    }

    .table thead th {
      white-space: nowrap;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: .65rem;
      padding: .55rem .6rem;
      border-radius: 999px;
      background: #f3f6fb;
      border: 1px solid #e8eef7;
    }

    .pill strong {
      font-weight: 800;
    }

    .kpi {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: .8rem;
    }

    @media (max-width: 992px) {
      .kpi {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 520px) {
      .kpi {
        grid-template-columns: 1fr;
      }
    }

    .warn0 {
      border-left: 4px solid #f0ad4e;
      padding: .6rem .8rem;
      background: #fff7e6;
      color: #000 !important;
      border-radius: .5rem;
    }

    .dia-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
  </style>
</head>

<body>
  <?php
  $menuAtivo = 'lavagensResumo';
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>

          <div class="ms-auto d-print-none d-flex align-items-center gap-2">
            <a href="<?= h($notaUrl) ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-receipt me-1"></i> Imprimir Resumo
            </a>
            <a href="configuracoes.php" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-gear me-1"></i> Configurações
            </a>
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <h1 class="mb-0">Resumo do Lavador</h1>
          <p>
            <strong><?= h((string)$det['lavador']) ?></strong>
            • Semana <?= h($period !== '' ? $period : ($iniDate . ' – ' . $fimDate)) ?>
          </p>

          <?php if (!empty($vmBase['msg'])): ?>
            <div class="mt-2">
              <div class="alert alert-<?= !empty($vmBase['err']) ? 'danger' : 'success' ?> py-2 mb-0">
                <?= h((string)$vmBase['msg']) ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($ok || $err): ?>
            <div class="mt-2">
              <div class="alert alert-<?= $ok ? 'success' : 'danger' ?> py-2 mb-0">
                <?= h($msg ?: ($ok ? 'Vale lançado com sucesso.' : 'Falha ao lançar vale.')) ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100" alt="">
        </div>

        <div class="container-fluid content-inner mt-n3 py-0">
          <div class="row">
            <div class="col-12">

              <?php if ($uPct <= 0): ?>
                <div class="warn0 mb-3">
                  Atenção: o percentual de utilidades está zerado.
                </div>
              <?php endif; ?>

              <div class="card">
                <div class="card-header">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h4 class="card-title mb-0">Resumo financeiro e operacional</h4>

                    <div class="d-flex flex-wrap gap-2">
                      <a href="<?= h($backSemana()) ?>" class="btn btn-sm btn-outline-dark">
                        <i class="bi bi-arrow-left"></i> Voltar
                      </a>

                      <a href="<?= h($notaUrl) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-receipt"></i> Nota
                      </a>

                      <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#boxVale">
                        <i class="bi bi-plus-circle"></i> Gerar Vale
                      </button>
                    </div>
                  </div>
                </div>

                <div class="card-body">

                  <div class="kpi mb-3">
                    <div class="card shadow-sm mb-0">
                      <div class="card-body">
                        <div class="text-muted small"><i class="bi bi-cart-check"></i> Lavagens</div>
                        <div class="fs-5 text-success fw-bold"><?= (int)($det['qtd'] ?? 0) ?></div>
                      </div>
                    </div>

                    <div class="card shadow-sm mb-0">
                      <div class="card-body">
                        <div class="text-muted small"><i class="bi bi-cash-coin"></i> Bruto</div>
                        <div class="fs-5 text-success fw-bold"><?= money($bruto) ?></div>
                      </div>
                    </div>

                    <div class="card shadow-sm mb-0">
                      <div class="card-body">
                        <div class="text-muted small"><i class="bi bi-tools"></i> Utilidades (<?= pct($uPct) ?>)</div>
                        <div class="fs-5 text-success fw-bold">- <?= money($descU) ?></div>
                      </div>
                    </div>

                    <div class="card shadow-sm mb-0">
                      <div class="card-body">
                        <div class="text-muted small"><i class="bi bi-piggy-bank"></i> Saldo a repassar</div>
                        <div class="fs-5 text-success fw-bold"><?= money($saldoARepasse) ?></div>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                      <span class="pill text-success"><i class="bi bi-percent"></i> Comissão (<?= pct($cPct) ?>): <strong><?= money($comissao) ?></strong></span>
                      <span class="pill text-success"><i class="bi bi-wallet2"></i> A pagar (pré-vale): <strong><?= money($aPagarLav) ?></strong></span>
                      <span class="pill text-danger"><i class="bi bi-lightning-charge"></i> Total de Vales: <strong>- <?= money($valesTotal) ?></strong></span>
                    </div>
                  </div>

                  <div class="collapse d-print-none" id="boxVale">
                    <div class="card border mb-3">
                      <div class="card-body">
                        <?php if ($lavadorId <= 0): ?>
                          <div class="alert alert-warning mb-0">
                            Não consegui identificar <strong>lavador_id</strong>. Garanta que o lavador exista em <code>lavadores_peca</code> com CPF ou nome corretos.
                          </div>
                        <?php else: ?>
                          <form method="post" action="../actions/valeSalvar.php" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf" value="<?= h($csrfVale) ?>">
                            <input type="hidden" name="lav" value="<?= h($lav) ?>">
                            <input type="hidden" name="q" value="<?= h($q) ?>">
                            <input type="hidden" name="lavador_id" value="<?= (int)$lavadorId ?>">
                            <input type="hidden" name="lavador_nome" value="<?= h($lavadorNome) ?>">

                            <?php if ($weekRefOut !== ''): ?>
                              <input type="hidden" name="week_ref" value="<?= h($weekRefOut) ?>">
                            <?php else: ?>
                              <input type="hidden" name="ini" value="<?= h($iniDate) ?>">
                              <input type="hidden" name="fim" value="<?= h($fimDate) ?>">
                            <?php endif; ?>

                            <div class="col-12 col-md-3">
                              <label class="form-label">Valor do Vale</label>
                              <input type="text" name="valor" class="form-control form-control-sm" placeholder="Ex.: 25,00" required>
                            </div>

                            <div class="col-12 col-md-4">
                              <label class="form-label">Motivo</label>
                              <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Ex.: adiantamento, compra..." maxlength="255">
                            </div>

                            <div class="col-12 col-md-3">
                              <label class="form-label">Forma</label>
                              <select name="forma_pagamento" class="form-select form-select-sm">
                                <?php
                                $opts = [
                                  'dinheiro' => 'Dinheiro',
                                  'pix' => 'PIX',
                                  'debito' => 'Débito',
                                  'credito' => 'Crédito',
                                  'outro' => 'Outro'
                                ];
                                $pad = (string)($cfg['forma_pagamento_padrao'] ?? 'dinheiro');
                                foreach ($opts as $k => $rot) {
                                  $sel = ($pad === $k) ? 'selected' : '';
                                  echo '<option value="' . h($k) . '" ' . $sel . '>' . h($rot) . '</option>';
                                }
                                ?>
                              </select>
                            </div>

                            <div class="col-12 col-md-2">
                              <button class="btn btn-sm btn-success w-100">
                                <i class="bi bi-check2"></i> Salvar Vale
                              </button>
                            </div>

                            <div class="col-12">
                              <small class="text-muted">
                                O vale entra em <code>vales_lavadores_peca</code> e já aparece nos vales da semana.
                              </small>
                            </div>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <div class="card border mb-3">
                    <div class="card-body">
                      <div class="d-flex align-items-center justify-content-between">
                        <h6 class="mb-2"><i class="bi bi-gear me-1"></i> Configuração aplicada</h6>
                        <a class="btn btn-sm btn-outline-secondary d-print-none" href="configuracoes.php"><i class="bi bi-pencil"></i> Editar</a>
                      </div>

                      <div class="d-flex flex-wrap gap-2">
                        <span class="pill text-success">Utilidades: <strong><?= pct($uPct) ?></strong></span>
                        <span class="pill text-success">Comissão: <strong><?= pct($cPct) ?></strong></span>
                        <span class="pill text-success">Pagamento padrão: <strong><?= h((string)$cfg['forma_pagamento_padrao']) ?></strong></span>
                        <span class="pill text-success">QR público: <strong><?= !empty($cfg['permitir_publico_qr']) ? 'Sim' : 'Não' ?></strong></span>
                        <span class="pill text-success">Impressão auto: <strong><?= !empty($cfg['imprimir_auto']) ? 'Sim' : 'Não' ?></strong></span>
                      </div>

                      <?php if (!empty(trim((string)$cfg['obs']))): ?>
                        <div class="text-muted small mt-2"><?= h((string)$cfg['obs']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="card border mb-3">
                    <div class="card-body">
                      <div class="d-flex align-items-center justify-content-between">
                        <h6 class="mb-2"><i class="bi bi-ticket-detailed me-1"></i> Vales da semana</h6>
                        <div class="text-muted small">
                          <?= ($ini !== '' && $fim !== '') ? 'Filtrado pelo período real da semana' : 'Sem período detectado' ?>
                        </div>
                      </div>

                      <?php if ($lavadorId <= 0): ?>
                        <div class="text-muted">Sem vales: lavador_id não identificado.</div>
                      <?php elseif (empty($vales)): ?>
                        <div class="text-muted">Nenhum vale encontrado.</div>
                      <?php else: ?>
                        <div class="table-responsive">
                          <table class="table table-sm table-striped align-middle">
                            <thead>
                              <tr>
                                <th style="width:110px">ID</th>
                                <th style="width:160px">Criado em</th>
                                <th>Motivo</th>
                                <th style="width:140px">Forma</th>
                                <th class="text-end" style="width:140px">Valor</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($vales as $v): ?>
                                <tr>
                                  <td class="text-muted">#<?= (int)$v['id'] ?></td>
                                  <td><?= h(date('d/m/Y H:i', strtotime((string)$v['criado_em']))) ?></td>
                                  <td><?= h((string)($v['motivo'] ?? '—')) ?></td>
                                  <td><?= h((string)($v['forma_pagamento'] ?? '—')) ?></td>
                                  <td class="text-end"><?= money((float)($v['valor'] ?? 0)) ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if (empty($det['items'])): ?>
                    <div class="text-muted">Nenhuma lavagem registrada para este lavador nesta semana.</div>
                  <?php else: ?>

                    <?php foreach ($porDia as $diaKey => $diaRow): ?>
                      <?php
                      $valeDiaTotal = isset($valesPorDia[$diaKey]) ? (float)$valesPorDia[$diaKey]['total'] : 0.0;
                      $valeDiaItens = isset($valesPorDia[$diaKey]) ? ($valesPorDia[$diaKey]['items'] ?? []) : [];
                      $liquidoDia = round((float)$diaRow['total'] - $valeDiaTotal, 2);
                      ?>
                      <div class="card border mb-3">
                        <div class="card-body">
                          <div class="dia-head mb-3">
                            <div>
                              <h6 class="mb-1"><i class="bi bi-calendar-event me-1"></i><?= h((string)$diaRow['label']) ?></h6>
                              <div class="d-flex flex-wrap gap-2">
                                <span class="pill text-success">
                                  <i class="bi bi-cart-check"></i>
                                  Lavagens: <strong><?= (int)$diaRow['qtd'] ?></strong>
                                </span>
                                <span class="pill text-success">
                                  <i class="bi bi-cash-coin"></i>
                                  Total do dia: <strong><?= money((float)$diaRow['total']) ?></strong>
                                </span>
                                <span class="pill text-danger">
                                  <i class="bi bi-wallet2"></i>
                                  Vales do dia: <strong>- <?= money($valeDiaTotal) ?></strong>
                                </span>
                                <span class="pill text-primary">
                                  <i class="bi bi-piggy-bank"></i>
                                  Líquido do dia: <strong><?= money($liquidoDia) ?></strong>
                                </span>
                              </div>
                            </div>
                          </div>

                          <?php if (!empty($valeDiaItens)): ?>
                            <div class="mb-3">
                              <div class="small text-muted mb-2">Vales lançados neste dia</div>
                              <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle">
                                  <thead>
                                    <tr>
                                      <th style="width:160px">Hora</th>
                                      <th>Motivo</th>
                                      <th style="width:140px">Forma</th>
                                      <th class="text-end" style="width:140px">Valor</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php foreach ($valeDiaItens as $v): ?>
                                      <tr>
                                        <td><?= h(date('H:i', strtotime((string)$v['criado_em']))) ?></td>
                                        <td><?= h((string)($v['motivo'] ?? '—')) ?></td>
                                        <td><?= h((string)($v['forma_pagamento'] ?? '—')) ?></td>
                                        <td class="text-end"><?= money((float)($v['valor'] ?? 0)) ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          <?php endif; ?>

                          <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                              <thead>
                                <tr>
                                  <th style="width:110px">Hora</th>
                                  <th>Serviço</th>
                                  <th>Veículo</th>
                                  <th style="width:140px">Pagamento</th>
                                  <th style="width:140px">Status</th>
                                  <th class="text-end" style="width:140px">Valor</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($diaRow['items'] as $item): ?>
                                  <tr>
                                    <td><?= h(only_time((string)$item['quando'])) ?></td>
                                    <td><?= h((string)($item['servico'] ?? '—')) ?></td>
                                    <td><?= h((string)($item['veiculo'] ?? '—')) ?></td>
                                    <td><?= h((string)($item['forma_pagamento'] ?? '—')) ?></td>
                                    <td><?= h((string)($item['status'] ?? '—')) ?></td>
                                    <td class="text-end"><?= money((float)($item['valor'] ?? 0)) ?></td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>

                          <?php if ((int)$diaRow['abertas_qtd'] > 0): ?>
                            <div class="mt-2 small text-warning">
                              Existem <?= (int)$diaRow['abertas_qtd'] ?> lavagem(ns) ainda não concluída(s), somando <?= money((float)$diaRow['abertas_total']) ?>.
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>

                  <?php endif; ?>

                </div>
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