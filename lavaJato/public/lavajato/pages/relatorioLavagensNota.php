<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

require_once __DIR__ . '/../../../conexao/conexao.php';
require_once __DIR__ . '/../controllers/relatorioLavagensController.php';
require_once __DIR__ . '/../../../lib/util.php';

/* Inputs */
$ini = $_GET['ini'] ?? date('Y-m-d');
$fim = $_GET['fim'] ?? date('Y-m-d');

/* Helpers */
function h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v)
{
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/* Empresa */
$empresaNome = empresa_nome_logada($pdo) ?: 'SUA EMPRESA';

/* Dados */
$vm = relatorio_lavagens_viewmodel($pdo, [
  'ini'    => $ini,
  'fim'    => $fim,
  'status' => 'todos'
]);

$linhas = $vm['linhas'] ?? [];

/* Totais */
$totalConcluido = 0.0;
$totalAberto    = 0.0;

/* Resumo por lavador */
$resumoLavadores = [];

foreach ($linhas as $l) {
  $valor  = (float)($l['valor'] ?? 0);
  $status = strtolower((string)($l['status'] ?? ''));

  if ($status === 'concluida') {
    $totalConcluido += $valor;
  }

  if ($status === 'aberta') {
    $totalAberto += $valor;
  }

  // tenta descobrir o nome do lavador em campos comuns
  $lavador = trim((string)($l['lavador'] ?? ''));

  if ($lavador === '') {
    $lavador = trim((string)($l['lavador_nome'] ?? ''));
  }

  if ($lavador === '') {
    $lavador = trim((string)($l['nome_lavador'] ?? ''));
  }

  if ($lavador === '') {
    $lavador = trim((string)($l['funcionario'] ?? ''));
  }

  if ($lavador === '') {
    $lavador = trim((string)($l['responsavel'] ?? ''));
  }

  if ($lavador === '') {
    $lavador = 'Sem lavador';
  }

  if (!isset($resumoLavadores[$lavador])) {
    $resumoLavadores[$lavador] = [
      'lavador' => $lavador,
      'qtd'     => 0,
      'total'   => 0.0,
    ];
  }

  $resumoLavadores[$lavador]['qtd']++;
  $resumoLavadores[$lavador]['total'] += $valor;
}

$totalGeral = $totalConcluido + $totalAberto;

/* Ordena por quantidade desc, depois total desc, depois nome asc */
if (!empty($resumoLavadores)) {
  usort($resumoLavadores, function ($a, $b) {
    if ($a['qtd'] !== $b['qtd']) {
      return $b['qtd'] <=> $a['qtd'];
    }
    if ((float)$a['total'] !== (float)$b['total']) {
      return (float)$b['total'] <=> (float)$a['total'];
    }
    return strcmp((string)$a['lavador'], (string)$b['lavador']);
  });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    html,
    body {
      width: 80mm;
      margin: 0;
      padding: 2mm;
      font-family: monospace;
      font-size: 11px;
      color: #000;
    }

    @page {
      size: 80mm auto;
      margin: 0;
    }

    .center {
      text-align: center;
    }

    .right {
      text-align: right;
    }

    .line {
      border-top: 1px dashed #000;
      margin: 6px 0;
    }

    .row {
      display: flex;
      justify-content: space-between;
      margin: 3px 0;
      gap: 8px;
    }

    .bold {
      font-weight: 700;
    }

    .big {
      font-size: 14px;
      font-weight: 800;
    }

    .empresa {
      font-size: 13px;
      font-weight: 800;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
    }

    th,
    td {
      padding: 3px 0;
      vertical-align: top;
      font-size: 11px;
    }

    th {
      text-align: left;
      border-bottom: 1px solid #000;
      font-weight: 800;
    }

    th.qtd,
    td.qtd {
      width: 12mm;
      text-align: right;
    }

    th.total,
    td.total {
      width: 22mm;
      text-align: right;
    }

    .small {
      font-size: 10px;
    }

    @media print {
      .no-print {
        display: none;
      }
    }
  </style>
</head>

<body>

  <div class="center empresa">
    <?= h($empresaNome) ?>
  </div>

  <div class="center bold">
    RELATÓRIO DE LAVAGENS
  </div>

  <div class="line"></div>

  <div class="row">
    <span>Data</span>
    <span><?= date('d/m/Y', strtotime($ini)) ?> a <?= date('d/m/Y', strtotime($fim)) ?></span>
  </div>

  <div class="line"></div>

  <table>
    <thead>
      <tr>
        <th>Lavador</th>
        <th class="qtd">Qtd</th>
        <th class="total">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($resumoLavadores)): ?>
        <tr>
          <td colspan="3" class="center">Sem lavagens no período</td>
        </tr>
      <?php else: ?>
        <?php foreach ($resumoLavadores as $r): ?>
          <tr>
            <td><?= h($r['lavador']) ?></td>
            <td class="qtd"><?= (int)$r['qtd'] ?></td>
            <td class="total"><?= money($r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="line"></div>

  <div class="row">
    <span>Concluído</span>
    <span><?= money($totalConcluido) ?></span>
  </div>

  <div class="row">
    <span>Em aberto</span>
    <span><?= money($totalAberto) ?></span>
  </div>

  <div class="line"></div>

  <div class="row big">
    <span>Total</span>
    <span><?= money($totalGeral) ?></span>
  </div>

  <div class="line"></div>

  <div class="center small">
    NÃO É DOCUMENTO FISCAL
  </div>

  <script>
    (function() {
      const AUTO = 1;
      let printed = false;
      let redirected = false;

      const url = "relatorioLavagens.php?<?= http_build_query([
                                            'ini' => $ini,
                                            'fim' => $fim
                                          ]) ?>";

      function goBack() {
        if (redirected) return;
        redirected = true;
        window.location.href = url;
      }

      function doPrint() {
        if (printed) return;
        printed = true;
        window.print();
      }

      window.addEventListener('load', () => {
        if (AUTO === 1) {
          setTimeout(doPrint, 200);
        }
      });

      if ('onafterprint' in window) {
        window.addEventListener('afterprint', () => {
          setTimeout(goBack, 300);
        });
      }

      document.addEventListener('visibilitychange', () => {
        if (printed && document.visibilityState === 'visible') {
          setTimeout(goBack, 300);
        }
      });

      setTimeout(() => {
        if (AUTO === 1) {
          goBack();
        }
      }, 8000);
    })();
  </script>
</body>

</html>