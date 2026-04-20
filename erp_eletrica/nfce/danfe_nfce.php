<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?chave=NNNN...&venda_id=123&id=principal_1
//   ou: danfe_nfce.php?arq=procNFCe_...xml

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Content-Type: text/html; charset=utf-8');

/* ===================== Atualiza venda (chave/status) ===================== */
$vendaIdUrl   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);
$empresaIdUrl = isset($_GET['id']) ? trim((string)$_GET['id']) : (string)($_SESSION['empresa_id'] ?? '');

try {
  require_once __DIR__ . '/config.php';

  $chaveReq = null;

  if (!empty($_GET['chave'])) {
    $chaveReq = preg_replace('/\D+/', '', (string)$_GET['chave']);
  } elseif (!empty($_GET['arq'])) {
    $arq = (string)$_GET['arq'];
    if (preg_match('/^[\w.\-\/]+$/', $arq) && is_file($arq)) {
      $xmlTmp = @file_get_contents($arq);
      if ($xmlTmp) {
        if (preg_match('/Id="NFe(\d{44})"/', $xmlTmp, $m)) {
          $chaveReq = $m[1];
        } elseif (preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xmlTmp, $m)) {
          $chaveReq = $m[1];
        }
      }
    }
  }

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

function br($v): string
{
  return number_format((float)$v, 2, ',', '.');
}

function limpar($s): string
{
  return trim((string)$s);
}

function fmtChave($ch): string
{
  $ch = preg_replace('/\D+/', '', (string)$ch);
  return trim(implode(' ', str_split($ch, 4)));
}

function mapTPag($t): string
{
  $k = str_pad(preg_replace('/\D+/', '', (string)$t), 2, '0', STR_PAD_LEFT);

  $m = [
    '01' => 'Dinheiro',
    '02' => 'Cheque',
    '03' => 'Cartão de Crédito',
    '04' => 'Cartão de Débito',
    '05' => 'Crédito Loja',
    '10' => 'Vale Alimentação',
    '11' => 'Vale Refeição',
    '12' => 'Vale Presente',
    '13' => 'Vale Combustível',
    '15' => 'Boleto',
    '16' => 'Depósito',
    '17' => 'PIX',
    '18' => 'Transferência/Carteira',
    '19' => 'Programa de Fidelidade',
    '20' => 'PIX',
    '90' => 'Sem Pagamento',
    '99' => 'Outros',
  ];

  return $m[$k] ?? 'Outros';
}

/* =========================== Carrega XML procNFe ========================== */
$base  = __DIR__ . DIRECTORY_SEPARATOR;
$chave = null;
$error = null;
$file  = null;

if (!empty($_GET['arq'])) {
  $file = $base . basename((string)$_GET['arq']);
} elseif (!empty($_GET['chave'])) {
  $chave = preg_replace('/\D+/', '', (string)$_GET['chave']);
  $file  = $base . 'procNFCe_' . $chave . '.xml';
} elseif ($vendaIdUrl > 0) {
  try {
    $stNF = $pdo->prepare("
      SELECT chave, status_sefaz
      FROM nfce_emitidas
      WHERE venda_id = ?
        AND status_sefaz IN ('100', '150', '101')
      ORDER BY id DESC
      LIMIT 1
    ");
    $stNF->execute([$vendaIdUrl]);

    $nfData = $stNF->fetch(PDO::FETCH_ASSOC);
    $chave = (string)($nfData['chave'] ?? '');
    $isCancelada = ($nfData['status_sefaz'] ?? '') == '101';

    if ($chave !== '') {
      $file = $base . 'procNFCe_' . $chave . '.xml';
    } else {
      $error = "Nenhuma nota fiscal autorizada ou cancelada foi encontrada para a venda #{$vendaIdUrl}.";
    }
  } catch (Throwable $e) {
    $error = "Erro ao buscar dados da nota: " . $e->getMessage();
  }
} else {
  $error = "Parâmetros de visualização ausentes.";
}

if ($error || !$file || !is_file($file)) {
  if (!$error) {
    $error = "O arquivo XML da nota não foi localizado no servidor.";
  }
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
      <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
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

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadXML($xml);

$nfeNS  = 'http://www.portalfiscal.inf.br/nfe';
$infNFe = $dom->getElementsByTagNameNS($nfeNS, 'infNFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($nfeNS, 'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($nfeNS, 'protNFe')->item(0);

/* ============================== Emitente ================================ */
$emit      = $dom->getElementsByTagNameNS($nfeNS, 'emit')->item(0);
$enderEmit = $emit ? $emit->getElementsByTagNameNS($nfeNS, 'enderEmit')->item(0) : null;

$emit_xNome = '';
$emit_xFant = '';
$emit_CNPJ  = '';
$emit_IE    = '';
$end_txt    = '';

if ($emit) {
  $node = $emit->getElementsByTagName('xNome')->item(0);
  $emit_xNome = $node ? limpar($node->nodeValue) : '';

  $node = $emit->getElementsByTagName('xFant')->item(0);
  $emit_xFant = $node ? limpar($node->nodeValue) : '';

  $node = $emit->getElementsByTagName('CNPJ')->item(0);
  $emit_CNPJ = $node ? limpar($node->nodeValue) : '';

  $node = $emit->getElementsByTagName('IE')->item(0);
  $emit_IE = $node ? limpar($node->nodeValue) : '';
}

if ($enderEmit) {
  $xLgr    = $enderEmit->getElementsByTagName('xLgr')->item(0);
  $nro     = $enderEmit->getElementsByTagName('nro')->item(0);
  $xBairro = $enderEmit->getElementsByTagName('xBairro')->item(0);
  $xMun    = $enderEmit->getElementsByTagName('xMun')->item(0);
  $UF      = $enderEmit->getElementsByTagName('UF')->item(0);

  $end_txt =
    limpar($xLgr ? $xLgr->nodeValue : '') . ' ' .
    limpar($nro ? $nro->nodeValue : '') . ', ' .
    limpar($xBairro ? $xBairro->nodeValue : '') . ', ' .
    limpar($xMun ? $xMun->nodeValue : '') . ' - ' .
    limpar($UF ? $UF->nodeValue : '');
}

/* ================================ IDE ================================== */
$ide    = $dom->getElementsByTagNameNS($nfeNS, 'ide')->item(0);
$serie  = '';
$nNF    = '';
$dhEmi  = '';

if ($ide) {
  $node = $ide->getElementsByTagName('serie')->item(0);
  $serie = $node ? limpar($node->nodeValue) : '';

  $node = $ide->getElementsByTagName('nNF')->item(0);
  $nNF = $node ? limpar($node->nodeValue) : '';

  $node = $ide->getElementsByTagName('dhEmi')->item(0);
  $dhEmi = $node ? limpar($node->nodeValue) : '';
}

$idAttr = $infNFe ? $infNFe->getAttribute('Id') : '';
$chave  = preg_replace('/^NFe/', '', $idAttr);

/* =============================== Totais ================================ */
$tot   = $dom->getElementsByTagNameNS($nfeNS, 'ICMSTot')->item(0);
$vProd = '0,00';
$vDesc = '0,00';
$vNF   = '0,00';
$vTrib = '0,00';

if ($tot) {
  $node = $tot->getElementsByTagName('vProd')->item(0);
  $vProd = $node ? br($node->nodeValue) : '0,00';

  $node = $tot->getElementsByTagName('vDesc')->item(0);
  $vDesc = $node ? br($node->nodeValue) : '0,00';

  $node = $tot->getElementsByTagName('vNF')->item(0);
  $vNF = $node ? br($node->nodeValue) : '0,00';

  $node = $tot->getElementsByTagName('vTotTrib')->item(0);
  $vTrib = $node ? br($node->nodeValue) : '0,00';
}

/* ============================== Pagamento ============================== */
$detPag = $dom->getElementsByTagNameNS($nfeNS, 'detPag')->item(0);
$tPag   = '';
$vPag   = '0,00';
$vTroco = $dom->getElementsByTagNameNS($nfeNS, 'vTroco')->item(0);

if ($detPag) {
  $node = $detPag->getElementsByTagName('tPag')->item(0);
  $tPag = $node ? limpar($node->nodeValue) : '';

  $node = $detPag->getElementsByTagName('vPag')->item(0);
  $vPag = $node ? br($node->nodeValue) : '0,00';
}

$vTroco = $vTroco ? br($vTroco->nodeValue) : '0,00';

/* ============================== Destinatário =========================== */
$dest      = $dom->getElementsByTagNameNS($nfeNS, 'dest')->item(0);
$dest_doc  = '';
$dest_nome = '';

if ($dest) {
  $dCNPJ = $dest->getElementsByTagName('CNPJ')->item(0);
  $dCPF  = $dest->getElementsByTagName('CPF')->item(0);
  $dXN   = $dest->getElementsByTagName('xNome')->item(0);

  $dest_doc  = $dCNPJ ? 'CNPJ: ' . limpar($dCNPJ->nodeValue) : ($dCPF ? 'CPF: ' . limpar($dCPF->nodeValue) : '');
  $dest_nome = $dXN ? limpar($dXN->nodeValue) : '';
}

/* ============================ Protocolo ================================ */
$protInfo = '';

if ($prot) {
  $infProt = $prot->getElementsByTagName('infProt')->item(0);

  if ($infProt) {
    $cStatNode = $infProt->getElementsByTagName('cStat')->item(0);
    $xMotivoNode = $infProt->getElementsByTagName('xMotivo')->item(0);
    $nProtNode = $infProt->getElementsByTagName('nProt')->item(0);
    $dhRecNode = $infProt->getElementsByTagName('dhRecbto')->item(0);

    $cStat   = $cStatNode ? limpar($cStatNode->nodeValue) : '';
    $xMotivo = $xMotivoNode ? limpar($xMotivoNode->nodeValue) : '';
    $nProt   = $nProtNode ? limpar($nProtNode->nodeValue) : '';
    $dhRec   = $dhRecNode ? limpar($dhRecNode->nodeValue) : '';

    $protInfo = $nProt ? "Protocolo de Autorização: $nProt — $dhRec" : "Status: $cStat — $xMotivo";
  }
}

/* ============================== QR Code ================================ */
$qrTxt    = '';
$urlChave = '';

if ($supl) {
  $node = $supl->getElementsByTagName('qrCode')->item(0);
  $qrTxt = $node ? limpar($node->nodeValue) : '';

  $node = $supl->getElementsByTagName('urlChave')->item(0);
  $urlChave = $node ? limpar($node->nodeValue) : '';
}

/* ================================ Itens ================================ */
$itens = [];

foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
  $prod = $det->getElementsByTagNameNS($nfeNS, 'prod')->item(0);
  if (!$prod) {
    continue;
  }

  $cProdNode = $prod->getElementsByTagName('cProd')->item(0);
  $xProdNode = $prod->getElementsByTagName('xProd')->item(0);
  $qComNode  = $prod->getElementsByTagName('qCom')->item(0);
  $uComNode  = $prod->getElementsByTagName('uCom')->item(0);
  $vUnNode   = $prod->getElementsByTagName('vUnCom')->item(0);
  $vTotNode  = $prod->getElementsByTagName('vProd')->item(0);

  $itens[] = [
    'cProd' => $cProdNode ? limpar($cProdNode->nodeValue) : '',
    'xProd' => $xProdNode ? limpar($xProdNode->nodeValue) : '',
    'qCom'  => number_format((float)($qComNode ? $qComNode->nodeValue : 0), 3, ',', '.'),
    'uCom'  => $uComNode ? limpar($uComNode->nodeValue) : '',
    'vUn'   => br($vUnNode ? $vUnNode->nodeValue : 0),
    'vTot'  => br($vTotNode ? $vTotNode->nodeValue : 0),
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
    --ticket-screen-max: 384px;
    --ticket-print-width: 80mm;
    --pad: 10px;
    --qr: 190px;
    --accent: #1a73e8;
    --ink: #111;
    --paper: #fff;
    --bg: #f5f7fb;
  }

  * {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  html,
  body {
    margin: 0;
    padding: 0;
    background: var(--bg);
    color: var(--ink);
    font: 13px/1.35 monospace;
    height: auto;
    min-height: 0;
  }

  body {
    display: block;
  }

  .wrapper {
    width: 100%;
    max-width: var(--ticket-screen-max);
    margin: 10px auto 92px auto;
    background: var(--paper);
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .08);
    padding: var(--pad);
    display: block;
  }

  .center {
    text-align: center;
  }

  .left {
    text-align: left;
  }

  .right {
    text-align: right;
  }

  .small {
    font-size: 11px;
  }

  .hr {
    border-top: 1px dashed #000;
    margin: 6px 0;
    height: 0;
  }

  .tbl {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .tbl th {
    border-bottom: 1px dashed #000;
    padding: 4px 0;
  }

  .tbl td {
    padding: 3px 0;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }

  .badge {
    display: inline-block;
    background: #eef2ff;
    color: #1f2937;
    padding: 3px 6px;
    border-radius: 6px;
    font-size: 10px;
  }

  .nome_empresa {
    margin: 0 0 4px 0;
    font-size: 15px;
    line-height: 1.25;
    word-break: break-word;
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
    justify-content: center;
    z-index: 999;
  }

  .btn {
    border: 0;
    border-radius: 10px;
    padding: 11px 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    font-size: 13px;
  }

  .btn-primary {
    background: var(--accent);
    color: #fff;
  }

  .btn-secondary {
    background: #e5e7eb;
    color: #111;
  }

  .banner-cancelada {
    background: #fee2e2;
    color: #b91c1c;
    border: 2px solid #b91c1c;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    text-transform: uppercase;
    display: block;
    width: 100%;
  }

  #qrcode {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 6px auto;
    width: var(--qr);
    min-height: var(--qr);
  }

  #qrcode img,
  #qrcode canvas {
    display: block;
    margin: 0 auto;
  }

  @page {
    size: 80mm auto;
    margin: 0;
  }

  @media print {
    html,
    body {
      width: 80mm !important;
      min-width: 80mm !important;
      max-width: 80mm !important;
      height: auto !important;
      min-height: 0 !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #fff !important;
      overflow: hidden !important;
    }

    body {
      display: inline-block !important;
    }

    body * {
      visibility: hidden !important;
    }

    .wrapper,
    .wrapper * {
      visibility: visible !important;
    }

    .wrapper {
      position: absolute !important;
      left: 0 !important;
      top: 0 !important;
      width: 80mm !important;
      max-width: 80mm !important;
      margin: 0 !important;
      padding: 3mm 2mm 0 2mm !important;
      border: none !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      background: #fff !important;
      display: block !important;
      page-break-after: avoid !important;
      break-after: avoid-page !important;
    }

    .actions {
      display: none !important;
    }

    .nome_empresa {
      font-size: 13px !important;
    }

    #qrcode {
      width: 180px !important;
      min-height: 180px !important;
      margin: 4px auto !important;
    }

    .hr {
      margin: 4px 0 !important;
    }

    .tbl th,
    .tbl td {
      padding: 2px 0 !important;
    }

    body::after,
    .wrapper::after {
      content: none !important;
      display: none !important;
      height: 0 !important;
    }
  }
</style>
</head>

<body>
  <div class="wrapper" id="ticket">
    <?php if (isset($isCancelada) && $isCancelada): ?>
      <div class="banner-cancelada">
        ⚠️ ESTA NOTA FOI CANCELADA NA SEFAZ ⚠️
      </div>
    <?php endif; ?>
    <header class="center">
      <h2 class="nome_empresa"><?= htmlspecialchars($emit_xFant ?: $emit_xNome, ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="small">
        CNPJ: <?= htmlspecialchars($emit_CNPJ, ENT_QUOTES, 'UTF-8') ?>
        &middot;
        IE: <?= htmlspecialchars($emit_IE, ENT_QUOTES, 'UTF-8') ?>
        <br>
        <?= htmlspecialchars($end_txt, ENT_QUOTES, 'UTF-8') ?>
      </div>

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
            <td><?= htmlspecialchars($it['xProd'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="right"><?= $it['qCom'] ?><br><?= htmlspecialchars($it['uCom'], ENT_QUOTES, 'UTF-8') ?></td>
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
          <td><b>VALOR PRODUTOS R$</b></td>
          <td class="right"><?= $vProd ?></td>
        </tr>
        <?php if ($vDesc !== '0,00'): ?>
          <tr>
            <td><b>DESCONTO R$</b></td>
            <td class="right"><?= $vDesc ?></td>
          </tr>
        <?php endif; ?>
        <tr>
          <td><b>VALOR TOTAL R$</b></td>
          <td class="right"><?= $vNF ?></td>
        </tr>
        <tr>
          <td><b>FORMA PAGAMENTO</b></td>
          <td class="right"><?= htmlspecialchars(mapTPag($tPag), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
          <td><b>VALOR PAGO R$</b></td>
          <td class="right"><?= $vPag ?></td>
        </tr>
        <?php if ($vTroco !== '0,00'): ?>
          <tr>
            <td><b>TROCO R$</b></td>
            <td class="right"><?= $vTroco ?></td>
          </tr>
        <?php endif; ?>
        <?php if ($vTrib !== '0,00'): ?>
          <tr>
            <td><b>TRIBUTOS APROX. R$</b></td>
            <td class="right"><?= $vTrib ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="hr"></div>

    <div class="small">
      Nº: <?= htmlspecialchars($nNF, ENT_QUOTES, 'UTF-8') ?>
      &nbsp; Série: <?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>
      &nbsp; Emissão: <?= htmlspecialchars($dhEmi, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="center small">
      <b>CHAVE DE ACESSO</b><br>
      <?= htmlspecialchars(fmtChave($chave), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="hr"></div>

    <div class="center small">
      <b>CONSUMIDOR</b><br>
      <?php
      $dN = strtoupper(trim($dest_nome));
      if (
        $dN !== '' &&
        $dN !== 'CONSUMIDOR FINAL' &&
        $dN !== 'CONSUMIDOR AVULSO' &&
        $dN !== 'CONSUMIDOR NÃO IDENTIFICADO'
      ):
      ?>
        <?= htmlspecialchars($dest_nome, ENT_QUOTES, 'UTF-8') ?><br>
        <?= htmlspecialchars($dest_doc, ENT_QUOTES, 'UTF-8') ?>
      <?php else: ?>
        <?= htmlspecialchars($dest_doc ?: 'CONSUMIDOR FINAL', ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </div>

    <div class="hr"></div>

    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode"></div>

    <div class="hr"></div>

    <?php if ($protInfo): ?>
      <div class="small center"><?= htmlspecialchars($protInfo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>

  <div class="actions">
    <button
      class="btn btn-secondary"
      onclick="if (window.history.length > 1) { window.history.back(); } else { window.close(); }"
      type="button">
      Voltar / Fechar
    </button>

    <a
      class="btn btn-secondary"
      href="danfe_a4.php?id=<?= urlencode($empresaIdUrl) ?>&venda_id=<?= (int)$vendaIdUrl ?>&chave=<?= urlencode((string)$chave) ?>"
      target="_blank">
      Modelo SEFAZ (A4)
    </a>

    <button class="btn btn-primary" type="button" onclick="window.print()">
      Imprimir
    </button>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    (function() {
      var qrTarget = document.getElementById('qrcode');
      var qrText = <?= json_encode($qrTxt ?: $urlChave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      if (qrTarget && qrText) {
        new QRCode(qrTarget, {
          text: qrText,
          width: 180,
          height: 180
        });
      }
    })();
  </script>
</body>

</html>