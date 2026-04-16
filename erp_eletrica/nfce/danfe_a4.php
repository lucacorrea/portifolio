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

$val = function($q, $node = null) use ($xpath) {
    $n = $xpath->query($q, $node);
    return ($n && $n->length) ? trim($n->item(0)->nodeValue) : '';
};
$br = fn($v) => number_format((float)$v, 2, ',', '.');
$fmtChave = fn($ch) => trim(implode(' ', str_split(preg_replace('/\D+/', '', $ch), 4)));

/* -------------------------- Dados Gerais -------------------------- */
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
        @import url('https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap');
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0px; background: #e0e0e0; color: #000; font-size: 8px; }
        .page { width: 21cm; min-height: 29.7cm; padding: 0.8cm; background: #fff; margin: 0.5cm auto; box-shadow: 0 0 15px rgba(0,0,0,0.2); position: relative; box-sizing: border-box; }
        
        .box { border: 1px solid #000; margin-bottom: -1px; margin-right: -1px; position: relative; }
        .flex { display: flex; width: 100%; }
        .f1 { flex: 1; } .f2 { flex: 2; } .f3 { flex: 3; } .f4 { flex: 4; } .f5 { flex: 5; }
        .center { text-align: center; } .right { text-align: right; } .bold { font-weight: bold; }
        .title { font-size: 6px; font-weight: bold; text-transform: uppercase; padding: 1px 3px; display: block; border-bottom: 0px; }
        .val { font-size: 8px; padding: 1px 4px; min-height: 10px; text-transform: uppercase; word-break: break-all; }
        .section-title { font-size: 8px; font-weight: bold; margin: 4px 0 2px 0; }
        
        /* Header */
        .header-main { height: 110px; }
        .emit-logo { width: 3.5cm; display: flex; align-items: center; justify-content: center; padding: 5px; border-right: 1px solid #000; }
        .emit-info { flex: 4; padding: 4px; font-size: 9px; }
        .danfe-box { width: 3.2cm; border-left: 1px solid #000; border-right: 1px solid #000; padding: 2px; }
        .barcode-box { flex: 4; padding: 4px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .barcode { font-family: 'Libre Barcode 128', cursive; font-size: 42px; margin: 0; line-height: 42px; }
        
        /* Tables */
        .table { width: 100%; border-collapse: collapse; margin-top: 0px; margin-bottom: 5px; }
        .table th { border: 1px solid #000; font-size: 6px; padding: 1px; background: #f2f2f2; }
        .table td { border: 1px solid #000; font-size: 7px; padding: 1px 2px; line-height: 1.1; vertical-align: middle; }

        /* Stub (Canhoto) */
        .canhoto { margin-top: 20px; border-top: 1px dashed #000; padding-top: 10px; }

        @media print {
            body { background: #fff; padding: 0; }
            .page { margin: 0; box-shadow: none; width: 21cm; height: 100%; padding: 0.8cm; }
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
        <!-- Header Structure -->
        <div class="box flex header-main">
            <div class="emit-logo">
                <img src="public/img/logo_premium.png" style="max-width: 90%; max-height: 90%;" onerror="this.src='logo_sistema_erp_eletrica.png'">
            </div>
            <div class="emit-info">
                <div class="bold" style="font-size: 11px;"><?= $emit['xNome'] ?></div>
                <div class="bold" style="font-size: 8px; margin-top: 2px; color: #555;"><?= $emit['xFant'] ?></div>
                <div style="margin-top: 6px; line-height: 1.2; font-size: 8px;">
                    <?= $emit['xLgr'] ?>, <?= $emit['nro'] ?> <?= $emit['xCpl'] ?><br>
                    <?= $emit['xBairro'] ?> - CEP: <?= $emit['CEP'] ?><br>
                    <?= $emit['xMun'] ?> - <?= $emit['UF'] ?><br>
                    FONE: <?= $emit['fone'] ?>
                </div>
            </div>
            <div class="danfe-box center">
                <div class="bold" style="font-size: 12px; margin-top: 5px;">DANFE</div>
                <div style="font-size: 7px;">Documento Auxiliar da<br>Nota Fiscal Eletrônica</div>
                <div class="flex" style="margin: 5px 0; border: 1px solid #000;">
                    <div style="width: 50%; font-size: 6px; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: center; padding: 2px 0;">
                        <span>0 - ENTRADA</span>
                        <span>1 - SAÍDA</span>
                    </div>
                    <div style="width: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                        <?= $ide['tpNF'] ?>
                    </div>
                </div>
                <div class="bold" style="font-size: 9px;">
                    Nº <?= $ide['nNF'] ?><br>
                    SÉRIE <?= $ide['serie'] ?><br>
                    FOLHA 1 / 1
                </div>
            </div>
            <div class="barcode-box">
                <div class="barcode">*<?= $ide['chave'] ?>*</div>
                <div style="width: 100%; border-top: 1px solid #000; margin-top: 2px; padding-top: 2px;">
                    <span class="title" style="text-align: center;">CHAVE DE ACESSO</span>
                    <div class="bold center" style="font-size: 8px;"><?= $fmtChave($ide['chave']) ?></div>
                </div>
                <div class="center" style="font-size: 7px; margin-top: 6px;">
                    Consulta de autenticidade no portal nacional da NF-e<br>
                    <b>www.nfe.fazenda.gov.br/portal</b> ou no site da Sefaz Autorizada
                </div>
            </div>
        </div>

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
        <div style="flex: 1; overflow: hidden;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 35px;">COD. PROD</th>
                        <th>DESCRIÇÃO DO PRODUTO / SERVIÇO</th>
                        <th style="width: 25px;">CFOP</th>
                        <th style="width: 40px;">NCM/SH</th>
                        <th style="width: 25px;">CST</th>
                        <th style="width: 16px;">UN</th>
                        <th style="width: 35px;">QUANT.</th>
                        <th style="width: 45px;">V. UNITÁRIO</th>
                        <th style="width: 45px;">V. TOTAL</th>
                        <th style="width: 40px;">V. DESC.</th>
                        <th style="width: 45px;">V. LÍQUIDO</th>
                        <th style="width: 40px;">BC ICMS</th>
                        <th style="width: 35px;">VALOR ICMS</th>
                        <th style="width: 30px;">VALOR IPI</th>
                        <th style="width: 20px;">ALÍQ. ICMS</th>
                        <th style="width: 20px;">ALÍQ. IPI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                    <tr>
                        <td class="center"><?= $it['cProd'] ?></td>
                        <td><?= htmlspecialchars($it['xProd']) ?></td>
                        <td class="center"><?= $it['CFOP'] ?></td>
                        <td class="center"><?= $it['NCM'] ?></td>
                        <td class="center"><?= $it['CST'] ?></td>
                        <td class="center"><?= $it['uCom'] ?></td>
                        <td class="right"><?= $it['qCom'] ?></td>
                        <td class="right"><?= $it['vUn'] ?></td>
                        <td class="right"><?= $it['vTot'] ?></td>
                        <td class="right"><?= $it['vDesc'] ?></td>
                        <td class="right bold"><?= $it['vLiq'] ?></td>
                        <td class="right"><?= $it['vBC'] ?></td>
                        <td class="right"><?= $it['vICMS'] ?></td>
                        <td class="right"><?= $it['vIPI'] ?></td>
                        <td class="right"><?= $it['pICMS'] ?></td>
                        <td class="right"><?= $it['pIPI'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Adicionais -->
        <div class="section-title">DADOS ADICIONAIS</div>
        <div class="flex" style="min-height: 80px;">
            <div class="box f5" style="padding: 2px;">
                <span class="title">INFORMAÇÕES COMPLEMENTARES</span>
                <div class="val" style="font-size: 7px; line-height: 1.2; text-transform: none;">
                    <?= nl2br(htmlspecialchars($infAdic)) ?><br>
                    <?php if ($tot['vTrib'] !== '0,00'): ?><b>VALOR TOTAL ESTIMADO DOS TRIBUTOS (Lei 12.741/2012): R$ <?= $tot['vTrib'] ?></b><?php endif; ?>
                </div>
                <?php if ($qrTxt): ?>
                <div style="margin-top: 5px; display: flex; align-items: center; gap: 8px;">
                    <div id="qrcode"></div>
                    <span style="font-size: 6px;">Consulta via QR Code</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="box f2">
                <span class="title">RESERVADO AO FISCO</span>
                <div class="val"></div>
            </div>
        </div>

        <!-- Stub (Canhoto) -->
        <div class="canhoto">
            <div class="flex">
                <div class="box f4" style="padding: 4px;">
                    <div style="font-size: 7px;">RECEBEMOS DE <b><?= $emit['xNome'] ?></b> OS PRODUTOS E/OU SERVIÇOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO</div>
                    <div class="flex" style="margin-top: 8px;">
                        <div style="flex: 1; border-top: 1px solid #000; margin-top: 12px; margin-right: 20px;">
                            <span class="title center">DATA DE RECEBIMENTO</span>
                        </div>
                        <div style="flex: 2; border-top: 1px solid #000; margin-top: 12px;">
                            <span class="title center">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</span>
                        </div>
                    </div>
                </div>
                <div class="box center" style="width: 120px; display: flex; flex-direction: column; justify-content: center; border-left: none;">
                    <b style="font-size: 14px;">NF-e</b>
                    <b style="font-size: 9px;">Nº <?= $ide['nNF'] ?><br>SÉRIE <?= $ide['serie'] ?></b>
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
