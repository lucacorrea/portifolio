<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?chave=NNNN...&venda_id=123&id=principal_1  OU  danfe_nfce.php?arq=procNFCe_...xml

ini_set('display_errors', 1);
error_reporting(E_ALL);
if(session_status() === PHP_SESSION_NONE) session_start();
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
if (!empty($_GET['arq'])) {
  $file = $base . basename((string)$_GET['arq']);
} elseif (!empty($_GET['chave'])) {
  $file = $base . 'procNFCe_' . preg_replace('/\D+/', '', (string)$_GET['chave']) . '.xml';
} else {
  die('Informe ?chave=... ou ?arq=procNFCe_....xml');
}
if (!is_file($file)) die('Arquivo não encontrado: ' . htmlspecialchars($file));

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
    :root { --ticket-max: 384px; --pad: 12px; --qr: 210px; --accent: #1a73e8; --ink: #111; --paper: #fff; --bg: #f5f7fb }
    *{box-sizing:border-box}
    body{margin:0; padding:0; background:var(--bg); color:var(--ink); font: 13px/1.45 monospace}
    .wrapper{width:100%; max-width:var(--ticket-max); margin:10px auto 92px; background:var(--paper); border-radius:12px; box-shadow:0 10px 28px rgba(0,0,0,.08); padding:var(--pad)}
    .center{text-align:center} .right{text-align:right} .small{font-size:11px}
    .hr{border-top:1px dashed #000; margin:8px 0}
    .tbl{width:100%; border-collapse:collapse; table-layout:fixed}
    .tbl th{border-bottom:1px dashed #000; padding:4px 0} .tbl td{padding:3px 0; vertical-align:top}
    .badge{display:inline-block; background:#eef2ff; color:#1f2937; padding:3px 6px; border-radius:6px; font-size:10px}
    .actions{position:fixed; left:0; right:0; bottom:0; padding:10px; background:#fff; border-top:1px solid #e5e7eb; display:flex; gap:10px; justify-content:center}
    .btn{border:0; border-radius:10px; padding:11px 16px; font-weight:600; cursor:pointer}
    .btn-primary{background:var(--accent); color:#fff} .btn-secondary{background:#e5e7eb; color:#111}
    #qrcode{display:block; margin:8px auto; width:var(--qr); height:var(--qr)}
    @media print{.actions{display:none} .wrapper{box-shadow:none; border-radius:0; margin:0; width:75mm}}
  </style>
</head>
<body>
<div class="wrapper">
    <header class="center">
      <h2><?= htmlspecialchars($emit_xFant ?: $emit_xNome) ?></h2>
      <div class="small">CNPJ: <?= htmlspecialchars($emit_CNPJ) ?> &middot; IE: <?= htmlspecialchars($emit_IE) ?><br><?= htmlspecialchars($end_txt) ?></div>
      <div class="hr"></div>
      <div class="small badge">NFC-e não permite aproveitamento de crédito de ICMS</div>
      <div class="hr"></div>
    </header>

    <table class="tbl small">
      <thead><tr><th class="left">Descrição</th><th class="right">Qtde</th><th class="right">V.Unit</th><th class="right">V.Total</th></tr></thead>
      <tbody>
        <?php foreach ($itens as $it): ?>
          <tr><td><?= htmlspecialchars($it['xProd']) ?></td><td class="right"><?= $it['qCom'] ?><br><?= $it['uCom'] ?></td><td class="right"><?= $it['vUn'] ?></td><td class="right"><?= $it['vTot'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="hr"></div>
    <table class="tbl small">
      <tbody>
        <tr><td><b>QTDE TOTAL DE ITENS</b></td><td class="right"><?= count($itens) ?></td></tr>
        <tr><td><b>VALOR TOTAL R$</b></td><td class="right"><?= $vNF ?></td></tr>
        <tr><td><b>FORMA PAGAMENTO</b></td><td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td></tr>
        <tr><td><b>VALOR PAGO R$</b></td><td class="right"><?= $vPag ?></td></tr>
        <?php if($vTroco != '0,00'): ?><tr><td><b>TROCO R$</b></td><td class="right"><?= $vTroco ?></td></tr><?php endif; ?>
      </tbody>
    </table>

    <div class="hr"></div>
    <div class="small">Nº: <?= $nNF ?> Série: <?= $serie ?> Emissão: <?= $dhEmi ?></div>
    <div class="center small"><b>CHAVE DE ACESSO</b><br><?= fmtChave($chave) ?></div>

    <div class="hr"></div>
    <div class="center small"><b>CONSUMIDOR</b><br><?= $dest_nome ?: 'CONSUMIDOR NÃO IDENTIFICADO' ?><br><?= $dest_doc ?></div>

    <div class="hr"></div>
    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode"></div>
    
    <div class="hr"></div>
    <?php if ($protInfo): ?><div class="small center"><?= htmlspecialchars($protInfo) ?></div><?php endif; ?>
</div>

<div class="actions">
    <button class="btn btn-secondary" onclick="window.history.back()">Voltar</button>
    <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById("qrcode"), { text: <?= json_encode($qrTxt) ?>, width: 210, height: 210 });
</script>
</body>
</html>