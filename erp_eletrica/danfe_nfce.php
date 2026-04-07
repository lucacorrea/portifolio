<?php
// danfe_nfce.php — DANFE NFC-e 80mm para erp_eletrica
// Uso: danfe_nfce.php?venda_id=123   ou   danfe_nfce.php?chave=XXXX(44 dígitos)
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');

require_once __DIR__ . '/autoloader.php';

$db = \App\Config\Database::getInstance()->getConnection();

$vendaId  = (int)($_GET['venda_id'] ?? 0);
$chaveReq = preg_replace('/\D+/', '', (string)($_GET['chave'] ?? ''));

// ── 1. Try to load XML from notas_fiscais table ──────────────────────────────
$xmlRaw = null;
$nfRecord = null;
try {
    if (strlen($chaveReq) === 44 || $vendaId > 0) {
        if (strlen($chaveReq) === 44) {
            $where = "chave_acesso = ?";
            $param = $chaveReq;
        } else {
            $where = "venda_id = ?";
            $param = $vendaId;
        }
        $st = $db->prepare("SELECT * FROM notas_fiscais WHERE $where ORDER BY id DESC LIMIT 1");
        $st->execute([$param]);
        $nfRecord = $st->fetch(PDO::FETCH_ASSOC);
        if ($nfRecord && !empty($nfRecord['xml_path'])) {
            $fullPath = __DIR__ . '/storage/' . ltrim($nfRecord['xml_path'], '/');
            if (is_file($fullPath)) {
                $xmlRaw = file_get_contents($fullPath);
            }
        }
    }
} catch (Throwable $e) { /* silencioso */ }

// ── 2. If no XML, render a simple confirmation page with sale data ─────────
if (!$xmlRaw) {
    // Fetch sale data as fallback display
    $venda = null; $itens = [];
    if ($vendaId > 0) {
        $sv = $db->prepare("
            SELECT v.*, COALESCE(c.nome, v.nome_cliente_avulso,'Consumidor Final') as cliente_nome,
                   f.nome as filial_nome, f.cnpj as filial_cnpj, f.endereco as filial_endereco,
                   f.cidade as filial_cidade, f.uf as filial_uf
            FROM vendas v LEFT JOIN clientes c ON v.cliente_id=c.id LEFT JOIN filiais f ON v.filial_id=f.id
            WHERE v.id=?
        ");
        $sv->execute([$vendaId]);
        $venda = $sv->fetch(PDO::FETCH_ASSOC);
        if ($venda) {
            $si = $db->prepare("SELECT vi.*,p.nome,p.codigo,p.unidade FROM vendas_itens vi JOIN produtos p ON vi.produto_id=p.id WHERE vi.venda_id=?");
            $si->execute([$vendaId]);
            $itens = $si->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $statusNF = $nfRecord ? ($nfRecord['status'] ?? 'pendente') : 'sem_registro';
    $chaveNF  = $nfRecord ? ($nfRecord['chave_acesso'] ?? '') : '';
    $protNF   = $nfRecord ? ($nfRecord['protocolo'] ?? '') : '';
    ?>
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>NFC-e #<?= $vendaId ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{box-sizing:border-box;} body{font:13px/1.45 monospace;margin:0;padding:0;background:#f5f7fb;}
        .wrapper{max-width:384px;margin:10px auto 90px;background:#fff;border-radius:12px;box-shadow:0 10px 28px rgba(0,0,0,.08);padding:14px;}
        .center{text-align:center;} .right{text-align:right;} .left{text-align:left;}
        .small{font-size:11px;} .hr{border-top:1px dashed #000;margin:8px 0;}
        .tbl{width:100%;border-collapse:collapse;table-layout:fixed;}
        .tbl thead th{border-bottom:1px dashed #000;font-weight:700;padding:4px 0;}
        .tbl td{padding:3px 0;vertical-align:top;}
        .badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;}
        .badge-ok{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .badge-pend{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;}
        .badge-rej{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .actions{position:fixed;left:0;right:0;bottom:0;z-index:50;padding:10px;background:#fff;border-top:1px solid #e5e7eb;display:flex;gap:10px;justify-content:center;}
        .btn{appearance:none;border:0;border-radius:10px;padding:11px 20px;font-family:system-ui,sans-serif;font-weight:600;cursor:pointer;font-size:14px;}
        .btn-primary{background:#2563eb;color:#fff;} .btn-secondary{background:#6b7280;color:#fff;}
        @page{size:80mm auto;margin:3mm;}
        @media print{body{background:#fff;} .wrapper{box-shadow:none;border-radius:0;margin:0;max-width:unset;width:75mm;padding:0;} .actions{display:none;}}
    </style></head><body>
    <div class="wrapper">
        <div class="center" style="font-size:15px;font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($venda['filial_nome'] ?? 'ERP Elétrica') ?></div>
        <?php if (!empty($venda['filial_cnpj'])): ?>
        <div class="small center">CNPJ: <?= htmlspecialchars($venda['filial_cnpj']) ?></div>
        <?php endif; ?>
        <div class="hr"></div>
        <div class="center">
            <?php if ($statusNF === 'autorizada'): ?>
                <span class="badge badge-ok">✓ NFC-e AUTORIZADA</span>
            <?php elseif ($statusNF === 'sem_registro'): ?>
                <span class="badge badge-pend">⏳ NFC-e NÃO EMITIDA</span>
            <?php else: ?>
                <span class="badge badge-pend"><?= strtoupper($statusNF) ?></span>
            <?php endif; ?>
        </div>
        <div class="hr"></div>
        <div class="small">Venda Nº: <b>#<?= $vendaId ?></b></div>
        <?php if ($chaveNF): ?><div class="small">Chave: <span style="word-break:break-all;"><?= chunk_split($chaveNF, 4, ' ') ?></span></div><?php endif; ?>
        <?php if ($protNF):  ?><div class="small">Protocolo: <?= htmlspecialchars($protNF) ?></div><?php endif; ?>
        <div class="hr"></div>
        <?php if ($venda): ?>
        <table class="tbl small">
            <colgroup><col style="width:45%"><col style="width:10%"><col style="width:10%"><col style="width:17%"><col style="width:18%"></colgroup>
            <thead><tr><th class="left">Produto</th><th class="right">Qtd</th><th>Un</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($itens as $it): $sub = $it['quantidade']*$it['preco_unitario']; ?>
            <tr>
                <td class="left"><?= htmlspecialchars(mb_strimwidth($it['nome'],0,20,'..')) ?></td>
                <td class="right"><?= number_format($it['quantidade'],2,',','.') ?></td>
                <td class="center"><?= htmlspecialchars($it['unidade']??'UN') ?></td>
                <td class="right"><?= number_format($it['preco_unitario'],2,',','.') ?></td>
                <td class="right"><?= number_format($sub,2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="hr"></div>
        <table class="tbl small"><tbody>
            <?php if ($venda['desconto_total']>0): ?>
            <tr><td><b>DESCONTO</b></td><td class="right">- R$ <?= number_format($venda['desconto_total'],2,',','.') ?></td></tr>
            <?php endif; ?>
            <tr><td style="font-size:14px;"><b>TOTAL R$</b></td><td class="right" style="font-size:14px;"><b><?= number_format($venda['valor_total'],2,',','.') ?></b></td></tr>
        </tbody></table>
        <div class="hr"></div>
        <div class="small center">
            <?php 
            $cName = strtoupper(trim($venda['cliente_nome']));
            if ($cName !== 'CONSUMIDOR FINAL' && $cName !== 'CONSUMIDOR AVULSO' && $cName !== 'CONSUMIDOR'): ?>
                Cliente: <b><?= htmlspecialchars($venda['cliente_nome']) ?></b>
                <?php if (!empty($venda['cpf_cliente'])): ?><br>CPF: <?= htmlspecialchars($venda['cpf_cliente']) ?><?php endif; ?>
            <?php elseif (!empty($venda['cpf_cliente'])): ?>
                <b>CPF: <?= htmlspecialchars($venda['cpf_cliente']) ?></b>
            <?php else: ?>
                <b>Consumidor Final</b>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="hr"></div>
        <?php if ($statusNF === 'sem_registro' || $statusNF === 'pendente'): ?>
        <div class="small center" style="color:#b45309;">
            ⚠️ XML da NFC-e ainda não está disponível.<br>
            Verifique as configurações SEFAZ e tente emitir novamente.
        </div>
        <?php else: ?>
        <div class="small center" style="color:#6b7280;">Consulta via portal SEFAZ do seu estado.</div>
        <?php endif; ?>
    </div>
    <div class="actions">
        <button class="btn btn-secondary" onclick="window.close()">← Fechar</button>
        <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
    </div>
    <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),600));</script>
    </body></html>
    <?php
    exit;
}

// ── 3. Render full DANFE from XML ─────────────────────────────────────────────
function br_($v)    { return number_format((float)$v, 2, ',', '.'); }
function lm_($s)    { return trim((string)$s); }
function fmtCh($ch) { $ch = preg_replace('/\D+/', '', $ch); return trim(implode(' ', str_split($ch, 4))); }
function tPag_($t)  {
    $k = str_pad(preg_replace('/\D+/', '', (string)$t), 2, '0', STR_PAD_LEFT);
    $m = ['01'=>'Dinheiro','02'=>'Cheque','03'=>'Cartão de Crédito','04'=>'Cartão de Débito','05'=>'Crédito Loja',
          '15'=>'Boleto','16'=>'Depósito','17'=>'PIX','90'=>'Sem Pagamento','99'=>'Outros'];
    return $m[$k] ?? 'Outros';
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($xmlRaw, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NONET);
libxml_clear_errors();

$ns = 'http://www.portalfiscal.inf.br/nfe';
$infNFe = $dom->getElementsByTagNameNS($ns,'infNFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($ns,'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($ns,'protNFe')->item(0);
$emit   = $dom->getElementsByTagNameNS($ns,'emit')->item(0);
$emit_xNome = $emit ? lm_($emit->getElementsByTagNameNS($ns,'xNome')->item(0)->nodeValue) : '';
$emit_xFant = ($emit && $emit->getElementsByTagNameNS($ns,'xFant')->item(0)) ? lm_($emit->getElementsByTagNameNS($ns,'xFant')->item(0)->nodeValue) : '';
$emit_CNPJ  = $emit ? lm_($emit->getElementsByTagNameNS($ns,'CNPJ')->item(0)->nodeValue) : '';
$emit_IE    = ($emit && $emit->getElementsByTagNameNS($ns,'IE')->item(0)) ? lm_($emit->getElementsByTagNameNS($ns,'IE')->item(0)->nodeValue) : '';
$enderEmit  = $emit ? $emit->getElementsByTagNameNS($ns,'enderEmit')->item(0) : null;
$end_txt = '';
if ($enderEmit) {
    $g = fn($t) => ($x=$enderEmit->getElementsByTagNameNS($ns,$t)->item(0)) ? lm_($x->nodeValue) : '';
    $end_txt = $g('xLgr').' '.$g('nro').', '.$g('xBairro').', '.$g('xMun').' - '.$g('UF');
}
$ide   = $dom->getElementsByTagNameNS($ns,'ide')->item(0);
$serie = $ide ? lm_($ide->getElementsByTagNameNS($ns,'serie')->item(0)->nodeValue) : '';
$nNF   = $ide ? lm_($ide->getElementsByTagNameNS($ns,'nNF')->item(0)->nodeValue) : '';
$dhEmi = $ide ? lm_($ide->getElementsByTagNameNS($ns,'dhEmi')->item(0)->nodeValue) : '';
$idAttr= $infNFe ? $infNFe->getAttribute('Id') : '';
$chave = preg_replace('/^NFe/', '', $idAttr);
$total   = $dom->getElementsByTagNameNS($ns,'ICMSTot')->item(0);
$vProd = $total ? br_($total->getElementsByTagNameNS($ns,'vProd')->item(0)->nodeValue) : '0,00';
$vDesc = ($total && $total->getElementsByTagNameNS($ns,'vDesc')->item(0)) ? br_($total->getElementsByTagNameNS($ns,'vDesc')->item(0)->nodeValue) : '0,00';
$vNF   = $total ? br_($total->getElementsByTagNameNS($ns,'vNF')->item(0)->nodeValue) : '0,00';
$vTrib = ($total && $total->getElementsByTagNameNS($ns,'vTotTrib')->item(0)) ? br_($total->getElementsByTagNameNS($ns,'vTotTrib')->item(0)->nodeValue) : '0,00';
$detPag= $dom->getElementsByTagNameNS($ns,'detPag')->item(0);
$tPag  = $detPag ? lm_($detPag->getElementsByTagNameNS($ns,'tPag')->item(0)->nodeValue) : '';
$vPag  = $detPag ? br_($detPag->getElementsByTagNameNS($ns,'vPag')->item(0)->nodeValue) : '0,00';
$vTroco= $dom->getElementsByTagNameNS($ns,'vTroco')->item(0);
$vTroco= $vTroco ? br_($vTroco->nodeValue) : '0,00';
$dest  = $dom->getElementsByTagNameNS($ns,'dest')->item(0);
$dest_doc  = '';
$dest_nome = '';
if ($dest) {
    $dC = $dest->getElementsByTagNameNS($ns, 'CNPJ')->item(0);
    $dF = $dest->getElementsByTagNameNS($ns, 'CPF')->item(0);
    $dN = $dest->getElementsByTagNameNS($ns, 'xNome')->item(0);
    $dest_doc  = $dC ? 'CNPJ: '.lm_($dC->nodeValue) : ($dF ? 'CPF: '.lm_($dF->nodeValue) : '');
    $dest_nome = $dN ? lm_($dN->nodeValue) : '';
}
$protInfo = '';
if ($prot) {
    $infP = $prot->getElementsByTagNameNS('*', 'infProt')->item(0);
    $nProtTag= ($infP && $infP->getElementsByTagNameNS('*', 'nProt')->item(0)) ? $infP->getElementsByTagNameNS('*', 'nProt')->item(0) : null;
    $dhRecTag= ($infP && $infP->getElementsByTagNameNS('*', 'dhRecbto')->item(0)) ? $infP->getElementsByTagNameNS('*', 'dhRecbto')->item(0) : null;
    $nProt = $nProtTag ? lm_($nProtTag->nodeValue) : '';
    $dhRec = $dhRecTag ? lm_($dhRecTag->nodeValue) : '';
    $protInfo = $nProt ? "Protocolo: $nProt — $dhRec" : '';
}
$qrTxt = ($supl && $supl->getElementsByTagNameNS($ns,'qrCode')->item(0)) ? lm_($supl->getElementsByTagNameNS($ns,'qrCode')->item(0)->nodeValue) : '';
$itens = [];
foreach ($dom->getElementsByTagNameNS($ns,'det') as $det) {
    $prod = $det->getElementsByTagNameNS($ns,'prod')->item(0);
    if (!$prod) continue;
    $g   = fn($t) => lm_($prod->getElementsByTagNameNS($ns,$t)->item(0)->nodeValue);
    $itens[] = [
        'cProd'=>$g('cProd'), 'xProd'=>$g('xProd'),
        'qCom' =>number_format((float)$prod->getElementsByTagNameNS($ns,'qCom')->item(0)->nodeValue,3,',','.'),
        'uCom' =>$g('uCom'),
        'vUn'  =>br_($prod->getElementsByTagNameNS($ns,'vUnCom')->item(0)->nodeValue),
        'vTot' =>br_($prod->getElementsByTagNameNS($ns,'vProd')->item(0)->nodeValue),
    ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <style>
        :root{--ticket-max:384px;--pad:12px;--accent:#1a73e8;--ink:#111;--paper:#fff;--bg:#f5f7fb}
        *{box-sizing:border-box;-webkit-font-smoothing:antialiased;}
        html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);-webkit-text-size-adjust:100%}
        body{font:13px/1.45 monospace}
        .wrapper{width:100%;max-width:var(--ticket-max);margin:10px auto 92px;background:var(--paper);border-radius:12px;box-shadow:0 10px 28px rgba(0,0,0,.08);padding:var(--pad)}
        header h2{font-size:14px;margin:4px 0 2px;text-transform:uppercase}
        .small{font-size:11px;color:#111} .hr{border-top:1px dashed #000;margin:8px 0}
        .tbl{width:100%;border-collapse:collapse;table-layout:fixed}
        .tbl thead th{border-bottom:1px dashed #000;font-weight:700;padding:4px 0}
        .tbl td{padding:3px 0;vertical-align:top}
        .left{text-align:left} .right{text-align:right} .center{text-align:center}
        .key{letter-spacing:1px;word-spacing:4px}
        .qr{display:block;margin:8px auto;width:min(210px,calc(100% - 2*var(--pad)));height:auto;aspect-ratio:1/1}
        .badge{display:inline-block;background:#eef2ff;color:#1f2937;padding:3px 6px;border-radius:6px;font-size:10px}
        .actions{position:fixed;left:0;right:0;bottom:0;z-index:50;padding:10px env(safe-area-inset-right) calc(10px + env(safe-area-inset-bottom)) env(safe-area-inset-left);background:#fff;border-top:1px solid #e5e7eb;display:flex;gap:10px;justify-content:center}
        .btn{appearance:none;border:0;border-radius:10px;padding:11px 16px;font-family:system-ui,sans-serif;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap}
        .btn-primary{background:var(--accent);color:#fff} .btn-secondary{background:#6b7280;color:#fff}
        @page{size:80mm auto;margin:3mm}
        @media print{html,body{background:#fff} .wrapper{box-shadow:none;border-radius:0;margin:0;max-width:unset;width:75mm;padding:0} .actions{display:none} .qr{width:210px;height:210px}}
    </style>
</head>
<body>
<div class="wrapper" role="document" aria-label="DANFE NFC-e">
    <header class="center">
        <h2><?= htmlspecialchars($emit_xFant ?: $emit_xNome) ?></h2>
        <div class="small">CNPJ: <?= htmlspecialchars($emit_CNPJ) ?> &nbsp; IE: <?= htmlspecialchars($emit_IE) ?></div>
        <div class="small"><?= htmlspecialchars($end_txt) ?></div>
        <div class="hr"></div>
        <div class="small badge">NFC-e não permite aproveitamento de crédito de ICMS</div>
        <div class="hr"></div>
    </header>

    <table class="tbl small" aria-label="Itens">
        <colgroup><col style="width:16%"><col style="width:42%"><col style="width:10%"><col style="width:8%"><col style="width:12%"><col style="width:12%"></colgroup>
        <thead><tr><th class="left">Cód</th><th class="left">Descrição</th><th class="right">Qtde</th><th class="right">Un</th><th class="right">V.Unit</th><th class="right">V.Total</th></tr></thead>
        <tbody>
            <?php
    $itens = $dom->getElementsByTagNameNS($ns, 'det');
    foreach($itens as $item):
        $prod = $item->getElementsByTagNameNS($ns, 'prod')->item(0);
        if(!$prod) continue;
        $cProd = $prod->getElementsByTagNameNS($ns, 'cProd')->item(0)->nodeValue;
        $xProd = $prod->getElementsByTagNameNS($ns, 'xProd')->item(0)->nodeValue;
        $qCom  = $prod->getElementsByTagNameNS($ns, 'qCom')->item(0)->nodeValue;
        $uCom  = $prod->getElementsByTagNameNS($ns, 'uCom')->item(0)->nodeValue;
        $vUn   = $prod->getElementsByTagNameNS($ns, 'vUnCom')->item(0)->nodeValue;
        $vTot  = $prod->getElementsByTagNameNS($ns, 'vProd')->item(0)->nodeValue;
    ?>
    <tr>
        <td class="left small"><?= htmlspecialchars($cProd) ?><br><?= htmlspecialchars(lm_($xProd)) ?></td>
        <td class="right small"><?= number_format($qCom, 3, ',', '.') ?> <?= htmlspecialchars(lm_($uCom)) ?></td>
        <td class="right small"><?= br_($vUn) ?></td>
        <td class="right small"><?= br_($vTot) ?></td>
    </tr>
    <?php endforeach; ?>
        </tbody>
    </table>

    <div class="hr"></div>

    <table class="tbl small" aria-label="Totais">
        <tbody>
            <?php
    $vProd = $total->getElementsByTagNameNS($ns, 'vProd')->item(0);
    $vDesc = $total->getElementsByTagNameNS($ns, 'vDesc')->item(0);
    $vNF   = $total->getElementsByTagNameNS($ns, 'vNF')->item(0);
    ?>
    <tr><td class="left">VALOR TOTAL BRUTO</td><td class="right"><?= $vProd ? br_($vProd->nodeValue) : '0,00' ?></td></tr>
    <tr><td class="left">DESCONTO</td><td class="right">- <?= $vDesc ? br_($vDesc->nodeValue) : '0,00' ?></td></tr>
    <tr><td class="left"><b>VALOR TOTAL LÍQUIDO</b></td><td class="right"><b><?= $vNF ? br_($vNF->nodeValue) : '0,00' ?></b></td></tr>
            <tr><td class="left"><b>FORMA DE PAGAMENTO</b></td><td class="right"><?= htmlspecialchars(tPag_($tPag)) ?></td></tr>
            <tr><td class="left"><b>VALOR PAGO</b></td><td class="right"><?= $vPag ?></td></tr>
            <?php if ($vTroco !== '0,00'): ?><tr><td class="left"><b>TROCO</b></td><td class="right"><?= $vTroco ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php if ($vTrib !== '0,00'): ?>
    <div class="small">Inf. dos Tributos Totais (Lei 12.741/2012): R$ <?= $vTrib ?></div>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="small">Nº: <?= htmlspecialchars($nNF) ?> &nbsp; Série: <?= htmlspecialchars($serie) ?> &nbsp; Emissão: <?= htmlspecialchars($dhEmi) ?></div>
    <div class="center" style="margin-top:6px">
        <div class="small"><b>CHAVE DE ACESSO</b></div>
        <div class="key small"><?= fmtCh($chave) ?></div>
    </div>
    <div class="hr"></div>
    <div class="small"><b>CONSUMIDOR</b></div>
    <?php 
    $dN = strtoupper(trim($dest_nome));
    if ($dest_nome && $dN !== 'CONSUMIDOR FINAL' && $dN !== 'CONSUMIDOR AVULSO' && $dN !== 'CONSUMIDOR'): ?>
        <div class="small"><?= htmlspecialchars($dest_nome) ?></div>
        <div class="small"><?= htmlspecialchars($dest_doc ?: '—') ?></div>
    <?php else: ?>
        <div class="small"><?= htmlspecialchars($dest_doc ?: 'CONSUMIDOR FINAL') ?></div>
    <?php endif; ?>
    <div class="hr"></div>
    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode" class="qr" role="img" aria-label="QR Code da NFC-e"></div>
    <div class="hr"></div>
    <?php if ($protInfo): ?><div class="small"><?= htmlspecialchars($protInfo) ?></div><?php endif; ?>
</div>

<div class="actions">
    <button class="btn btn-secondary" onclick="window.close()">← Fechar</button>
    <button id="btn-print" class="btn btn-primary">🖨️ Imprimir</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
    var el  = document.getElementById('qrcode');
    var txt = <?= json_encode($qrTxt, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    function buildQR(){
        el.innerHTML = '';
        if (!txt) return;
        try {
            var w = Math.min(210, Math.max(140, el.clientWidth||180));
            if (window.QRCode) new QRCode(el, {text:txt, width:w, height:w, correctLevel:QRCode.CorrectLevel.M});
        } catch(e){}
    }
    window.addEventListener('load', function(){ buildQR(); setTimeout(()=>window.print(),800); });
    window.addEventListener('resize', function(){ clearTimeout(window.__qrR); window.__qrR=setTimeout(buildQR,120); });
    document.getElementById('btn-print').addEventListener('click', ()=>window.print());
})();
</script>
</body>
</html>
