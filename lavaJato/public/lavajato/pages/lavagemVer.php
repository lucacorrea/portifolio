<?php
// autoErp/public/lavajato/pages/lavagemVer.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function br_money(float $v): string { return number_format($v, 2, ',', '.'); }

function badge_status(string $status): array
{
  $s = mb_strtolower(trim($status));
  if ($s === 'aberta') return ['warning', 'Aberta', 'bi-hourglass-split'];
  if ($s === 'lavando') return ['info', 'Lavando', 'bi-droplet-half'];
  if ($s === 'concluida' || $s === 'concluída') return ['success', 'Concluída', 'bi-check2-circle'];
  if ($s === 'cancelada') return ['danger', 'Cancelada', 'bi-x-circle'];
  return ['secondary', $status ?: '-', 'bi-info-circle'];
}

function fmt_dt(?string $s): string
{
  if (!$s) return '-';
  try { return (new DateTime($s))->format('d/m/Y H:i'); }
  catch (Throwable $e) { return $s; }
}

function fmt_payment(string $p): string
{
  $p = mb_strtolower(trim($p));
  return match ($p) {
    'dinheiro' => 'Dinheiro',
    'pix' => 'Pix',
    'credito', 'crédito' => 'Cartão (Crédito)',
    'debito', 'débito' => 'Cartão (Débito)',
    'transferencia', 'transferência' => 'Transferência',
    default => ($p !== '' ? ucfirst($p) : '-'),
  };
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('ID inválido.'); }

try {
  $sql = "
    SELECT
      l.*,
      COALESCE(NULLIF(lv.nome,''), l.lavador_cpf) AS lavador_nome
    FROM lavagens_peca l
    LEFT JOIN lavadores_peca lv
      ON lv.empresa_cnpj = l.empresa_cnpj
     AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(lv.cpf,''), '.', ''), '-', ''), '/', ''), ' ', '')
       = REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.lavador_cpf,''), '.', ''), '-', ''), '/', ''), ' ', '')
    WHERE l.id = :id
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $id]);
  $lav = $st->fetch(PDO::FETCH_ASSOC);

  if (!$lav) { http_response_code(404); die('Lavagem não encontrada.'); }

  if (!empty($_SESSION['empresa_cnpj']) && (string)$lav['empresa_cnpj'] !== (string)$_SESSION['empresa_cnpj']) {
    http_response_code(403);
    die('Acesso negado.');
  }

  $stA = $pdo->prepare("
    SELECT id, lavagem_id, adicional_id, nome, valor, criado_em
    FROM lavagem_adicionais_peca
    WHERE lavagem_id = :id
    ORDER BY id DESC
  ");
  $stA->execute([':id' => $id]);
  $adicionais = $stA->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  die('Erro ao carregar detalhes: ' . $e->getMessage());
}

/* ===== dados ===== */
$placa  = (string)($lav['placa'] ?? '');
$modelo = (string)($lav['modelo'] ?? '');
$cor    = (string)($lav['cor'] ?? '');
$cat    = (string)($lav['categoria_nome'] ?? '-');

$lavadorNome = (string)($lav['lavador_nome'] ?? ($lav['lavador_cpf'] ?? '-'));

$valorBase = (float)($lav['valor'] ?? 0.0);
$somaAd = 0.0;
foreach ($adicionais as $a) $somaAd += (float)($a['valor'] ?? 0.0);
$totalGeral = $valorBase + $somaAd;

$forma = fmt_payment((string)($lav['forma_pagamento'] ?? 'dinheiro'));
$status = (string)($lav['status'] ?? '');
[$badge, $labelStatus, $statusIcon] = badge_status($status);

$checkin  = !empty($lav['checkin_at']) ? (string)$lav['checkin_at'] : null;
$checkout = !empty($lav['checkout_at']) ? (string)$lav['checkout_at'] : null;
$obs      = trim((string)($lav['observacoes'] ?? ''));

$criadoEm = !empty($lav['criado_em']) ? (string)$lav['criado_em'] : null;

$veiculoTitle = trim(($modelo ?: '') . ($cor ? " • {$cor}" : '') . ($placa ? " • {$placa}" : ''));
if ($veiculoTitle === '') $veiculoTitle = 'Veículo não informado';

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Detalhes da Lavagem</title>

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
    body { background: transparent; }
    .page-wrap { padding: 14px; }

    .header-card{
      border-radius: 14px;
      overflow: hidden;
    }
    .header-top{
      padding: 14px 14px 10px 14px;
      background: rgba(0,0,0,.02);
    }
    .header-bottom{
      padding: 10px 14px 14px 14px;
    }

    .mini-muted{ font-size: 12px; color: rgba(108,117,125,1); }
    .title-line{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      align-items:center;
      justify-content:space-between;
    }

    .kpi{
      border: 1px solid rgba(0,0,0,.06);
      border-radius: 14px;
      padding: 12px;
      background: rgba(255,255,255,.65);
    }
    .kpi .label{ font-size: 12px; color: rgba(108,117,125,1); }
    .kpi .value{ font-size: 18px; font-weight: 700; }

    .kv .k{ font-size: 12px; color: rgba(108,117,125,1); }
    .kv .v{ font-size: 14px; font-weight: 600; }

    .chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 12px;
      border: 1px solid rgba(0,0,0,.06);
      background: rgba(255,255,255,.7);
    }

    .obs-box{
      border-radius: 12px;
      border: 1px dashed rgba(0,0,0,.18);
      padding: 10px 12px;
      background: rgba(255,255,255,.5);
    }

    .table td, .table th { vertical-align: middle; }
    .table thead th { white-space: nowrap; }
  </style>
</head>

<body>
  <div class="page-wrap">

    <!-- HEADER BONITO -->
    <div class="card header-card mb-3">
      <div class="header-top">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="mini-muted"><?= h((string)$empresaNome) ?></div>
            <div class="title-line">
              <div>
                <h4 class="mb-0">Lavagem <span class="text-muted">#<?= (int)$lav['id'] ?></span></h4>
                <div class="mini-muted">Criado em: <?= h(fmt_dt($criadoEm)) ?></div>
              </div>
            </div>
          </div>

          <div class="text-end">
            <span class="badge bg-<?= h($badge) ?> px-3 py-2">
              <i class="bi <?= h($statusIcon) ?> me-1"></i><?= h($labelStatus) ?>
            </span>

            <div class="mt-2 d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i>
              </button>

              <?php if (mb_strtolower((string)($lav['status'] ?? '')) === 'aberta'): ?>
                <a class="btn btn-sm btn-success"
                   href="../actions/lavagensFinalizar.php?id=<?= (int)$lav['id'] ?>"
                   onclick="return confirm('Finalizar diretamente? (recomendado usar o botão Finalizar na lista)')">
                  <i class="bi bi-check-circle"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- KPIs -->
      <div class="header-bottom">
        <div class="row g-2">
          <div class="col-12 col-md-4">
            <div class="kpi">
              <div class="label">Total geral</div>
              <div class="value">R$ <?= h(br_money($totalGeral)) ?></div>
              <div class="mini-muted">Base: R$ <?= h(br_money($valorBase)) ?> • Adicionais: R$ <?= h(br_money($somaAd)) ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="kpi">
              <div class="label">Veículo</div>
              <div class="value" style="font-size:16px;"><?= h($veiculoTitle) ?></div>
              <div class="mini-muted"><?= h($cat ?: '-') ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="kpi">
              <div class="label">Pagamento</div>
              <div class="value" style="font-size:16px;"><?= h($forma) ?></div>
              <div class="mini-muted">Lavador: <?= h($lavadorNome ?: '-') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- GRID PRINCIPAL -->
    <div class="row g-3">
      <!-- Dados do veículo -->
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0"><i class="bi bi-car-front me-2"></i>Veículo</h5>
            <span class="chip"><i class="bi bi-tag"></i><?= h($cat ?: '-') ?></span>
          </div>
          <div class="card-body">
            <div class="row g-3 kv">
              <div class="col-6">
                <div class="k">Placa</div>
                <div class="v"><?= h($placa ?: '-') ?></div>
              </div>
              <div class="col-6">
                <div class="k">Modelo</div>
                <div class="v"><?= h($modelo ?: '-') ?></div>
              </div>
              <div class="col-6">
                <div class="k">Cor</div>
                <div class="v"><?= h($cor ?: '-') ?></div>
              </div>
              <div class="col-6">
                <div class="k">Categoria</div>
                <div class="v"><?= h($cat ?: '-') ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Controle -->
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i>Controle</h5>
            <span class="badge bg-<?= h($badge) ?> px-3 py-2">
              <i class="bi <?= h($statusIcon) ?> me-1"></i><?= h($labelStatus) ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row g-3 kv">
              <div class="col-12">
                <div class="k">Lavador</div>
                <div class="v"><?= h($lavadorNome ?: '-') ?></div>
              </div>
              <div class="col-6">
                <div class="k">Check-in</div>
                <div class="v"><?= h(fmt_dt($checkin)) ?></div>
              </div>
              <div class="col-6">
                <div class="k">Check-out</div>
                <div class="v"><?= h(fmt_dt($checkout)) ?></div>
              </div>
              <div class="col-12">
                <div class="k">Forma de pagamento</div>
                <div class="v"><?= h($forma ?: '-') ?></div>
              </div>
            </div>

            <?php if ($obs !== ''): ?>
              <hr class="my-3">
              <div class="k mb-2">Observações</div>
              <div class="obs-box small"><?= nl2br(h($obs)) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Valores + Adicionais -->
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0"><i class="bi bi-cash-coin me-2"></i>Valores e adicionais</h5>
            <div class="chip">
              <i class="bi bi-receipt"></i>
              Total: R$ <?= h(br_money($totalGeral)) ?>
            </div>
          </div>

          <div class="card-body">
            <div class="row g-3 mb-2">
              <div class="col-12 col-md-4">
                <div class="kpi">
                  <div class="label">Valor base</div>
                  <div class="value">R$ <?= h(br_money($valorBase)) ?></div>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <div class="kpi">
                  <div class="label">Adicionais</div>
                  <div class="value">R$ <?= h(br_money($somaAd)) ?></div>
                  <div class="mini-muted"><?= count($adicionais) ?> item(ns)</div>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <div class="kpi">
                  <div class="label">Total</div>
                  <div class="value">R$ <?= h(br_money($totalGeral)) ?></div>
                </div>
              </div>
            </div>

            <hr class="my-3">

            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Itens adicionais</h6>
              <span class="text-muted small"><?= count($adicionais) ?> item(ns)</span>
            </div>

            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead>
                  <tr>
                    <th style="width:90px">#</th>
                    <th>Nome</th>
                    <th class="text-end" style="width:140px">Valor</th>
                    <th style="width:170px">Criado em</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($adicionais)): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">Sem adicionais.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($adicionais as $a): ?>
                      <tr>
                        <td class="text-muted">#<?= (int)$a['id'] ?></td>
                        <td class="fw-semibold"><?= h((string)$a['nome']) ?></td>
                        <td class="text-end">R$ <?= h(br_money((float)$a['valor'])) ?></td>
                        <td class="text-muted small"><?= h(fmt_dt((string)($a['criado_em'] ?? null))) ?></td>
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

    <div class="mt-3 small text-muted text-center">
      AutoERP • <?= h((string)$empresaNome) ?>
    </div>

  </div>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
</body>
</html>
