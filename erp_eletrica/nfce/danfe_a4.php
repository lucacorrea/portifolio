<?php
// danfe_a4.php — DANFE A4 (Modelo SEFAZ)
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
        // Tenta no diretório superior
        $file = dirname(__DIR__) . '/nfce/procNFCe_' . $chaveReq . '.xml';
    }
}

if (!is_file($file)) {
    // Tenta no banco
    try {
        $st = $pdo->prepare("SELECT xml_nfeproc FROM nfce_emitidas WHERE chave = :ch OR venda_id = :v ORDER BY id DESC LIMIT 1");
        $st->execute([':ch' => $chaveReq, ':v' => $vendaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['xml_nfeproc'])) $xmlRaw = $row['xml_nfeproc'];
    } catch (Throwable $e) {}
} else {
    $xmlRaw = file_get_contents($file);
}

if (!$xmlRaw) die('XML da nota não encontrado.');

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($xmlRaw);
libxml_clear_errors();

$nfeNS = 'http://www.portalfiscal.inf.br/nfe';
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('n', $nfeNS);

$val = fn($q) => ($n = $xpath->query($q)) && $n->length ? trim($n->item(0)->nodeValue) : '';
$br = fn($v) => number_format((float)$v, 2, ',', '.');
$fmtChave = fn($ch) => trim(implode(' ', str_split(preg_replace('/\D+/', '', $ch), 4)));

/* -------------------------- Dados -------------------------- */
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
    'tpNF'   => $val('//n:ide/n:tpNF'), // 1=Saída
    'dhEmi'  => $val('//n:ide/n:dhEmi') ?: $val('//n:ide/n:dhEmis'),
    'natOp'  => $val('//n:ide/n:natOp'),
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
    'dhEmi' => $val('//n:ide/n:dhEmi') ?: $val('//n:ide/n:dhEmis')
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

$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
    $p = $det->getElementsByTagName('prod')->item(0);
    $i = $det->getElementsByTagName('imposto')->item(0);
    $icms = $i->getElementsByTagName('ICMS')->item(0);
    $icmsX = $icms ? $icms->firstChild : null; // CST00, CST20, etc.

    $itens[] = [
        'cProd'  => $val('.//n:cProd', $p),
        'xProd'  => $val('.//n:xProd', $p),
        'NCM'    => $val('.//n:NCM', $p),
        'CST'    => $val('.//n:CST', $icmsX) ?: $val('.//n:CSOSN', $icmsX),
        'CFOP'   => $val('.//n:CFOP', $p),
        'uCom'   => $val('.//n:uCom', $p),
        'qCom'   => number_format((float)$val('.//n:qCom', $p), 4, ',', '.'),
        'vUn'    => $br($val('.//n:vUnCom', $p)),
        'vTot'   => $br($val('.//n:vProd', $p)),
        'vBC'    => $br($val('.//n:vBC', $icmsX)),
        'vICMS'  => $br($val('.//n:vICMS', $icmsX)),
        'vIPI'   => $br($val('.//n:vIPI', $i)),
        'pICMS'  => $br($val('.//n:pICMS', $icmsX)),
        'pIPI'   => $br($val('.//n:pIPI', $i))
    ];
}

$infAdic = $val('//n:infAdic/n:infCpl');

// QR Code data
$qrTxt = $val('//n:infNFeSupl/n:qrCode');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFE - <?= $ide['nNF'] ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap');
        
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; background: #f0f0f0; }
        .page { width: 19cm; min-height: 27cm; padding: 0.5cm; background: #fff; margin: 0 auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .box { border: 1px solid #000; margin-bottom: 2px; padding: 2px; overflow: hidden; }
        .no-top { border-top: none; }
        .no-left { border-left: none; }
        .flex { display: flex; }
        .f1 { flex: 1; }
        .f2 { flex: 2; }
        .col { display: flex; flex-direction: column; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .title { font-size: 6px; text-transform: uppercase; margin-bottom: 1px; color: #000; font-weight: bold; }
        .val { font-size: 9px; min-height: 11px; text-transform: uppercase; }
        
        .header-main { height: 110px; }
        .emit-logo { width: 2.5cm; border-right: 1px solid #000; display: flex; align-items: center; justify-content: center; padding: 5px; }
        .emit-info { flex: 3.5; padding: 2px; font-size: 9px; }
        .danfe-box { width: 3.5cm; border-left: 1px solid #000; border-right: 1px solid #000; padding: 2px; }
        .barcode-box { flex: 5; padding: 2px; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 2px; border: 1px solid #000; }
        .table th { border: 1px solid #000; background: #fff; font-size: 6px; padding: 2px; font-weight: bold; }
        .table td { border: 1px solid #000; padding: 1px 2px; font-size: 8px; }
        
        .barcode { font-family: 'Libre Barcode 128', cursive; font-size: 40px; margin: 0; line-height: 40px; }
        .qr-box { width: 3cm; height: 3cm; border: 1px solid #000; margin: 10px auto; padding: 5px; }
        
        @media print {
            @page { size: A4; margin: 0.5cm; }
            body { background: none; padding: 0; display: block; margin: 0; }
            .page { 
                box-shadow: none; margin: 0; width: 100%; padding: 0;
                display: flex; flex-direction: column; 
                height: 28cm; /* Explicit height for A4 print */
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; padding: 15px; position: sticky; top: 0; z-index: 100;">
        <button onclick="window.print()" style="padding:10px 30px; font-weight:bold; cursor:pointer; background: #1a73e8; color: #fff; border: none; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">IMPRIMIR DANFE</button>
        <button onclick="window.close()" style="padding:10px 20px; margin-left:10px; cursor:pointer; border: 1px solid #ccc; border-radius: 5px;">Fechar</button>
    </div>

    <div class="page">
        <!-- Recebemos de ... -->
        <div class="flex" style="margin-bottom: 5px;">
            <div class="box f1" style="border-right: none;">
                <div class="val" style="font-size: 7px;">RECEBEMOS DE <?= $emit['xNome'] ?> OS PRODUTOS / SERVIÇOS CONSTANTES DA NOTA FISCAL INDICADO AO LADO</div>
            </div>
            <div class="box center" style="width: 140px;">
                <div class="bold" style="font-size: 11px;">NF-e</div>
                <div class="bold">Nº <?= $ide['nNF'] ?></div>
                <div class="bold">SÉRIE <?= $ide['serie'] ?></div>
            </div>
        </div>
        <div class="flex" style="margin-top: -7px; margin-bottom: 10px;">
            <div class="box" style="width: 100px; border-right: none;">
                <div class="title">DATA DE RECEBIMENTO</div>
                <div class="val"></div>
            </div>
            <div class="box f1">
                <div class="title">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</div>
                <div class="val"></div>
            </div>
        </div>

        <!-- Header -->
        <div class="box flex header-main">
            <div class="emit-logo">
                <!-- Se tiver logo fixa no sistema, pode usar aqui -->
                <img src="/assets/img/logo.png" style="max-width: 100%; max-height: 100%;" onerror="this.style.display='none'">
            </div>
            <div class="emit-info">
                <div class="bold" style="font-size: 11px;"><?= $emit['xNome'] ?></div>
                <div style="margin-top:5px;">
                    <?= $emit['xLgr'] ?>, <?= $emit['nro'] ?> <?= $emit['xCpl'] ?><br>
                    <?= $emit['xBairro'] ?> - CEP: <?= $emit['CEP'] ?><br>
                    <?= $emit['xMun'] ?> - <?= $emit['UF'] ?><br>
                    Fone/Fax: <?= $emit['fone'] ?>
                </div>
            </div>
            <div class="danfe-box center">
                <div class="bold" style="font-size: 12px;">DANFE</div>
                <div style="font-size: 6px; margin-bottom: 5px;">Documento Auxiliar da Nota Fiscal Eletrônica</div>
                <div class="flex center" style="border: 1px solid #000; margin: 0 5px; height: 35px;">
                    <div class="f1 center" style="border-right: 1px solid #000;">
                        <span class="title">0 - ENTRADA</span><br>
                        <span class="title">1 - SAÍDA</span><br>
                        <span class="bold" style="font-size: 12px;"><?= $ide['tpNF'] ?></span>
                    </div>
                </div>
                <div class="bold" style="font-size: 10px; margin-top: 5px;">
                    Nº <?= $ide['nNF'] ?><br>
                    SÉRIE <?= $ide['serie'] ?><br>
                    FOLHA 1 / 1
                </div>
            </div>
            <div class="barcode-box">
                <div class="center barcode">
                    *<?= $ide['chave'] ?>*
                </div>
                <div class="center" style="margin-top: 2px;">
                    <span class="title">CHAVE DE ACESSO</span><br>
                    <span class="bold" style="font-size: 9px;"><?= $fmtChave($ide['chave']) ?></span>
                </div>
                <div class="center" style="font-size: 7px; margin-top: 5px;">
                    Consulta de autenticidade no portal nacional da NF-e<br>
                    <span class="bold">www.nfe.fazenda.gov.br/portal</span><br>
                    ou no site da Sefaz Autorizada
                </div>
            </div>
        </div>

        <!-- Natureza Op / Protocolo -->
        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;">
                <div class="title">NATUREZA DA OPERAÇÃO</div>
                <div class="val"><?= $ide['natOp'] ?></div>
            </div>
            <div class="box" style="width: 250px;">
                <div class="title">PROTOCOLO DE AUTORIZAÇÃO DE USO</div>
                <div class="val bold center" style="font-size: 10px;"><?= $prot['nProt'] ?> <?= $prot['dhRec'] ? date('d/m/Y H:i:s', strtotime($prot['dhRec'])) : '' ?></div>
            </div>
        </div>

        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;">
                <div class="title">INSCRIÇÃO ESTADUAL</div>
                <div class="val"><?= $emit['IE'] ?></div>
            </div>
            <div class="box f1" style="border-right: none;">
                <div class="title">INSCRIÇÃO ESTADUAL DO SUBST. TRIB.</div>
                <div class="val"><?= $emit['IEST'] ?></div>
            </div>
            <div class="box f1">
                <div class="title">CNPJ</div>
                <div class="val"><?= $emit['CNPJ'] ?></div>
            </div>
        </div>

        <!-- Destinatário -->
        <div class="bold" style="margin-top: 5px; font-size: 8px;">DESTINATÁRIO / REMETENTE</div>
        <div class="flex">
            <div class="box f1" style="border-right: none;">
                <div class="title">NOME / RAZÃO SOCIAL</div>
                <div class="val bold"><?= $dest['xNome'] ?></div>
            </div>
            <div class="box" style="width: 150px; border-right: none;">
                <div class="title"><?= $dest['docT'] ?> / CPF</div>
                <div class="val bold"><?= $dest['doc'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <div class="title">DATA DA EMISSÃO</div>
                <div class="val center"><?= $dest['dhEmi'] ? date('d/m/Y', strtotime($dest['dhEmi'])) : '' ?></div>
            </div>
        </div>
        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;">
                <div class="title">ENDEREÇO</div>
                <div class="val"><?= $dest['xLgr'] ?>, <?= $dest['nro'] ?> <?= $dest['xCpl'] ?></div>
            </div>
            <div class="box f1" style="border-right: none;">
                <div class="title">BAIRRO / DISTRITO</div>
                <div class="val"><?= $dest['xBairro'] ?></div>
            </div>
            <div class="box" style="width: 100px; border-right: none;">
                <div class="title">CEP</div>
                <div class="val center"><?= $dest['CEP'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <div class="title">DATA DA SAÍDA / ENTRADA</div>
                <div class="val center"><?= $dest['dhEmi'] ? date('d/m/Y', strtotime($dest['dhEmi'])) : '' ?></div>
            </div>
        </div>
        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;">
                <div class="title">MUNICÍPIO</div>
                <div class="val"><?= $dest['xMun'] ?></div>
            </div>
            <div class="box" style="width: 50px; border-right: none;">
                <div class="title">UF</div>
                <div class="val center"><?= $dest['UF'] ?></div>
            </div>
            <div class="box" style="width: 120px; border-right: none;">
                <div class="title">FONE / FAX</div>
                <div class="val"><?= $dest['fone'] ?></div>
            </div>
            <div class="box" style="width: 150px; border-right: none;">
                <div class="title">INSCRIÇÃO ESTADUAL</div>
                <div class="val"><?= $dest['IE'] ?></div>
            </div>
            <div class="box" style="width: 100px;">
                <div class="title">HORA DA SAÍDA</div>
                <div class="val center"><?= $dest['dhEmi'] ? date('H:i:s', strtotime($dest['dhEmi'])) : '' ?></div>
            </div>
        </div>

        <!-- Impostos -->
        <div class="bold" style="margin-top: 5px; font-size: 8px;">CÁLCULO DO IMPOSTO</div>
        <div class="flex">
            <div class="box f1" style="border-right: none;"><div class="title">BASE DE CÁLCULO DO ICMS</div><div class="val right"><?= $tot['vBC'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">VALOR DO ICMS</div><div class="val right"><?= $tot['vICMS'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">BASE DE CÁLCULO DO ICMS ST</div><div class="val right"><?= $tot['vBCST'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">VALOR DO ICMS ST</div><div class="val right"><?= $tot['vST'] ?></div></div>
            <div class="box f1"><div class="title">VALOR TOTAL DOS PRODUTOS</div><div class="val right"><?= $tot['vProd'] ?></div></div>
        </div>
        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;"><div class="title">VALOR DO FRETE</div><div class="val right"><?= $tot['vFrete'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">VALOR DO SEGURO</div><div class="val right"><?= $tot['vSeg'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">DESCONTO</div><div class="val right"><?= $tot['vDesc'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">OUTRAS DESPESAS ACESS.</div><div class="val right"><?= $tot['vOutro'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">VALOR DO IPI</div><div class="val right"><?= $tot['vIPI'] ?></div></div>
            <div class="box f1"><div class="title">VALOR TOTAL DA NOTA</div><div class="val right bold" style="font-size: 11px;"><?= $tot['vNF'] ?></div></div>
        </div>

        <!-- Transportador -->
        <div class="bold" style="margin-top: 5px; font-size: 8px;">TRANSPORTADOR / VOLUMES TRANSPORTADOS</div>
        <div class="flex">
            <div class="box f1" style="border-right: none;"><div class="title">RAZÃO SOCIAL</div><div class="val"><?= $transp['xNome'] ?></div></div>
            <div class="box" style="width: 100px; border-right: none;"><div class="title">FRETE POR CONTA</div><div class="val center"><?= $transp['modFrete'] ?></div></div>
            <div class="box" style="width: 100px; border-right: none;"><div class="title">CÓDIGO ANTT</div><div class="val"></div></div>
            <div class="box" style="width: 100px; border-right: none;"><div class="title">PLACA DO VEÍCULO</div><div class="val center"><?= $transp['placa'] ?></div></div>
            <div class="box" style="width: 40px; border-right: none;"><div class="title">UF</div><div class="val center"><?= $transp['UFPlaca'] ?></div></div>
            <div class="box" style="width: 130px;"><div class="title">CNPJ / CPF</div><div class="val center"><?= $transp['doc'] ?></div></div>
        </div>
        <div class="flex" style="margin-top: -2px;">
            <div class="box f1" style="border-right: none;"><div class="title">ENDEREÇO</div><div class="val"><?= $transp['xEnder'] ?></div></div>
            <div class="box f1" style="border-right: none;"><div class="title">MUNICÍPIO</div><div class="val"><?= $transp['xMun'] ?></div></div>
            <div class="box" style="width: 40px; border-right: none;"><div class="title">UF</div><div class="val center"><?= $transp['UF'] ?></div></div>
            <div class="box" style="width: 150px;"><div class="title">INSCRIÇÃO ESTADUAL</div><div class="val"></div></div>
        </div>

        <!-- Itens -->
        <div class="bold" style="margin-top: 5px; font-size: 8px;">DADOS DO PRODUTO / SERVIÇOS</div>
        <div style="flex: 1;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 35px;">CÓDIGO</th>
                        <th>DESCRIÇÃO DO PRODUTO / SERVIÇO</th>
                        <th style="width: 35px;">NCM/SH</th>
                        <th style="width: 25px;">CST</th>
                        <th style="width: 25px;">CFOP</th>
                        <th style="width: 20px;">UNID.</th>
                        <th style="width: 35px;">QUANT.</th>
                        <th style="width: 45px;">VALOR UNIT.</th>
                        <th style="width: 45px;">VALOR TOTAL</th>
                        <th style="width: 45px;">BC ICMS</th>
                        <th style="width: 35px;">VALOR ICMS</th>
                        <th style="width: 35px;">VALOR IPI</th>
                        <th style="width: 25px;">ALÍQ. ICMS</th>
                        <th style="width: 25px;">ALÍQ. IPI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                    <tr>
                        <td><?= $it['cProd'] ?></td>
                        <td><?= htmlspecialchars($it['xProd']) ?></td>
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
                        <td class="right"><?= $it['pICMS'] ?></td>
                        <td class="right"><?= $it['pIPI'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Dados Adicionais -->
        <div class="bold" style="margin-top: 10px; font-size: 8px;">DADOS ADICIONAIS</div>
        <div class="flex" style="min-height: 100px;">
            <div class="box f2" style="border-right: none;">
                <div class="title">INFORMAÇÕES COMPLEMENTARES</div>
                <div class="val" style="font-size: 7px; text-transform: none;">
                    <?= nl2br(htmlspecialchars($infAdic)) ?><br>
                    <?php if ($tot['vTrib'] !== '0,00'): ?><b>VALOR TOTAL ESTIMADO DOS TRIBUTOS (Lei 12.741/2012): R$ <?= $tot['vTrib'] ?></b><?php endif; ?>
                </div>
                <?php if ($qrTxt): ?>
                <div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 5px;">
                    <div class="title center">QR Code para consulta</div>
                    <div id="qrcode" class="center" style="margin: 0 auto; width: 80px; height: 80px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="box f1">
                <div class="title">RESERVADO AO FISCO</div>
                <div class="val"></div>
            </div>
        </div>
        
        <div class="center" style="margin-top: 20px; font-size: 7px; color: #888;">
            Gerado por ERP Elétrica - Inteligência em Gestão
        </div>
    </div>

    <?php if ($qrTxt): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qrcode"), { text: <?= json_encode($qrTxt) ?>, width: 80, height: 80 });
    </script>
    <?php endif; ?>
</body>
</html>
