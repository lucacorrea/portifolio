<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?chave=NNNN...&venda_id=123&id=principal_1  OU  danfe_nfce.php?arq=procNFCe_...xml

ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');

/* ===================== Atualiza venda (chave/status) ===================== */
try {
  require_once __DIR__ . '/config.php';

  // Empresa/venda vindos da URL ou sessão
  $empresaIdUrl = isset($_GET['id']) ? trim((string)$_GET['id']) : (string)($_SESSION['empresa_id'] ?? '');
  $vendaIdUrl   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);

  // Descobre a chave informada
  $chaveReq = null;
  if (!empty($_GET['chave'])) {
    $chaveReq = preg_replace('/\D+/', '', (string)$_GET['chave']);
  } elseif (!empty($_GET['arq'])) {
    $arq = (string)$_GET['arq'];
    if (preg_match('/^[\w.\-\/]+$/', $arq) && is_file($arq)) {
      $xmlTmp = @file_get_contents($arq);
      if ($xmlTmp) {
        if (preg_match('/Id="NFe(\d{44})"/', $xmlTmp, $m))             $chaveReq = $m[1];
        elseif (preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xmlTmp, $m)) $chaveReq = $m[1];
      }
    }
  }

  // Atualiza a venda quando já sabemos tudo (Apenas colunas existentes)
  if ($vendaIdUrl > 0 && $chaveReq && strlen($chaveReq) === 44) {
    try {
      $stV = $pdo->prepare("UPDATE vendas SET tipo_nota = 'fiscal' WHERE id = :id");
      $stV->execute([':id' => $vendaIdUrl]);
    } catch (Throwable $ve) {
      error_log('DANFE:update tipo_nota falhou: ' . $ve->getMessage());
    }
  }
} catch (Throwable $e) {
  error_log('DANFE:update venda falhou: ' . $e->getMessage());
}
/* ======================================================================== */

function br($v)
{
  return number_format((float)$v, 2, ',', '.');
}
function limpar($s)
{
  return trim((string)$s);
}
function fmtChave($ch)
{
  $ch = preg_replace('/\D+/', '', $ch);
  return trim(implode(' ', str_split($ch, 4)));
}
function mapTPag($t)
{
  $k = str_pad(preg_replace('/\D+/', '', (string)$t), 2, '0', STR_PAD_LEFT);
  $m = ['01' => 'Dinheiro', '02' => 'Cheque', '03' => 'Cartão de Crédito', '04' => 'Cartão de Débito', '05' => 'Crédito Loja', '10' => 'Vale Alimentação', '11' => 'Vale Refeição', '12' => 'Vale Presente', '13' => 'Vale Combustível', '15' => 'Boleto', '16' => 'Depósito', '17' => 'PIX', '20' => 'PIX', '18' => 'Transferência/Carteira', '19' => 'Programa de Fidelidade', '90' => 'Sem Pagamento', '99' => 'Outros'];
  return $m[$k] ?? 'Outros';
}

/* =========================== Carrega XML procNFe ========================== */
$base = __DIR__ . DIRECTORY_SEPARATOR;
$chave = null;
$error = null;

if (!empty($_GET['arq'])) {
  $file = $base . basename((string)$_GET['arq']);
} elseif (!empty($_GET['chave'])) {
  $chave = preg_replace('/\D+/', '', (string)$_GET['chave']);
  $file = $base . 'procNFCe_' . $chave . '.xml';
} elseif ($vendaIdUrl > 0) {
  // Busca a chave no banco se não veio na URL
  try {
    $stNF = $pdo->prepare("SELECT chave FROM nfce_emitidas WHERE venda_id = ? AND status_sefaz IN ('100', '150') ORDER BY id DESC LIMIT 1");
    $stNF->execute([$vendaIdUrl]);
    $chave = $stNF->fetchColumn();
    if ($chave) {
      $file = $base . 'procNFCe_' . $chave . '.xml';
    } else {
      $error = "Nenhuma nota fiscal autorizada foi encontrada para a venda #{$vendaIdUrl}.";
    }
  } catch (Throwable $e) {
    $error = "Erro ao buscar dados da nota: " . $e->getMessage();
  }
} else {
  $error = "Parâmetros de visualização ausentes.";
}

if ($error || !isset($file) || !is_file($file)) {
  if (!$error) $error = "O arquivo XML da nota não foi localizado no servidor.";
?>
  <!DOCTYPE html>
  <html lang="pt-BR">

  <head>
    <meta charset="UTF-8">
    <title>Nota não encontrada</title>
    <style>
      body {
        font-family: sans-serif;
        background: #f4f7f9;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
      }

      .card {
        background: white;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        text-align: center;
        max-width: 450px;
      }

      h2 {
        color: #d32f2f;
        margin-top: 0;
      }

      p {
        color: #555;
        line-height: 1.5;
      }

      .actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
        justify-content: center;
      }

      .btn {
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
      }

      .btn-primary {
        background: #2b4c7d;
        color: white;
        border: none;
      }

      .btn-secondary {
        background: #e0e0e0;
        color: #333;
        border: none;
      }

      .btn:hover {
        filter: brightness(1.1);
      }
    </style>
  </head>

  <body>
    <div class="card">
      <h2>⚠️ Nota não localizada</h2>
      <p><?php echo htmlspecialchars($error); ?></p>
      <p>Apesar da venda ser fiscal, o documento oficial não pôde ser gerado ou encontrado. Você deseja imprimir o recibo comum em vez disso?</p>
      <div class="actions">
        <a href="javascript:window.close()" class="btn btn-secondary">Fechar</a>
        <?php if ($vendaIdUrl > 0): ?>
          <a href="../recibo_venda.php?id=<?php echo $vendaIdUrl; ?>" class="btn btn-primary">Imprimir Recibo</a>
        <?php endif; ?>
      </div>
    </div>
  </body>

  </html>
<?php
  exit;
}

$xml = file_get_contents($file);
$dom = new DOMDocument();
$dom->loadXML($xml);

$nfeNS  = 'http://www.portalfiscal.inf.br/nfe';
$infNFe = $dom->getElementsByTagNameNS($nfeNS, 'infNFe')->item(0);
$nfe    = $dom->getElementsByTagNameNS($nfeNS, 'NFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($nfeNS, 'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($nfeNS, 'protNFe')->item(0);

/* ============================== Emitente ================================ */
$emit = $dom->getElementsByTagNameNS($nfeNS, 'emit')->item(0);
$enderEmit = $emit ? $emit->getElementsByTagNameNS($nfeNS, 'enderEmit')->item(0) : null;
$emit_xNome = $emit ? limpar($emit->getElementsByTagName('xNome')->item(0)->nodeValue) : '';
$emit_xFant = $emit ? limpar(($emit->getElementsByTagName('xFant')->item(0)->nodeValue ?? '')) : '';
$emit_CNPJ  = $emit ? limpar($emit->getElementsByTagName('CNPJ')->item(0)->nodeValue) : '';
$emit_IE    = $emit ? limpar($emit->getElementsByTagName('IE')->item(0)->nodeValue) : '';
$end_txt = '';
if ($enderEmit) {
  $end_txt = limpar($enderEmit->getElementsByTagName('xLgr')->item(0)->nodeValue) . ' ' .
    limpar($enderEmit->getElementsByTagName('nro')->item(0)->nodeValue) . ', ' .
    limpar($enderEmit->getElementsByTagName('xBairro')->item(0)->nodeValue) . ', ' .
    limpar($enderEmit->getElementsByTagName('xMun')->item(0)->nodeValue) . ' - ' .
    limpar($enderEmit->getElementsByTagName('UF')->item(0)->nodeValue);
}

/* ================================ IDE ================================== */
$ide    = $dom->getElementsByTagNameNS($nfeNS, 'ide')->item(0);
$serie  = $ide ? limpar($ide->getElementsByTagName('serie')->item(0)->nodeValue) : '';
$nNF    = $ide ? limpar($ide->getElementsByTagName('nNF')->item(0)->nodeValue) : '';
$dhEmi  = $ide ? limpar($ide->getElementsByTagName('dhEmi')->item(0)->nodeValue) : '';
$idAttr = $infNFe ? $infNFe->getAttribute('Id') : '';
$chave  = preg_replace('/^NFe/', '', $idAttr);

/* =============================== Totais ================================ */
$tot    = $dom->getElementsByTagNameNS($nfeNS, 'ICMSTot')->item(0);
$vProd  = $tot ? br($tot->getElementsByTagName('vProd')->item(0)->nodeValue) : '0,00';
$vDesc  = $tot ? br($tot->getElementsByTagName('vDesc')->item(0)->nodeValue) : '0,00';
$vNF    = $tot ? br($tot->getElementsByTagName('vNF')->item(0)->nodeValue) : '0,00';
$vTrib  = $tot ? br(($tot->getElementsByTagName('vTotTrib')->item(0)->nodeValue ?? 0)) : '0,00';

/* ============================== Pagamento ============================== */
$detPag = $dom->getElementsByTagNameNS($nfeNS, 'detPag')->item(0);
$tPag   = $detPag ? limpar($detPag->getElementsByTagName('tPag')->item(0)->nodeValue) : '';
$vPag   = $detPag ? br($detPag->getElementsByTagName('vPag')->item(0)->nodeValue) : '0,00';
$vTroco = $dom->getElementsByTagNameNS($nfeNS, 'vTroco')->item(0);
$vTroco = $vTroco ? br($vTroco->nodeValue) : '0,00';

/* ============================== Destinatário =========================== */
$dest     = $dom->getElementsByTagNameNS($nfeNS, 'dest')->item(0);
$dest_doc = '';
$dest_nome = '';
if ($dest) {
  $dCNPJ = $dest->getElementsByTagName('CNPJ')->item(0);
  $dCPF  = $dest->getElementsByTagName('CPF')->item(0);
  $dXN   = $dest->getElementsByTagName('xNome')->item(0);
  $dest_doc = $dCNPJ ? 'CNPJ: ' . limpar($dCNPJ->nodeValue) : ($dCPF ? 'CPF: ' . limpar($dCPF->nodeValue) : '');
  $dest_nome = $dXN ? limpar($dXN->nodeValue) : '';
}

/* ============================ Protocolo ================================ */
$protInfo = '';
if ($prot) {
  $infProt = $prot->getElementsByTagName('infProt')->item(0);
  $cStat   = $infProt ? limpar($infProt->getElementsByTagName('cStat')->item(0)->nodeValue) : '';
  $xMotivo = $infProt ? limpar($infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue) : '';
  $nProt   = $infProt ? limpar(($infProt->getElementsByTagName('nProt')->item(0)->nodeValue ?? '')) : '';
  $dhRec   = $infProt ? limpar($infProt->getElementsByTagName('dhRecbto')->item(0)->nodeValue) : '';
  $protInfo = $nProt ? "Protocolo de Autorização: $nProt — $dhRec" : "Status: $cStat — $xMotivo";
}

/* ============================== QR Code ================================ */
$qrTxt   = $supl ? limpar($supl->getElementsByTagName('qrCode')->item(0)->nodeValue) : '';
$urlChave = $supl ? limpar($supl->getElementsByTagName('urlChave')->item(0)->nodeValue) : '';

/* ================================ Itens ================================ */
$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
  $prod = $det->getElementsByTagNameNS($nfeNS, 'prod')->item(0);
  if (!$prod) continue;
  $itens[] = [
    'cProd' => limpar($prod->getElementsByTagName('cProd')->item(0)->nodeValue),
    'xProd' => limpar($prod->getElementsByTagName('xProd')->item(0)->nodeValue),
    'qCom'  => number_format((float)$prod->getElementsByTagName('qCom')->item(0)->nodeValue, 3, ',', '.'),
    'uCom'  => limpar($prod->getElementsByTagName('uCom')->item(0)->nodeValue),
    'vUn'   => br($prod->getElementsByTagName('vUnCom')->item(0)->nodeValue),
    'vTot'  => br($prod->getElementsByTagName('vProd')->item(0)->nodeValue),
  ];
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>DANFE NFC-e</title>
  <style>
    :root {
      --ticket-max: 384px;
      --pad: 12px;
      --qr: 210px;
      --accent: #1a73e8;
      --ink: #111;
      --paper: #fff;
      --bg: #f5f7fb
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      padding: 0;
      background: var(--bg);
      color: var(--ink);
      font: 13px/1.45 monospace
    }

    .wrapper {
      width: 100%;
      max-width: var(--ticket-max);
      margin: 10px auto 92px;
      background: var(--paper);
      border-radius: 12px;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .08);
    }

    .center {
      text-align: center
    }

    .right {
      text-align: right
    }

    .small {
      font-size: 11px
    }

    .hr {
      border-top: 1px dashed #000;
      margin: 8px 0
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed
    }

    .tbl th {
      border-bottom: 1px dashed #000;
      padding: 4px 0
    }

    .tbl td {
      padding: 3px 0;
      vertical-align: top
    }

    .badge {
      display: inline-block;
      background: #eef2ff;
      color: #1f2937;
      padding: 3px 6px;
      border-radius: 6px;
      font-size: 10px
    }

    .actions {
      position: fixed;
      left: 0;
      right: 0;
      bottom: 0;
      padding: 10px;
      background: #fff;
      border-top: 1px solid #e5e7eb;
      display: flex;
      gap: 10px;
      justify-content: center
    }

    .btn {
      border: 0;
      border-radius: 10px;
      padding: 11px 16px;
      font-weight: 600;
      cursor: pointer
    }

    .btn-primary {
      background: var(--accent);
      color: #fff
    }

    .btn-secondary {
      background: #e5e7eb;
      color: #111
    }

    #qrcode {
      display: block;
      margin: 8px auto;
      width: var(--qr);
      height: var(--qr)
    }

    @media print {
      .actions {
        display: none
      }

      .wrapper{
        zoom: 116% !important;
        margin-left: -20px !important;
      }

      .nome_empresa{
        font-size: 13px !important;
      }

      .wrapper {
        box-shadow: none;
        border-radius: 0;
        margin: 0;
        width: 75mm
      }
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <header class="center">
      <h2 class="nome_empresa"><?= htmlspecialchars($emit_xFant ?: $emit_xNome) ?></h2>
      <div class="small">CNPJ: <?= htmlspecialchars($emit_CNPJ) ?> &middot; IE: <?= htmlspecialchars($emit_IE) ?><br><?= htmlspecialchars($end_txt) ?></div>
      <div class="hr"></div>
      <div class="small badge">NFC-e não permite aproveitamento de crédito de ICMS</div>
      <div class="hr"></div>
    </header>

    <table class="tbl small">
      <thead>
        <tr>
          <th class="left">Descrição</th>
          <th class="right">Qtde</th>
          <th class="right">V.Unit</th>
          <th class="right">V.Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itens as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['xProd']) ?></td>
            <td class="right"><?= $it['qCom'] ?><br><?= $it['uCom'] ?></td>
            <td class="right"><?= $it['vUn'] ?></td>
            <td class="right"><?= $it['vTot'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="hr"></div>
    <table class="tbl small">
      <tbody>
        <tr>
          <td><b>QTDE TOTAL DE ITENS</b></td>
          <td class="right"><?= count($itens) ?></td>
        </tr>
        <tr>
          <td><b>VALOR TOTAL R$</b></td>
          <td class="right"><?= $vNF ?></td>
        </tr>
        <tr>
          <td><b>FORMA PAGAMENTO</b></td>
          <td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td>
        </tr>
        <tr>
          <td><b>VALOR PAGO R$</b></td>
          <td class="right"><?= $vPag ?></td>
        </tr>
        <?php if ($vTroco != '0,00'): ?><tr>
            <td><b>TROCO R$</b></td>
            <td class="right"><?= $vTroco ?></td>
          </tr><?php endif; ?>
      </tbody>
    </table>

    <div class="hr"></div>
    <div class="small">Nº: <?= $nNF ?> Série: <?= $serie ?> Emissão: <?= $dhEmi ?></div>
    <div class="center small"><b>CHAVE DE ACESSO</b><br><?= fmtChave($chave) ?></div>

    <div class="hr"></div>
    <div class="center small">
      <b>CONSUMIDOR</b><br>
      <?php
      $dN = strtoupper(trim($dest_nome));
      if ($dN !== '' && $dN !== 'CONSUMIDOR FINAL' && $dN !== 'CONSUMIDOR AVULSO' && $dN !== 'CONSUMIDOR NÃO IDENTIFICADO'): ?>
        <?= htmlspecialchars($dest_nome) ?><br>
        <?= htmlspecialchars($dest_doc) ?>
      <?php else: ?>
        <?= htmlspecialchars($dest_doc ?: 'CONSUMIDOR FINAL') ?>
      <?php endif; ?>
    </div>

    <div class="hr"></div>
    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode"></div>

    <div class="hr"></div>
    <?php if ($protInfo): ?><div class="small center"><?= htmlspecialchars($protInfo) ?></div><?php endif; ?>
  </div>

  <div class="actions">
    <button class="btn btn-secondary" onclick="if(window.history.length > 1) { window.history.back(); } else { window.close(); }">Voltar / Fechar</button>
    <a class="btn btn-secondary" href="danfe_a4.php?id=<?= urlencode($empresaIdUrl) ?>&venda_id=<?= (int)$vendaIdUrl ?>&chave=<?= urlencode($chave) ?>" target="_blank">Modelo SEFAZ (A4)</a>
    <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    new QRCode(document.getElementById("qrcode"), {
      text: <?= json_encode($qrTxt) ?>,
      width: 210,
      height: 210
    });
  </script>
</body>

</html>