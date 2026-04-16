<?php
// danfe_a4.php — DANFE A4 Premium (Total Standard)
// Uso: danfe_a4.php?id=<emp_id>&venda_id=123&chave=...

ini_set('display_errors', 0);
error_reporting(E_ALL);
if(session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';

/* ====================== Carregamento do XML ====================== */
$chaveReq = preg_replace('/\D+/', '', (string)($_GET['chave'] ?? ''));
$vendaId  = (int)($_GET['venda_id'] ?? 0);
$arqReq   = !empty($_GET['arq']) ? basename((string)$_GET['arq']) : null;

$xmlRaw = null;
$file = null;

if ($arqReq) {
    $file = __DIR__ . '/' . $arqReq;
} elseif ($chaveReq && strlen($chaveReq) === 44) {
    $file = __DIR__ . '/procNFCe_' . $chaveReq . '.xml';
    if (!is_file($file)) {
        $file = dirname(__DIR__) . '/nfce/procNFCe_' . $chaveReq . '.xml';
    }
}

if (!is_file($file)) {
    try {
        $st = $pdo->prepare("SELECT xml_nfeproc FROM nfce_emitidas WHERE (chave = :ch OR venda_id = :v) AND xml_nfeproc IS NOT NULL ORDER BY id DESC LIMIT 1");
        $st->execute([':ch' => $chaveReq, ':v' => $vendaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) $xmlRaw = $row['xml_nfeproc'];
    } catch (Throwable $e) {}
} else {
    $xmlRaw = file_get_contents($file);
}

if (!$xmlRaw) die('XML da nota não encontrado ou ainda não processado.');

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($xmlRaw);
libxml_clear_errors();

$nfeNS = 'http://www.portalfiscal.inf.br/nfe';
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('n', $nfeNS);

/* ====================== Funções Auxiliares ====================== */
$val = function($q, $node = null) use ($xpath) {
    $n = $xpath->query($q, $node);
    return ($n && $n->length) ? trim($n->item(0)->nodeValue) : '';
};
$br = fn($v) => number_format((float)$v, 2, ',', '.');
$fmtChave = fn($ch) => trim(implode(' ', str_split(preg_replace('/\D+/', '', $ch), 4)));

// Gerador de Barcode 128 Estático (Simplificado para DANFE)
function barcode128($text) {
    $chars = array(
        "00"=>"212222","01"=>"222122","02"=>"222221","03"=>"121223","04"=>"121322","05"=>"131222","06"=>"122213","07"=>"122312","08"=>"132212","09"=>"221213",
        "10"=>"221312","11"=>"231212","12"=>"112232","13"=>"122132","14"=>"122231","15"=>"113222","16"=>"123122","17"=>"123221","18"=>"223211","19"=>"221132",
        "20"=>"221231","21"=>"213212","22"=>"223112","23"=>"312131","24"=>"311222","25"=>"321122","26"=>"321221","27"=>"312212","28"=>"322112","29"=>"322211",
        "30"=>"212123","31"=>"212321","32"=>"232121","33"=>"111323","34"=>"131123","35"=>"131321","36"=>"112313","37"=>"132113","38"=>"132311","39"=>"211312",
        "40"=>"231112","41"=>"231311","42"=>"112133","43"=>"112331","44"=>"132131","45"=>"113123","46"=>"113321","47"=>"133121","48"=>"313121","49"=>"211331",
        "50"=>"231131","51"=>"213113","52"=>"213311","53"=>"213131","54"=>"311123","55"=>"311321","56"=>"331121","57"=>"312113","58"=>"312311","59"=>"332111",
        "60"=>"314111","61"=>"221411","62"=>"431111","63"=>"111224","64"=>"111422","65"=>"121124","66"=>"121421","67"=>"141122","68"=>"141221","69"=>"112214",
        "70"=>"112412","71"=>"122114","72"=>"122411","73"=>"142112","74"=>"142211","75"=>"241211","76"=>"221114","77"=>"413111","78"=>"241112","79"=>"134111",
        "80"=>"111242","81"=>"121142","82"=>"121241","83"=>"114212","84"=>"124112","85"=>"124211","86"=>"411212","87"=>"421112","88"=>"421211","89"=>"212141",
        "90"=>"214121","91"=>"412121","92"=>"111143","93"=>"111341","94"=>"131141","95"=>"114113","96"=>"114311","97"=>"411113","98"=>"411311","99"=>"113141",
        "A"=>"211412","B"=>"211214","C"=>"211232","D"=>"2331112"
    );
    $text = preg_replace('/\D+/', '', $text);
    $code = "211214"; // Start C
    $check = 105;
    for ($i=0, $pos=1; $i<strlen($text); $i+=2, $pos++) {
        $val = substr($text, $i, 2);
        $code .= $chars[$val];
        $check += ((int)$val * $pos);
    }
    $code .= $chars[str_pad($check % 103, 2, "0", STR_PAD_LEFT)];
    $code .= "2331112"; // Stop
    
    $svg = '<svg width="100%" height="40" viewBox="0 0 '.(strlen($code)*2).' 40" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">';
    $x = 0;
    for ($i=0; $i<strlen($code); $i++) {
        $w = (int)$code[$i];
        if ($i % 2 == 0) $svg .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="40" fill="#000" />';
        $x += $w;
    }
    $svg .= '</svg>';
    return $svg;
}

/* -------------------------- Dados Gerais -------------------------- */
// Busca logo do banco
$logoPath = 'logo_sistema_erp_eletrica.png';
try {
    $stL = $pdo->prepare("SELECT logo_path FROM filiais WHERE principal = 1 LIMIT 1");
    $stL->execute();
    $lRow = $stL->fetch();
    if ($lRow && !empty($lRow['logo_path'])) {
        $logoPath = 'storage/logos/' . $lRow['logo_path'];
        if (!is_file(dirname(__DIR__) . '/' . $logoPath)) $logoPath = 'logo_sistema_erp_eletrica.png';
    }
} catch (Throwable $e) {}

$emit = [
    'xNome' => $val('//n:emit/n:xNome'),
    'xFant' => $val('//n:emit/n:xFant'),
    'CNPJ'  => $val('//n:emit/n:CNPJ'),
    'IE'    => $val('//n:emit/n:IE'),
    'IEST'  => $val('//n:emit/n:IEST'),
    'xLgr'  => $val('//n:emit/n:enderEmit/n:xLgr'),
    'nro'   => $val('//n:emit/n:enderEmit/n:nro'),
    'xCpl'  => $val('//n:emit/n:enderEmit/n:xCpl'),
    'xBairro' => $val('//n:emit/n:enderEmit/n:xBairro'),
    'xMun'  => $val('//n:emit/n:enderEmit/n:xMun'),
    'UF'    => $val('//n:emit/n:enderEmit/n:UF'),
    'CEP'   => $val('//n:emit/n:enderEmit/n:CEP'),
    'fone'  => $val('//n:emit/n:enderEmit/n:fone')
];

$ide = [
    'nNF'    => $val('//n:ide/n:nNF'),
    'serie'  => $val('//n:ide/n:serie'),
    'tpNF'   => $val('//n:ide/n:tpNF'), 
    'natOp'  => $val('//n:ide/n:natOp'),
    'dhEmi'  => $val('//n:ide/n:dhEmi') ?: $val('//n:ide/n:dhEmis'),
    'chave'  => preg_replace('/^NFe/', '', $dom->getElementsByTagName('infNFe')->item(0)->getAttribute('Id'))
];

$dest = [
    'xNome' => $val('//n:dest/n:xNome'),
    'doc'   => $val('//n:dest/n:CNPJ') ?: $val('//n:dest/n:CPF'),
    'docT'  => $val('//n:dest/n:CNPJ') ? 'CNPJ' : 'CPF',
    'IE'    => $val('//n:dest/n:IE'),
    'xLgr'  => $val('//n:dest/n:enderDest/n:xLgr'),
    'nro'   => $val('//n:dest/n:enderDest/n:nro'),
    'xCpl'  => $val('//n:dest/n:enderDest/n:xCpl'),
    'xBairro' => $val('//n:dest/n:enderDest/n:xBairro'),
    'xMun'  => $val('//n:dest/n:enderDest/n:xMun'),
    'UF'    => $val('//n:dest/n:enderDest/n:UF'),
    'CEP'   => $val('//n:dest/n:enderDest/n:CEP'),
    'fone'  => $val('//n:dest/n:enderDest/n:fone'),
];

$tot = [
    'vBC'     => $br($val('//n:ICMSTot/n:vBC')),
    'vICMS'   => $br($val('//n:ICMSTot/n:vICMS')),
    'vBCST'   => $br($val('//n:ICMSTot/n:vBCST')),
    'vST'     => $br($val('//n:ICMSTot/n:vST')),
    'vProd'   => $br($val('//n:ICMSTot/n:vProd')),
    'vFrete'  => $br($val('//n:ICMSTot/n:vFrete')),
    'vSeg'    => $br($val('//n:ICMSTot/n:vSeg')),
    'vDesc'   => $br($val('//n:ICMSTot/n:vDesc')),
    'vOutro'  => $br($val('//n:ICMSTot/n:vOutro')),
    'vIPI'    => $br($val('//n:ICMSTot/n:vIPI')),
    'vNF'     => $br($val('//n:ICMSTot/n:vNF')),
    'vTrib'   => $br($val('//n:ICMSTot/n:vTotTrib'))
];

$prot = [
    'nProt' => $val('//n:protNFe/n:infProt/n:nProt'),
    'dhRec' => $val('//n:protNFe/n:infProt/n:dhRecbto')
];

$transp = [
    'modFrete' => $val('//n:transp/n:modFrete'),
    'xNome'    => $val('//n:transp/n:transporta/n:xNome'),
    'doc'      => $val('//n:transp/n:transporta/n:CNPJ') ?: $val('//n:transp/n:transporta/n:CPF'),
    'IE'       => $val('//n:transp/n:transporta/n:IE'),
    'xEnder'   => $val('//n:transp/n:transporta/n:xEnder'),
    'xMun'     => $val('//n:transp/n:transporta/n:xMun'),
    'UF'       => $val('//n:transp/n:transporta/n:UF'),
    'placa'    => $val('//n:transp/n:veicTransp/n:placa'),
    'UFPlaca'  => $val('//n:transp/n:veicTransp/n:UF')
];

// Duplicatas
$dups = [];
foreach($xpath->query('//n:cobr/n:dup') as $d) {
    if (!$d) continue;
    $dups[] = [
        'nDup' => $val('n:nDup', $d),
        'dVenc' => $val('n:dVenc', $d) ? date('d/m/Y', strtotime($val('n:dVenc', $d))) : '',
        'vDup' => $br($val('n:vDup', $d))
    ];
}

$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
    $p = $det->getElementsByTagName('prod')->item(0);
    $i = $det->getElementsByTagName('imposto')->item(0);
    $icms = $i->getElementsByTagName('ICMS')->item(0);
    $icmsX = $icms ? $icms->firstChild : null;

    $vProd = (float)$val('.//n:vProd', $p);
    $vDesItem = (float)$val('.//n:vDesc', $p);

    $itens[] = [
        'cProd'  => $val('.//n:cProd', $p),
        'xProd'  => $val('.//n:xProd', $p),
        'NCM'    => $val('.//n:NCM', $p),
        'CST'    => $val('.//n:CST', $icmsX) ?: $val('.//n:CSOSN', $icmsX),
        'CFOP'   => $val('.//n:CFOP', $p),
        'uCom'   => $val('.//n:uCom', $p),
        'qCom'   => number_format((float)$val('.//n:qCom', $p), 4, ',', '.'),
        'vUn'    => $br($val('.//n:vUnCom', $p)),
        'vTot'   => $br($vProd),
        'vDesc'  => $br($vDesItem),
        'vLiq'   => $br($vProd - $vDesItem),
        'vBC'    => $br($val('.//n:vBC', $icmsX)),
        'vICMS'  => $br($val('.//n:vICMS', $icmsX)),
        'vIPI'   => $br($val('.//n:vIPI', $i)),
        'pICMS'  => $br($val('.//n:pICMS', $icmsX)),
        'pIPI'   => $br($val('.//n:pIPI', $i))
    ];
}

$infAdic = $val('//n:infAdic/n:infCpl');
$qrTxt   = $val('//n:infNFeSupl/n:qrCode');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFE - <?= $ide['nNF'] ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0px; background: #e0e0e0; color: #000; font-size: 8px; min-width: 21cm; }
        .page { width: 21cm; height: 29.7cm; padding: 10mm; background: #fff; margin: 10px auto; box-shadow: 0 0 15px rgba(0,0,0,0.2); position: relative; box-sizing: border-box; overflow: hidden; }
        
        .box { border: 1px solid #000; margin-bottom: -1px; margin-right: -1px; position: relative; }
        .flex { display: flex; width: 100%; }
        .f1 { flex: 1; } .f2 { flex: 2; } .f3 { flex: 3; } .f4 { flex: 4; } .f5 { flex: 5; }
        .center { text-align: center; } .right { text-align: right; } .bold { font-weight: bold; }
        .title { font-size: 6px; font-weight: bold; text-transform: uppercase; padding: 1px 3px; display: block; border-bottom: 0px; line-height: 1; }
        .val { font-size: 8px; padding: 1px 4px; min-height: 10px; text-transform: uppercase; word-break: break-all; }
        .section-title { font-size: 8px; font-weight: bold; margin: 5px 0 2px 0; border-bottom: 1px solid #000; padding-bottom: 2px; }
        
        /* Header Fix */
        .header-main { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2px; }
        .header-main td { border: 1px solid #000; vertical-align: top; padding: 2px; overflow: hidden; }
        .emit-logo { width: 85px; text-align: center; vertical-align: middle !important; }
        .emit-info { font-size: 8px; line-height: 1.1; }
        .danfe-box { width: 85px; text-align: center; }
        .barcode-box { width: 220px; text-align: center; vertical-align: middle !important; }
        .barcode { font-size: 28px; letter-spacing: 2px; display: block; margin: 5px 0; font-weight: normal; border: 1px dashed #ccc; padding: 5px; }

        .footer-pinned {
            position: absolute;
            bottom: 10mm;
            left: 10mm;
            right: 10mm;
            z-index: 10;
        }
        
        /* Tables */
        .table { width: 100%; border-collapse: collapse; margin-top: 0px; margin-bottom: 5px; table-layout: fixed; }
        .table th { border: 1px solid #000; font-size: 6px; padding: 2px; background: #f2f2f2; text-align: center; }
        .table td { border: 1px solid #000; font-size: 7px; padding: 1px 2px; line-height: 1; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media print {
            body { background: #fff; padding: 0; min-width: 0; }
            .page { margin: 0; box-shadow: none; border: none; width: 21cm; height: 29.7cm; padding: 10mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; padding: 15px; background: #fff; border-bottom: 1px solid #ccc; position: sticky; top: 0; z-index: 999;">
        <button onclick="window.print()" style="padding:10px 40px; font-weight:bold; cursor:pointer; background:#1a73e8; color:#fff; border:none; border-radius:4px;">IMPRIMIR DANFE</button>
        <button onclick="window.close()" style="padding:10px 20px; margin-left:10px; cursor:pointer; border:1px solid #ccc; background:#fff;">Fechar</button>
    </div>

    <div class="page">
        <!-- Header Redesenhado com Tabela de Largura Fixa para Estabilidade -->
        <table class="header-main">
            <tr>
                <td class="emit-logo">
                    <img src="<?= $logoPath ?>" style="max-width: 80px; max-height: 80px;" onerror="this.src='logo_sistema_erp_eletrica.png'">
                </td>
                <td class="emit-info">
                    <div class="bold" style="font-size: 10px;"><?= $emit['xNome'] ?></div>
                    <div class="bold" style="font-size: 8px; color: #444; margin-bottom: 4px;"><?= $emit['xFant'] ?></div>
                    <div style="line-height: 1.2;">
                        <?= $emit['xLgr'] ?>, <?= $emit['nro'] ?> <?= $emit['xCpl'] ?><br>
                        <?= $emit['xBairro'] ?> - CEP: <?= $emit['CEP'] ?><br>
                        <?= $emit['xMun'] ?> - <?= $emit['UF'] ?><br>
                        FONE: <?= $emit['fone'] ?>
                    </div>
                </td>
                <td class="danfe-box">
                    <div class="bold" style="font-size: 11px; margin-top: 2px;">DANFE</div>
                    <div style="font-size: 6px;">Documento Auxiliar da<br>Nota Fiscal Eletrônica</div>
                    <div style="border: 1px solid #000; margin: 3px 5px; height: 24px; display: flex;">
                        <div style="width: 55%; font-size: 5px; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: center; text-align: left; padding-left: 2px;">
                            <span>0-ENTRADA</span>
                            <span>1-SAÍDA</span>
                        </div>
                        <div style="width: 45%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                            <?= $ide['tpNF'] ?>
                        </div>
                    </div>
                    <div class="bold" style="font-size: 9px;">
                        Nº <?= $ide['nNF'] ?><br>
                        SÉRIE <?= $ide['serie'] ?><br>
                        FOLHA 1 / 1
                    </div>
                </td>
                <td class="barcode-box">
                    <div class="barcode" style="border:none;">
                        <?= barcode128($ide['chave']) ?>
                    </div>
                    <div style="border-top: 1px solid #000; padding-top: 1px;">
                        <span class="title" style="font-size: 6px;">CHAVE DE ACESSO</span>
                        <div class="bold" style="font-size: 8px;"><?= $fmtChave($ide['chave']) ?></div>
                    </div>
                    <div style="font-size: 6px; margin-top: 4px;">
                        Consulta de autenticidade no portal nacional da NF-e<br>
                        <b>www.nfe.fazenda.gov.br/portal</b> ou no site da Sefaz Autorizada
                    </div>
                </td>
            </tr>
        </table>

        <!-- Nat Op / Protocolo -->
        <div class="flex">
            <div class="box f1">
                <span class="title">NATUREZA DA OPERAÇÃO</span>
                <div class="val text-truncate" style="max-height: 12px;"><?= $ide['natOp'] ?></div>
            </div>
            <div class="box" style="width: 250px;">
                <span class="title">PROTOCOLO DE AUTORIZAÇÃO DE USO</span>
                <div class="val bold center"><?= $prot['nProt'] ?> - <?= $prot['dhRec'] ? date('d/m/Y H:i:s', strtotime($prot['dhRec'])) : '' ?></div>
            </div>
        </div>

        <!-- IE / CNPJ -->
        <div class="flex">
            <div class="box f1">
                <span class="title">INSCRIÇÃO ESTADUAL</span>
                <div class="val"><?= $emit['IE'] ?></div>
            </div>
            <div class="box f1">
                <span class="title">INSCRIÇÃO ESTADUAL DO SUBST. TRIB.</span>
                <div class="val"><?= $emit['IEST'] ?></div>
            </div>
            <div class="box f1">
                <span class="title">CNPJ</span>
                <div class="val"><?= $emit['CNPJ'] ?></div>
            </div>
        </div>

        <!-- Destinatário -->
        <div class="section-title">DESTINATÁRIO / REMETENTE</div>
        <div class="flex">
            <div class="box f3">
                <span class="title">NOME / RAZÃO SOCIAL</span>
                <div class="val bold"><?= $dest['xNome'] ?></div>
            </div>
            <div class="box f1">
                <span class="title"><?= $dest['docT'] ?> / CPF</span>
                <div class="val bold"><?= $dest['doc'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <span class="title">DATA DA EMISSÃO</span>
                <div class="val center"><?= $ide['dhEmi'] ? date('d/m/Y', strtotime($ide['dhEmi'])) : '' ?></div>
            </div>
        </div>
        <div class="flex">
            <div class="box f2">
                <span class="title">ENDEREÇO</span>
                <div class="val"><?= $dest['xLgr'] ?>, <?= $dest['nro'] ?> <?= $dest['xCpl'] ?></div>
            </div>
            <div class="box f1">
                <span class="title">BAIRRO / DISTRITO</span>
                <div class="val"><?= $dest['xBairro'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <span class="title">CEP</span>
                <div class="val center"><?= $dest['CEP'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <span class="title">DATA DA SAÍDA</span>
                <div class="val center"><?= $ide['dhEmi'] ? date('d/m/Y', strtotime($ide['dhEmi'])) : '' ?></div>
            </div>
        </div>
        <div class="flex">
            <div class="box f2">
                <span class="title">MUNICÍPIO</span>
                <div class="val"><?= $dest['xMun'] ?></div>
            </div>
            <div class="box" style="width: 40px;">
                <span class="title">UF</span>
                <div class="val center"><?= $dest['UF'] ?></div>
            </div>
            <div class="box" style="width: 110px;">
                <span class="title">FONE / FAX</span>
                <div class="val"><?= $dest['fone'] ?></div>
            </div>
            <div class="box" style="width: 140px;">
                <span class="title">INSCRIÇÃO ESTADUAL</span>
                <div class="val"><?= $dest['IE'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <span class="title">HORA DA SAÍDA</span>
                <div class="val center"><?= $ide['dhEmi'] ? date('H:i:s', strtotime($ide['dhEmi'])) : '' ?></div>
            </div>
        </div>

        <!-- Faturas / Duplicatas -->
        <div class="section-title">FATURAS / DUPLICATAS</div>
        <div class="box flex" style="min-height: 22px; padding: 2px;">
            <?php if (empty($dups)): ?>
                <div class="val" style="color: #666; font-style: italic; font-size: 7px;">PAGAMENTO À VISTA</div>
            <?php else: ?>
                <?php foreach($dups as $d): ?>
                    <div style="border: 1px solid #ccc; margin-right: 4px; padding: 1px 3px; font-size: 7px; min-width: 60px;">
                        <b>Dupl:</b> <?= $d['nDup'] ?> - <b>Venc:</b> <?= $d['dVenc'] ?> - <b>Valor:</b> R$ <?= $d['vDup'] ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Impostos -->
        <div class="section-title">CÁLCULO DO IMPOSTO</div>
        <div class="flex">
            <div class="box f1"><span class="title">BASE DE CÁLCULO DO ICMS</span><div class="val right"><?= $tot['vBC'] ?></div></div>
            <div class="box f1"><span class="title">VALOR DO ICMS</span><div class="val right"><?= $tot['vICMS'] ?></div></div>
            <div class="box f1"><span class="title">BASE DE CÁLCULO DO ICMS ST</span><div class="val right"><?= $tot['vBCST'] ?></div></div>
            <div class="box f1"><span class="title">VALOR DO ICMS ST</span><div class="val right"><?= $tot['vST'] ?></div></div>
            <div class="box f1"><span class="title">VALOR TOTAL DOS PRODUTOS</span><div class="val right"><?= $tot['vProd'] ?></div></div>
        </div>
        <div class="flex">
            <div class="box f1"><span class="title">VALOR DO FRETE</span><div class="val right"><?= $tot['vFrete'] ?></div></div>
            <div class="box f1"><span class="title">VALOR DO SEGURO</span><div class="val right"><?= $tot['vSeg'] ?></div></div>
            <div class="box f1"><span class="title">DESCONTO</span><div class="val right"><?= $tot['vDesc'] ?></div></div>
            <div class="box f1"><span class="title">OUTRAS DESPESAS ACESS.</span><div class="val right"><?= $tot['vOutro'] ?></div></div>
            <div class="box f1"><span class="title">VALOR DO IPI</span><div class="val right"><?= $tot['vIPI'] ?></div></div>
            <div class="box f1"><span class="title">VALOR TOTAL DA NOTA</span><div class="val right bold" style="font-size: 10px;"><?= $tot['vNF'] ?></div></div>
        </div>

        <!-- Transportador -->
        <div class="section-title">TRANSPORTADOR / VOLUMES TRANSPORTADOS</div>
        <div class="flex">
            <div class="box f3"><span class="title">RAZÃO SOCIAL</span><div class="val text-truncate"><?= $transp['xNome'] ?></div></div>
            <div class="box" style="width: 100px;"><span class="title">FRETE POR CONTA</span><div class="val center"><?= $transp['modFrete'] ?></div></div>
            <div class="box" style="width: 90px;"><span class="title">CÓDIGO ANTT</span><div class="val"></div></div>
            <div class="box" style="width: 100px;"><span class="title">PLACA DO VEÍCULO</span><div class="val center"><?= $transp['placa'] ?></div></div>
            <div class="box" style="width: 30px;"><span class="title">UF</span><div class="val center"><?= $transp['UFPlaca'] ?></div></div>
            <div class="box" style="width: 130px;"><span class="title">CNPJ / CPF</span><div class="val center"><?= $transp['doc'] ?></div></div>
        </div>
        <div class="flex">
            <div class="box f3"><span class="title">ENDEREÇO</span><div class="val text-truncate"><?= $transp['xEnder'] ?></div></div>
            <div class="box f2"><span class="title">MUNICÍPIO</span><div class="val"><?= $transp['xMun'] ?></div></div>
            <div class="box" style="width: 30px;"><span class="title">UF</span><div class="val center"><?= $transp['UF'] ?></div></div>
            <div class="box f2"><span class="title">INSCRIÇÃO ESTADUAL</span><div class="val"></div></div>
        </div>

        <!-- Itens -->
        <div class="section-title">DADOS DO PRODUTO / SERVIÇOS</div>
        <div class="items-container">
            <table class="table-items">
                <thead>
                    <tr>
                        <th style="width: 7%;">CÓD. PROD.</th>
                        <th style="width: 28%;">DESCRIÇÃO DOS PRODUTOS / SERVIÇOS</th>
                        <th style="width: 8%;">NCM/SH</th>
                        <th style="width: 5%;">CSOSN</th>
                        <th style="width: 5%;">CFOP</th>
                        <th style="width: 5%;">UNID.</th>
                        <th style="width: 6%;">QUANT.</th>
                        <th style="width: 8%;">V. UNIT.</th>
                        <th style="width: 8%;">V. TOTAL</th>
                        <th style="width: 5%;">BC ICMS</th>
                        <th style="width: 5%;">V. ICMS</th>
                        <th style="width: 5%;">V. IPI</th>
                        <th style="width: 5%;">ALÍQ. ICMS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_itens = count($itens);
                    foreach ($itens as $it): 
                    ?>
                    <tr>
                        <td class="center"><?= $it['cProd'] ?></td>
                        <td style="white-space: normal;"><?= $it['xProd'] ?></td>
                        <td class="center"><?= $it['NCM'] ?></td>
                        <td class="center"><?= $it['CST'] ?></td>
                        <td class="center"><?= $it['CFOP'] ?></td>
                        <td class="center"><?= $it['uCom'] ?></td>
                        <td class="right"><?= $it['qCom'] ?></td>
                        <td class="right"><?= $it['vUn'] ?></td>
                        <td class="right"><?= $it['vTot'] ?></td>
                        <td class="right"><?= $it['vBC'] ?></td>
                        <td class="right"><?= $it['vICMS'] ?></td>
                        <td class="right"><?= $it['vIPI'] ?></td>
                        <td class="center"><?= $it['pICMS'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Rodapé Pinned (DADOS ADICIONAIS e CANHOTO NO RODAPÉ) -->
        <div class="footer-pinned">
            <!-- Dados Adicionais -->
            <table class="table-footer" style="margin-bottom: 5px; width: 100%;">
                <tr>
                    <td style="width: 70%; height: 130px; vertical-align: top; border: 0.5pt solid #000;">
                        <span class="title">DADOS ADICIONAIS</span>
                        <div class="bold" style="font-size: 7px; margin-bottom: 2px;">INFORMAÇÕES COMPLEMENTARES</div>
                        <div style="font-size: 7px; line-height: 1.2; text-transform: none;">
                            <?= nl2br($infAdic) ?><br>
                            <?php if ($tot['vTrib'] !== '0,00'): ?><b>VALOR TOTAL ESTIMADO DOS TRIBUTOS (Lei 12.741/2012): R$ <?= $tot['vTrib'] ?></b><?php endif; ?>
                        </div>
                    </td>
                    <td style="width: 30%; height: 130px; vertical-align: top; border: 0.5pt solid #000; border-left: none;">
                        <span class="title">RESERVADO AO FISCO</span>
                        <div class="val"></div>
                    </td>
                </tr>
            </table>

            <!-- Linha de Serrilha / Corte -->
            <div style="border-top: 0.8pt dashed #000; width: 100%; height: 1px; margin: 8px 0 5px 0;"></div>

            <!-- Canhoto / Comprovante de Recebimento -->
            <div class="canhoto-container" style="border: 1pt solid #000; padding: 0;">
                <table style="border: none; margin: 0; width: 100%;">
                    <tr style="border: none;">
                        <td style="border: none; width: 85%; padding: 4px; vertical-align: top;">
                            <div style="border-bottom: 0.5pt dashed #000; padding-bottom: 3px; margin-bottom: 5px; font-size: 7px; line-height: 1;">
                                RECEBEMOS DE <b><?= $emit['xNome'] ?></b> OS PRODUTOS E/OU SERVIÇOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO
                            </div>
                            <table style="border: none; width: 100%; margin: 0;">
                                <tr style="border: none;">
                                    <td style="border: 0.5pt solid #000; width: 20%; height: 40px; padding: 1px 3px; vertical-align: top;">
                                        <span class="title" style="font-size: 5px;">DATA DE RECEBIMENTO</span>
                                    </td>
                                    <td style="border: 0.5pt solid #000; border-left: none; width: 80%; height: 40px; padding: 1px 3px; vertical-align: top;">
                                        <span class="title" style="font-size: 5px;">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</span>
                                        <div style="margin-top: 14px; text-align: center;">
                                            <div style="border-bottom: 0.5pt dotted #000; width: 90%; margin: 0 auto 1px;"></div>
                                            <div class="bold" style="font-size: 7px; color: #444; text-transform: uppercase;">
                                                <?= $dest['xNome'] ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="border: none; border-left: 1pt dashed #000; width: 15%; text-align: center; vertical-align: middle; padding: 2px;">
                            <div class="bold" style="font-size: 10px;">NF-e</div>
                            <div class="bold" style="font-size: 14px; margin: 2px 0;">Nº. <?= $ide['nNF'] ?></div>
                            <div class="bold" style="font-size: 10px;">SÉRIE <?= $ide['serie'] ?></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style="text-align: right; font-size: 5px; margin-top: 2px; color: #999;">
                Geração do DANFE A4 v2.2 - Padrão SEFAZ Infinitum
            </div>
        </div>
        </div>
    </div>

    <?php if ($qrTxt): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qrcode"), { text: <?= json_encode($qrTxt) ?>, width: 50, height: 50 });
    </script>
    <?php endif; ?>
</body>
</html>
