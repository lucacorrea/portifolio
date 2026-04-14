<?php
// autoErp/public/lavajato/pages/lavagensNotaPublic.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Conexão direta (sem util.php / sem guard) ===== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  die('Conexão indisponível.');
}

/* ===== Parâmetros obrigatórios =====
   c   = CNPJ (com ou sem pontuação) da empresa
   mes = YYYY-mm (mês)
   w   = 1..4 (semana do mês)
   lav = "CPF:xxx" (com ou sem pontuação) OU "N:Nome" (ou só Nome)
======================================================= */
$cnpjIn = preg_replace('/\D+/', '', (string)($_GET['c'] ?? ''));
$mes    = isset($_GET['mes']) ? (string)$_GET['mes'] : '';
$w      = isset($_GET['w'])   ? (int)$_GET['w']   : 0;
$lav    = isset($_GET['lav']) ? (string)$_GET['lav'] : '';

if (!preg_match('/^\d{14}$/', $cnpjIn) || !preg_match('/^\d{4}\-\d{2}$/', $mes) || $w < 1 || $w > 4 || $lav === '') {
  http_response_code(400);
  die('Parâmetros inválidos.');
}

/* ===== Helpers ===== */
$soDig = static fn(?string $s): string => preg_replace('/\D+/', '', (string)$s);

function empresa_info_by_cnpj_pub(PDO $pdo, string $cnpj): array
{
  $sql = "SELECT nome_fantasia, razao_social, telefone, email, endereco, cidade, estado, cep
            FROM empresas_peca
           WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','') = :c
           LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':c' => $cnpj]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

function periodo_semana_do_mes(string $ym, int $w): array
{
  [$y, $m] = array_map('intval', explode('-', $ym));
  $iniMes = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m));
  $fimMes = $iniMes->modify('last day of this month')->setTime(23, 59, 59);
  $last   = (int)$fimMes->format('j');
  $ranges = [1 => [1, 7], 2 => [8, 14], 3 => [15, 21], 4 => [22, $last]];
  [$dini, $dfim] = $ranges[$w];
  $ini = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $dini));
  $fim = new DateTimeImmutable(sprintf('%04d-%02d-%02d 23:59:59', $y, $m, $dfim));
  return [$ini, $fim, $ini->format('d/m') . ' – ' . $fim->format('d/m')];
}

/* ===== Busca registros da semana, por empresa, agregando por lavador ===== */
[$ini, $fim, $periodLabel] = periodo_semana_do_mes($mes, $w);

$sql = "
  SELECT
    COALESCE(l.checkout_at, l.checkin_at, l.criado_em) AS data_evento,
    l.categoria_nome, l.modelo, l.cor, l.placa,
    l.valor, l.forma_pagamento, l.status,
    l.lavador_cpf,
    u.nome AS lavador_nome
  FROM lavagens_peca l
  LEFT JOIN lavadores_peca u
    ON u.cpf = l.lavador_cpf AND u.empresa_cnpj = l.empresa_cnpj
  WHERE REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','') = :c
    AND COALESCE(l.checkout_at, l.checkin_at, l.criado_em) BETWEEN :ini AND :fim
  ORDER BY data_evento ASC
";
$st = $pdo->prepare($sql);
$st->execute([':c' => $cnpjIn, ':ini' => $ini->format('Y-m-d H:i:s'), ':fim' => $fim->format('Y-m-d H:i:s')]);

$map = []; // lav_key => ['lavador','lav_key','items'=>[], 'qtd'=>0,'total'=>0.0]
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  $cpf   = trim((string)($row['lavador_cpf'] ?? ''));
  $nome  = trim((string)($row['lavador_nome'] ?? ''));
  if ($cpf !== '') {
    $key   = 'CPF:' . $cpf;
    $label = ($nome !== '' ? $nome : 'CPF ' . $cpf);
  } elseif ($nome !== '') {
    $key   = 'N:' . $nome;
    $label = $nome;
  } else {
    $key   = 'desconhecido';
    $label = '—';
  }

  if (!isset($map[$key])) $map[$key] = ['lavador' => $label, 'lav_key' => $key, 'items' => [], 'qtd' => 0, 'total' => 0.0];

  $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$row['data_evento'])
    ?: new DateTimeImmutable((string)$row['data_evento']);
  $map[$key]['items'][] = [
    'quando'          => $dt->format('d/m/Y H:i'),
    'servico'         => (string)($row['categoria_nome'] ?? 'Serviço'),
    'valor'           => (float)($row['valor'] ?? 0),
    'status'          => (string)$row['status'],
    'forma_pagamento' => (string)$row['forma_pagamento'],
  ];
  $map[$key]['qtd']++;
  $map[$key]['total'] += (float)($row['valor'] ?? 0);
}

/* Resolve lavador da URL (CPF com/sem pontuação, ou N:Nome) */
$lavKey = '';
$try = trim($lav);
if ($try !== '') {
  if (stripos($try, 'CPF:') === 0 || preg_match('/\d/', $try)) {
    $cpfTgt = $soDig(str_ireplace('CPF:', '', $try));
    foreach ($map as $k => $info) {
      if (stripos($k, 'CPF:') === 0 && $soDig(substr($k, 4)) === $cpfTgt) {
        $lavKey = $k;
        break;
      }
    }
  }
  if ($lavKey === '') {
    $nomeTgt = mb_strtolower(preg_replace('/^N:/i', '', $try), 'UTF-8');
    foreach ($map as $k => $info) {
      if (mb_strtolower($info['lavador'], 'UTF-8') === $nomeTgt) {
        $lavKey = $k;
        break;
      }
    }
  }
}
if ($lavKey === '' && count($map) === 1) $lavKey = array_key_first($map);
if ($lavKey === '') {
  http_response_code(404);
  die('Lavador não encontrado para esta semana.');
}

$det = $map[$lavKey];

/* Agrupar por serviço (qtd + total) */
$agr = [];
foreach ($det['items'] as $r) {
  $nome = trim((string)($r['servico'] ?? 'Serviço'));
  $key  = mb_strtolower($nome, 'UTF-8');
  if (!isset($agr[$key])) $agr[$key] = ['nome' => $nome, 'qtd' => 0, 'total' => 0.0];
  $agr[$key]['qtd']++;
  $agr[$key]['total'] += (float)($r['valor'] ?? 0);
}
usort($agr, function ($a, $b) {
  return ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']);
});

/* Empresa (dados completos) */
$empresa     = empresa_info_by_cnpj_pub($pdo, $cnpjIn);
$empresaNome = $empresa['nome_fantasia'] ?? '—';

/* ===== Configuração pública e percentuais ===== */
$cfg = [
  'permitir_publico_qr'  => 1,    // default segue sua tela de Config (permite público por padrão)
  'utilidades_pct'       => 0.00,
  'comissao_lavador_pct' => 0.00,
];
try {
  $stCfg = $pdo->prepare(
    "SELECT permitir_publico_qr, utilidades_pct, comissao_lavador_pct
       FROM lavjato_config_peca
      WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
      LIMIT 1"
  );
  $stCfg->execute([':c' => $cnpjIn]);
  if ($row = $stCfg->fetch(PDO::FETCH_ASSOC)) {
    $cfg['permitir_publico_qr']  = (int)$row['permitir_publico_qr'];
    $cfg['utilidades_pct']       = (float)$row['utilidades_pct'];
    $cfg['comissao_lavador_pct'] = (float)$row['comissao_lavador_pct'];
  }
} catch (Throwable $e) {
  // mantém defaults
}

/* Se a empresa NÃO permite acesso público via QR, bloqueia a página */
if (empty($cfg['permitir_publico_qr'])) {
  http_response_code(403);
  die('Acesso público via QR desabilitado para esta empresa.');
}

/* Totais e percentuais (se permitido) */
$fmtBRL = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$fmtPct = static function (float $p): string {
  $s = number_format($p, 2, ',', '.');
  $s = rtrim(rtrim($s, '0'), ',');
  return $s . '%';
};

$uPct = max(0.0, min(100.0, (float)$cfg['utilidades_pct']));
$cPct = max(0.0, min(100.0, (float)$cfg['comissao_lavador_pct']));

$bruto     = (float)($det['total'] ?? 0.0);
$descontU  = round($bruto * ($uPct / 100.0), 2);
$liquido   = round($bruto - $descontU, 2);
$comissao  = round($liquido * ($cPct / 100.0), 2);
$aPagarLav = round($liquido - $comissao, 2);

$hojeDM  = date('d/m');
$emissao = (new DateTime())->format('d/m/Y H:i:s');

/* URL desta (para QR) */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri     = $_SERVER['REQUEST_URI'] ?? '';
$notaUrl = $scheme . '://' . $host . $uri;

/* “Chave de acesso” (fake 44 dígitos) */
$seed = $cnpjIn . '|' . $mes . '|' . $w . '|' . $lavKey;
$hash = preg_replace('/\D+/', '', hash('sha256', $seed));
$chaveAcesso = substr(str_pad($hash, 44, '0'), 0, 44);
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DANFE NFC-e (Pública) • Semana <?= (int)$w ?></title>
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --w-cupom: 360px;
      /* ~80mm */
      --fg: #111;
      --muted: #555;
      --line: #d9d9d9;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: #f5f6f8;
      color: var(--fg);
      margin: 0;
    }

    .cupom {
      width: var(--w-cupom);
      margin: 16px auto;
      background: #fff;
      border: 1px solid var(--line);
    }

    .p-12 {
      padding: 12px;
    }

    .topo {
      border-bottom: 1px dashed var(--line);
      text-align: center;
    }

    .titulo {
      font-weight: 700;
      text-transform: uppercase;
      margin-top: 6px;
    }

    .sub {
      color: var(--muted);
      font-size: .82rem;
    }

    .sec {
      border-bottom: 1px dashed var(--line);
    }

    .linha {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      padding: 2px 0;
    }

    .lbl {
      color: var(--muted);
      font-size: .82rem;
    }

    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
      letter-spacing: .3px;
    }

    .chave {
      font-size: .92rem;
      word-break: break-all;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead th {
      text-align: left;
      font-weight: 700;
      padding: 6px 0;
      border-bottom: 1px solid var(--line);
    }

    tbody td {
      padding: 6px 0;
      border-bottom: 1px dashed var(--line);
      vertical-align: top;
    }

    .t-right {
      text-align: right;
    }

    .t-center {
      text-align: center;
    }

    .total {
      display: flex;
      justify-content: space-between;
      font-weight: 700;
      padding: 6px 0;
    }

    .qrwrap {
      display: flex;
      gap: 10px;
      align-items: center;
      justify-content: space-between;
    }

    .qrbox {
      width: 120px;
      height: 120px;
      border: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .obs {
      color: var(--muted);
      font-size: .8rem;
    }

    @media print {
      body {
        background: #fff;
        margin: 0;
      }

      .cupom {
        border: none;
        margin: 0;
      }

      @page {
        margin: 6mm;
      }
    }
  </style>
</head>

<body>

  <!-- CUPOM NFC-e (público) -->
  <div class="cupom">
    <!-- Cabeçalho -->
    <div class="topo p-12">
      <div class="titulo">Resumo de Serviços</div>
      <div style="margin-top:8px;">
        <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong><br>
        <?php
        $linha2 = [];
        if (!empty($empresa['endereco'])) $linha2[] = $empresa['endereco'];
        $cidadeUF = trim(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? ''));
        if ($cidadeUF !== '/') $linha2[] = $cidadeUF . (!empty($empresa['cep']) ? ' • CEP ' . $empresa['cep'] : '');
        if (!empty($empresa['telefone'])) $linha2[] = 'Tel. ' . $empresa['telefone'];
        if (!empty($empresa['email']))    $linha2[] = $empresa['email'];
        echo '<span class="sub">' . htmlspecialchars(implode(' • ', array_filter($linha2)), ENT_QUOTES, 'UTF-8') . '</span>';
        ?>
      </div>
    </div>

    <!-- Identificação -->
    <div class="sec p-12">
      <div class="linha">
        <div>
          <div class="lbl">Emissão</div>
          <div class="mono"><?= htmlspecialchars($emissao, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="t-right">
          <div class="lbl">Período (semana <?= (int)$w ?>)</div>
          <div class="mono"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
      <div style="margin-top:6px;">
        <div class="lbl">Chave de Acesso</div>
        <div class="mono chave"><?= htmlspecialchars(chunk_split($chaveAcesso, 4, ' '), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <!-- Destinatário -->
    <div class="sec p-12">
      <div class="lbl">Destinatário (Funcionário)</div>
      <div class="mono"><strong><?= htmlspecialchars((string)$det['lavador'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    </div>

    <!-- Itens (agrupados por serviço) -->
    <div class="sec p-12">
      <table>
        <thead>
          <tr>
            <th>Descrição</th>
            <th class="t-center" style="width:60px;">Qtde</th>
            <th class="t-right" style="width:90px;">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agr as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="t-center mono"><?= (int)$item['qtd'] ?></td>
              <td class="t-right mono"><?= $fmtBRL((float)$item['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totais + (se permitido) Resumo Financeiro com % -->
    <div class="sec p-12">
      <div class="total">
        <span>BRUTO</span>
        <span class="mono"><?= $fmtBRL($bruto) ?></span>
      </div>

      <!-- Detalhamento com percentuais (sempre que página pública estiver permitida) -->
      <div class="linha">
        <span>Utilidades (<?= $fmtPct($uPct) ?>)</span>
        <span class="mono">− <?= $fmtBRL($descontU) ?></span>
      </div>
      <div class="linha" style="border-top:1px dashed var(--line); margin-top:4px; padding-top:6px;">
        <span>LÍQUIDO</span>
        <span class="mono"><?= $fmtBRL($liquido) ?></span>
      </div>
      <div class="linha">
        <span>Comissão Lavador (<?= $fmtPct($cPct) ?>)</span>
        <span class="mono">− <?= $fmtBRL($comissao) ?></span>
      </div>
      <div class="total" style="border-top:1px solid var(--line); margin-top:6px; padding-top:8px;">
        <span>A PAGAR AO LAVADOR</span>
        <span class="mono"><?= $fmtBRL($aPagarLav) ?></span>
      </div>
    </div>

    <!-- Consulta / QR -->
    <div class="p-12">
      <div class="qrwrap">
        <div>
          <div class="lbl">Consulta via QR Code</div>
          <div class="obs">Escaneie para visualizar esta nota em seu dispositivo.</div>
          <div class="mono" style="margin-top:6px; word-break:break-all; font-size:.82rem;">
            <?= htmlspecialchars($notaUrl, ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
        <div class="qrbox" id="qrcode" aria-label="QR Code para esta nota pública"></div>
        <noscript>
          <img alt="QR Code" width="120" height="120"
            src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode($notaUrl) ?>">
        </noscript>
      </div>
      <div class="obs" style="margin-top:10px;">
        Documento sem valor fiscal. Uso interno de resumo de serviços (lavagens).
      </div>
    </div>
  </div>

  <!-- QR Code -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script>
    (function() {
      try {
        var el = document.getElementById('qrcode');
        if (el && window.QRCode) {
          new QRCode(el, {
            text: <?= json_encode($notaUrl) ?>,
            width: 120,
            height: 120,
            correctLevel: QRCode.CorrectLevel.M
          });
        }
      } catch (e) {}
    })();
  </script>
</body>

</html>