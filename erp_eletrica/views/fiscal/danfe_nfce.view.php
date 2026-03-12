<?php
/**
 * NFC-e DANFE Thermal Impression (80mm)
 * Logic ported from Acaidinhos
 */

if (!isset($xml)) {
    echo "Erro: XML não fornecido para o DANFE.";
    exit;
}

// Ensure XML is a DOMDocument or SimpleXMLElement
if (is_string($xml)) {
    $doc = new DOMDocument();
    $doc->loadXML($xml);
} else {
    $doc = $xml;
}

$xpath = new DOMXPath($doc);
$xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

// Helper to get node value
$val = function($query) use ($xpath) {
    $node = $xpath->query($query);
    return ($node->length > 0) ? trim($node->item(0)->nodeValue) : '';
};

$emit = [
    'nome'   => $val('//nfe:emit/nfe:xNome'),
    'fant'   => $val('//nfe:emit/nfe:xFant'),
    'cnpj'   => $val('//nfe:emit/nfe:CNPJ'),
    'ie'     => $val('//nfe:emit/nfe:IE'),
    'end'    => $val('//nfe:emit/nfe:enderEmit/nfe:xLgr') . ', ' . $val('//nfe:emit/nfe:enderEmit/nfe:nro'),
    'bairro' => $val('//nfe:emit/nfe:enderEmit/nfe:xBairro'),
    'cid'    => $val('//nfe:emit/nfe:enderEmit/nfe:xMun') . ' - ' . $val('//nfe:emit/nfe:enderEmit/nfe:UF')
];

$ide = [
    'num'    => $val('//nfe:ide/nfe:nNF'),
    'serie'  => $val('//nfe:ide/nfe:serie'),
    'dhEmiss'=> $val('//nfe:ide/nfe:dhEmi'),
    'chave'  => str_replace('NFe', '', $doc->getElementsByTagName('infNFe')->item(0)->getAttribute('Id')),
    'homol'  => $val('//nfe:ide/nfe:tpAmb') == '2'
];

$total = [
    'valor'  => number_format((float)$val('//nfe:total/nfe:ICMSTot/nfe:vNF'), 2, ',', '.'),
    'itens'  => $xpath->query('//nfe:det')->length,
    'desc'   => (float)$val('//nfe:total/nfe:ICMSTot/nfe:vDesc')
];

$qrCodeUrl = $val('//nfe:infNFeSupl/nfe:qrCode');

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>DANFE NFC-e - <?= $ide['num'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; width: 300px; margin: 0 auto; color: #000; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .dashed { border-bottom: 1px dashed #000; margin: 5px 0; }
        .table { width: 100%; border-collapse: collapse; }
        .text-right { text-align: right; }
        .extra-small { font-size: 10px; }
        @media print {
            .no-print { display: none; }
            body { width: 100%; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px;">Imprimir Novamente</button>
    </div>

    <div class="center">
        <div class="bold"><?= $emit['nome'] ?></div>
        <?php if($emit['fant']): ?><div><?= $emit['fant'] ?></div><?php endif; ?>
        <div>CNPJ: <?= $emit['cnpj'] ?></div>
        <div>IE: <?= $emit['ie'] ?></div>
        <div><?= $emit['end'] ?></div>
        <div><?= $emit['bairro'] ?> - <?= $emit['cid'] ?></div>
    </div>

    <div class="dashed"></div>
    <div class="center bold">DANFE NFC-e - Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div>
    
    <?php if($ide['homol']): ?>
    <div class="center bold" style="margin: 10px 0; border: 1px solid #000; padding: 5px;">
        AMBIENTE DE HOMOLOGAÇÃO - SEM VALOR FISCAL
    </div>
    <?php endif; ?>

    <div class="dashed"></div>
    <table class="table extra-small">
        <thead>
            <tr class="bold">
                <th align="left">CÓD / DESCRIÇÃO</th>
                <th align="right">QTD x UN</th>
                <th align="right">VALOR</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $items = $xpath->query('//nfe:det');
            foreach ($items as $item): 
                $prod = $item->getElementsByTagName('prod')->item(0);
            ?>
            <tr>
                <td colspan="3"><?= $prod->getElementsByTagName('cProd')->item(0)->nodeValue ?> - <?= $prod->getElementsByTagName('xProd')->item(0)->nodeValue ?></td>
            </tr>
            <tr>
                <td></td>
                <td align="right"><?= number_format((float)$prod->getElementsByTagName('qCom')->item(0)->nodeValue, 3, ',', '.') ?> <?= $prod->getElementsByTagName('uCom')->item(0)->nodeValue ?> x <?= number_format((float)$prod->getElementsByTagName('vUnCom')->item(0)->nodeValue, 2, ',', '.') ?></td>
                <td align="right"><?= number_format((float)$prod->getElementsByTagName('vProd')->item(0)->nodeValue, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dashed"></div>
    <table class="table">
        <tr>
            <td>QTD. TOTAL DE ITENS</td>
            <td class="text-right"><?= $total['itens'] ?></td>
        </tr>
        <tr>
            <td class="bold">VALOR TOTAL R$</td>
            <td class="text-right bold"><?= $total['valor'] ?></td>
        </tr>
        <?php if($total['desc'] > 0): ?>
        <tr>
            <td>Desconto R$</td>
            <td class="text-right">-<?= number_format($total['desc'], 2, ',', '.') ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="dashed"></div>
    <div class="center extra-small">
        Consulte pela Chave de Acesso em:<br>
        http://www.nfe.fazenda.gov.br/portal/consulta.aspx<br>
        <div class="bold" style="word-wrap: break-word; margin: 5px 0;">
            <?= implode(' ', str_split($ide['chave'], 4)) ?>
        </div>
    </div>

    <div class="dashed"></div>
    <div class="center">
        <?php 
        $dN = strtoupper(trim($val('//nfe:dest/nfe:xNome')));
        $dDoc = $val('//nfe:dest/nfe:CNPJ') ?: $val('//nfe:dest/nfe:CPF');
        if($dN && $dN !== 'CONSUMIDOR FINAL' && $dN !== 'CONSUMIDOR NÃO IDENTIFICADO'): ?>
            <div class="bold"><?= $dN ?></div>
            <div><?= $dDoc ? (strlen($dDoc) > 11 ? 'CNPJ: ' : 'CPF: ') . $dDoc : '' ?></div>
        <?php else: ?>
            <div class="bold"><?= $dDoc ? (strlen($dDoc) > 11 ? 'CNPJ: ' : 'CPF: ') . $dDoc : 'CONSUMIDOR FINAL' ?></div>
        <?php endif; ?>
    </div>

    <div class="dashed"></div>
    <div class="center">
        <?php if ($qrCodeUrl): ?>
            <!-- QR code would be rendered here via library -->
             <div class="extra-small">QR CODE GERADO NO XML</div>
             <p class="extra-small text-muted">Aponte a câmera para consultar a validade</p>
        <?php endif; ?>
    </div>

    <div class="dashed"></div>
    <div class="center extra-small">
        NFC-e nº <?= $ide['num'] ?> Série <?= $ide['serie'] ?> Data: <?= date('d/m/Y H:i:s', strtotime($ide['dhEmiss'])) ?><br>
        Protocolo de Autorização: <?= $val('//nfe:infProt/nfe:nProt') ?>
    </div>
</body>
</html>
