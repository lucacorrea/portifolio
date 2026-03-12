<?php
// === Wrapper de emissão para layout Venda Rápida ===
ob_start(); // captura qualquer HTML que a lógica atual imprimir
// Normaliza entrada: body JSON e/ou itens_json do form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false && $raw) {
        $_POST['_json_payload'] = $raw;
    }
    if (!empty($_POST['itens_json']) && empty($_POST['_json_payload'])) {
        $_POST['_json_payload'] = $_POST['itens_json'];
    }
}

// Autoload + config se existirem
if (file_exists(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php'))          require_once __DIR__ . '/config.php';

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

header('Content-Type: text/html; charset=utf-8');

/* ===== Helpers (guards) ===== */
if (!function_exists('soDig')) { function soDig($s){ return preg_replace('/\D+/', '', (string)$s); } }
if (!function_exists('pad'))   { function pad($n,$t){ return str_pad((string)$n,(int)$t,'0',STR_PAD_LEFT); } }
if (!function_exists('e'))     { function e($s){ return htmlspecialchars((string)$s, ENT_XML1|ENT_COMPAT, 'UTF-8'); } }
if (!function_exists('mod11')){
  function mod11(string $num): int { $f=[2,3,4,5,6,7,8,9]; $s=0;$k=0; for($i=strlen($num)-1;$i>=0;$i--){ $s+=(int)$num[$i]*$f[$k++%count($f)]; }
    $r=$s%11; return ($r==0||$r==1)?0:(11-$r); }
}
if (!function_exists('nfeproc')) {
  function nfeproc($nfe,$prot){
    $nfe = preg_replace('/<\?xml.*?\?>/','', $nfe);
    return '<?xml version="1.0" encoding="UTF-8"?>'
         . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
         . $nfe.$prot.'</nfeProc>';
  }
}

if (!function_exists('mapFormaToTPag')) {
  function mapFormaToTPag($forma): string {
    $f = (string)$forma;
    $fTrim = trim(mb_strtolower($f, 'UTF-8'));
    if (preg_match('/^\d+$/', $fTrim)) {
      return str_pad(substr($fTrim, -2), 2, '0', STR_PAD_LEFT);
    }
    $norm = iconv('UTF-8', 'ASCII//TRANSLIT', $fTrim);
    $norm = preg_replace('/[^a-z0-9]+/',' ', $norm);
    $norm = trim($norm);
    $map = [
      'dinheiro' => '01', 'cash' => '01', 'cheque' => '02',
      'cartao de credito' => '03', 'credito' => '03', 'cartao credito' => '03',
      'cartao de debito' => '04', 'debito' => '04', 'cartao debito' => '04',
      'vale alimentacao' => '10', 'vale refeicao' => '11', 'vale presente' => '12', 'vale combustivel' => '13',
      'boleto' => '15', 'deposito' => '16', 'pix' => '17', 'qr' => '17', 'qr code' => '17',
      'transferencia' => '18', 'carteira digital' => '18', 'fidelidade' => '19', 'sem pagamento' => '90',
      'pix estatico' => '20', 'outros' => '99',
    ];
    if (isset($map[$norm])) return $map[$norm];
    if (strpos($norm, 'pix') !== false) return '17';
    if (strpos($norm, 'debito') !== false) return '04';
    if (strpos($norm, 'credito') !== false) return '03';
    return '01';
  }
}

/* ===== Config NFePHP ===== */
$nfceConfig = [
  'atualizacao' => date('Y-m-d H:i:s'),
  'tpAmb'       => (int)TP_AMB,
  'razaosocial' => EMIT_XNOME,
  'siglaUF'     => EMIT_UF,
  'cnpj'        => EMIT_CNPJ,
  'schemes'     => 'PL_009_V4',
  'versao'      => '4.00',
  'urlChave'    => (defined('URL_CHAVE') ? constant('URL_CHAVE') : ''),
  'urlQRCode'   => (defined('URL_QR') ? constant('URL_QR') : ''),
  'CSC'         => CSC,
  'CSCid'       => ID_TOKEN,
  'proxyConf'   => ['proxyIp'=>'','proxyPort'=>'','proxyUser'=>'','proxyPass'=>''],
];
$configJson = json_encode($nfceConfig, JSON_UNESCAPED_UNICODE);

/* ===== Entrada do PDV ===== */
$payload = [];
$rawBody = file_get_contents('php://input');
if ($rawBody && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $payload = json_decode($rawBody, true) ?: [];
}

$itensRaw = $_POST['itens'] ?? $payload['itens'] ?? '[]';
$itens = is_array($itensRaw) ? $itensRaw : (json_decode((string)$itensRaw, true) ?: []);

$venda_id = (int)($_GET['venda_id'] ?? $_POST['venda_id'] ?? $payload['venda_id'] ?? 0);
$vendaRow = null;

if ($venda_id) {
    // Tenta localizar a conexão PDO
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $tryPaths = [
            __DIR__ . '/../../acainhadinhos/assets/php/conexao.php',
            __DIR__ . '/../config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
        ];
        foreach ($tryPaths as $p) {
            if (is_file($p)) { @require_once $p; if (isset($pdo) && $pdo instanceof PDO) break; }
        }
    }
    
    // Fallback Hardcoded (baseado no index.php que funciona)
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=u920914488_ERP;charset=utf8mb4", "u920914488_ERP", "N8r=$&Wrs$");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable $e) { /* falha total */ }
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            // Se itens ainda não vieram via POST, busca do banco
            if (empty($itens)) {
                $st = $pdo->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, unidade, ncm, cfop 
                                       FROM itens_venda 
                                      WHERE venda_id = :v");
                $st->execute([':v' => $venda_id]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $itens[] = [
                        'id'   => (int)$r['produto_id'],
                        'desc' => (string)$r['produto_nome'],
                        'qtd'  => (float)$r['quantidade'],
                        'vun'  => (float)$r['preco_unitario'],
                        'unid' => (string)($r['unidade'] ?? 'UN'),
                        'ncm'  => (string)($r['ncm'] ?? ''),
                        'cfop' => (string)($r['cfop'] ?? '')
                    ];
                }
            }
            
            // Busca dados da venda
            $stV = $pdo->prepare("SELECT v.*, c.cpf_cnpj AS cliente_cpf, c.nome AS cliente_nome 
                                    FROM vendas v 
                                    LEFT JOIN clientes c ON v.cliente_id = c.id 
                                   WHERE v.id = :v LIMIT 1");
            $stV->execute([':v' => $venda_id]);
            $vendaRow = $stV->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { /* silencioso */ }
    }
}

// Normalização
if ($itens) {
    $_tmp = [];
    foreach ($itens as $it) {
        $desc = $it['desc'] ?? $it['produto_nome'] ?? $it['nome'] ?? '';
        $qtd  = $it['qtd']  ?? $it['quantidade'] ?? 1;
        $vun  = $it['vun']  ?? $it['preco_unitario'] ?? 0;
        if ($desc !== '' && (float)$qtd > 0) {
            $_tmp[] = [
                'desc' => (string)$desc, 'qtd' => (float)$qtd, 'vun' => (float)$vun,
                'unid' => (string)($it['unid'] ?? $it['un'] ?? 'UN'),
                'ncm'  => (string)($it['ncm'] ?? ''), 'cfop' => (string)($it['cfop'] ?? '')
            ];
        }
    }
    $itens = $_tmp;
}

if (!$itens) die('Sem itens.');

/* Documento do destinatário */
$docInput = soDig($_POST['cpf'] ?? $_POST['cpf_cnpj'] ?? $payload['cpf'] ?? $payload['cpf_cliente'] ?? '');
$cpf  = strlen($docInput) === 11 ? $docInput : '';
$cnpj = strlen($docInput) === 14 ? $docInput : '';

if (empty($cpf) && empty($cnpj) && !empty($vendaRow)) {
    $rDoc = soDig($vendaRow['cliente_cpf'] ?? $vendaRow['cpf_cliente'] ?? '');
    if (strlen($rDoc) === 11) $cpf = $rDoc;
    elseif (strlen($rDoc) === 14) $cnpj = $rDoc;
}

// Lógica de Nome: Apenas se cadastrado
$nomeConsumidor = '';
if (!empty($vendaRow['cliente_nome'])) {
    $nomeConsumidor = $vendaRow['cliente_nome'];
}

/* ===== Certificado A1 ===== */
$pfx = @file_get_contents(PFX_PATH);
if ($pfx === false) die('Não encontrei o PFX em: '.e(PFX_PATH));
try {
    $cert = Certificate::readPfx($pfx, PFX_PASSWORD);
} catch (Throwable $e) {
    try { $cert = Certificate::readPfx($pfx, trim(PFX_PASSWORD)); }
    catch (Throwable $e2) { die('Falha no certificado: '.$e->getMessage()); }
}

$tools = new Tools($configJson, $cert);
$tools->model('65');

/* ===== nNF transacional ===== */
$nNF = null;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT ultimo_numero_nfce FROM integracao_nfce WHERE empresa_id = :e FOR UPDATE");
        $st->execute([':e' => NFCE_EMPRESA_ID]);
        $rN = $st->fetch(PDO::FETCH_ASSOC);
        $nNF = $rN ? (int)$rN['ultimo_numero_nfce'] + 1 : 1;
        $up = $pdo->prepare("UPDATE integracao_nfce SET ultimo_numero_nfce = :n WHERE empresa_id = :e");
        $up->execute([':n'=>$nNF, ':e'=>NFCE_EMPRESA_ID]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
}
if (!$nNF) $nNF = mt_rand(1000, 999999);

$cUF    = pad(COD_UF, 2);
$AAMM   = date('ym');
$CNPJem = pad(soDig(EMIT_CNPJ), 14);
$serie  = pad((string)NFC_SERIE, 3);
$nNFpad = pad($nNF, 9);
$tpEmis = '1';
$cNF    = pad((string)mt_rand(1, 99999999), 8);
$base44 = $cUF.$AAMM.$CNPJem.'65'.$serie.$nNFpad.$tpEmis.$cNF;
$cDV    = (string)mod11($base44);
$chave  = $base44.$cDV;
$IdNFe  = 'NFe'.$chave;

/* ===== XML Montagem ===== */
$destXML = '';
if ($nomeConsumidor) {
    $destXML = '<dest>'.($cpf?'<CPF>'.$cpf.'</CPF>':'<CNPJ>'.$cnpj.'</CNPJ>').'<xNome>'.e($nomeConsumidor).'</xNome><indIEDest>9</indIEDest></dest>';
} elseif ($cpf || $cnpj) {
    $destXML = '<dest>'.($cpf?'<CPF>'.$cpf.'</CPF>':'<CNPJ>'.$cnpj.'</CNPJ>').'<indIEDest>9</indIEDest></dest>';
}

$detXML = ''; $i=1; $vProd=0;
foreach ($itens as $it) {
    $vL = round($it['qtd'] * $it['vun'], 2);
    $ncm = $it['ncm'] ?: '21069090';
    $cfop = $it['cfop'] ?: '5102';
    $detXML .= '<det nItem="'.$i.'"><prod><cProd>'.$i.'</cProd><cEAN>SEM GTIN</cEAN><xProd>'.e($it['desc']).'</xProd><NCM>'.$ncm.'</NCM><CFOP>'.$cfop.'</CFOP><uCom>'.e($it['unid']).'</uCom><qCom>'.number_format($it['qtd'],4,'.','').'</qCom><vUnCom>'.number_format($it['vun'],10,'.','').'</vUnCom><vProd>'.number_format($vL,2,'.','').'</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>'.e($it['unid']).'</uTrib><qTrib>'.number_format($it['qtd'],4,'.','').'</qTrib><vUnTrib>'.number_format($it['vun'],10,'.','').'</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det>';
    $vProd += $vL; $i++;
}
$vNF = number_format($vProd, 2, '.', '');

// Pagamento
$tPag = mapFormaToTPag($vendaRow['forma_pagamento'] ?? '01');
$vPag = number_format(max($vProd, (float)($vendaRow['valor_recebido'] ?? 0)), 2, '.', '');
$vTroco = number_format(max(0, (float)$vPag - $vProd), 2, '.', '');

$pagXML = '<pag><detPag><indPag>0</indPag><tPag>'.$tPag.'</tPag><vPag>'.$vPag.'</vPag></detPag>';
if ((float)$vTroco > 0) $pagXML .= '<vTroco>'.$vTroco.'</vTroco>';
$pagXML .= '</pag>';

$xml = '<?xml version="1.0" encoding="UTF-8"?><NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="'.$IdNFe.'" versao="4.00"><ide><cUF>'.$cUF.'</cUF><cNF>'.$cNF.'</cNF><natOp>VENDA</natOp><mod>65</mod><serie>'.(int)NFC_SERIE.'</serie><nNF>'.$nNF.'</nNF><dhEmi>'.date('c').'</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>'.COD_MUN.'</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>'.$cDV.'</cDV><tpAmb>'.(int)TP_AMB.'</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>NFePHP</verProc></ide><emit><CNPJ>'.EMIT_CNPJ.'</CNPJ><xNome>'.e(EMIT_XNOME).'</xNome><xFant>'.e(EMIT_XFANT).'</xFant><enderEmit><xLgr>'.e(EMIT_XLGR).'</xLgr><nro>'.e(EMIT_NRO).'</nro><xBairro>'.e(EMIT_XBAIRRO).'</xBairro><cMun>'.COD_MUN.'</cMun><xMun>'.e(EMIT_XMUN).'</xMun><UF>'.EMIT_UF.'</UF><CEP>'.EMIT_CEP.'</CEP><cPais>1058</cPais><xPais>Brasil</xPais></enderEmit><IE>'.EMIT_IE.'</IE><CRT>'.EMIT_CRT.'</CRT></emit>'.$destXML.$detXML.'<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>'.$vNF.'</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>'.$vNF.'</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp>'.$pagXML.'<infAdic><infCpl>NFC-e Gerada pelo Sistema</infCpl></infAdic></infNFe></NFe>';

try {
    $xmlSigned = $tools->signNFe($xml);
    $resp = $tools->sefazEnviaLote([$xmlSigned], mt_rand(1, 99999), 1);
    $std = (new Standardize)->toStd($resp);
    if ((int)($std->cStat ?? 0) === 104 || (int)($std->cStat ?? 0) === 100) {
        if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $resp, $m)) {
            $procXml = nfeproc($xmlSigned, $m[1]);
            file_put_contents(__DIR__ . '/procNFCe_'.$chave.'.xml', $procXml);
            // Redir para DANFE
            header('Location: danfe_nfce.php?chave='.$chave.'&venda_id='.$venda_id);
            exit;
        }
    }
    echo "<pre>Erro SEFAZ:\n".print_r($std, true)."</pre>";
} catch (Throwable $e) { echo "Erro: ".$e->getMessage(); }