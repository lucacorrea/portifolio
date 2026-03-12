<?php
/**
 * nfce/emitir.php — Emissor unificado e robusto
 * Baseado na lógica 100% funcional do sistema Açaidinhos.
 */
ob_start();
session_start();

// Carrega Autoload do NFePHP
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Carrega Configuração (Banco de Dados e constantes da Empresa)
require_once __DIR__ . '/config.php';

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

header('Content-Type: text/html; charset=utf-8');

/* ===== Helpers (guards) ===== */
function e($s){ return htmlspecialchars((string)$s, ENT_XML1|ENT_COMPAT, 'UTF-8'); }
function pad($n,$t){ return str_pad((string)$n,(int)$t,'0',STR_PAD_LEFT); }
function soDig($s){ return preg_replace('/\D+/', '', (string)$s); }
function mod11(string $num): int { 
    $f=[2,3,4,5,6,7,8,9]; $s=0;$k=0; 
    for($i=strlen($num)-1;$i>=0;$i--){ $s+=(int)$num[$i]*$f[$k++%count($f)]; }
    $r=$s%11; return ($r==0||$r==1)?0:(11-$r); 
}
function nfeproc($nfe,$prot){
    $nfe = preg_replace('/<\?xml.*?\?>/','', $nfe);
    return '<?xml version="1.0" encoding="UTF-8"?>'
         . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
         . $nfe.$prot.'</nfeProc>';
}
function mapFormaToTPag($forma): string {
    $f = trim(mb_strtolower((string)$forma, 'UTF-8'));
    if (preg_match('/^\d+$/', $f)) return str_pad(substr($f, -2), 2, '0', STR_PAD_LEFT);
    $map = [
      'dinheiro'=>'01','cash'=>'01','cheque'=>'02','credito'=>'03','cartao credito'=>'03',
      'debito'=>'04','cartao debito'=>'04','vale'=>'10','alimentacao'=>'10','refeicao'=>'11',
      'boleto'=>'15','deposito'=>'16','pix'=>'17','transferencia'=>'18'
    ];
    foreach($map as $k=>$v) { if(strpos($f, $k)!==false) return $v; }
    return '01';
}

/* ===== Entrada de Dados ===== */
$venda_id = (int)($_REQUEST['venda_id'] ?? 0);
$itensRaw = $_POST['itens'] ?? '[]';
$itens    = json_decode((string)$itensRaw, true) ?: [];
$vendaRow = null; // Initialize vendaRow

if ($venda_id) {
    // Busca dados no banco de Vendas (pdoSales)
    try {
        // Se itens ainda não vieram via POST, busca do banco
        if (empty($itens)) {
            $st = $pdoSales->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, unidade, ncm, cfop 
                                       FROM itens_venda 
                                      WHERE venda_id = :v");
            $st->execute([':v' => $venda_id]);
            $rows = $st->fetchAll();
            foreach ($rows as $r) {
                $itens[] = [
                    'desc' => (string)$r['produto_nome'],
                    'qtd'  => (float)$r['quantidade'],
                    'vun'  => (float)$r['preco_unitario'],
                    'unid' => (string)($r['unidade'] ?? 'UN'),
                    'ncm'  => (string)($r['ncm'] ?? '21069090'),
                    'cfop' => (string)($r['cfop'] ?? '5102')
                ];
            }
        }
        
        // Busca dados da venda
        $stV = $pdoSales->prepare("SELECT v.*, c.cpf_cnpj AS cliente_cpf, c.nome AS cliente_nome 
                                    FROM vendas v 
                                    LEFT JOIN clientes c ON v.cliente_id = c.id 
                                   WHERE v.id = :v LIMIT 1");
        $stV->execute([':v' => $venda_id]);
        $vendaRow = $stV->fetch();
    } catch (Throwable $e) { /* silencioso */ }
}

if (!$itens) die('Sem itens. Verifique se a venda #'.$venda_id.' possui produtos cadastrados na tabela itens_venda.');

// Normaliza itens (Garante campos mínimos)
$_norm = [];
foreach ($itens as $it) {
    $d = $it['desc'] ?? $it['produto_nome'] ?? '';
    $q = (float)($it['qtd'] ?? $it['quantidade'] ?? 1);
    $v = (float)($it['vun'] ?? $it['preco_unitario'] ?? 0);
    if ($d !== '' && $q > 0) {
        $_norm[] = [
            'desc' => $d, 'qtd' => $q, 'vun' => $v,
            'unid' => (string)($it['unid'] ?? $it['unidade'] ?? 'UN'),
            'ncm'  => (string)($it['ncm'] ?? '21069090'),
            'cfop' => (string)($it['cfop'] ?? '5102')
        ];
    }
}
$itens = $_norm;

/* Destinatário */
$docInput = soDig($_POST['cpf'] ?? $_POST['documento'] ?? '');
$cpf = (strlen($docInput) === 11) ? $docInput : '';
$cnpj = (strlen($docInput) === 14) ? $docInput : '';
$nomeDest = null;

if (empty($cpf) && empty($cnpj) && $vendaRow) {
    $rDoc = soDig($vendaRow['cliente_cpf'] ?? $vendaRow['cpf_cliente'] ?? '');
    if (strlen($rDoc) === 11) $cpf = $rDoc;
    elseif (strlen($rDoc) === 14) $cnpj = $rDoc;
    if ($cpf || $cnpj) $nomeDest = $vendaRow['cliente_nome'] ?? null;
}

/* Certificado */
$pfx = file_get_contents(PFX_PATH);
try {
    $cert = Certificate::readPfx($pfx, PFX_PASSWORD);
} catch (Throwable $e) {
    die('Erro no certificado: '.$e->getMessage());
}

/* Config NFePHP */
$toolsConfig = [
    'atualizacao' => date('Y-m-d H:i:s'),
    'tpAmb'       => (int)TP_AMB,
    'razaosocial' => EMIT_XNOME,
    'siglaUF'     => EMIT_UF,
    'cnpj'        => EMIT_CNPJ,
    'schemes'     => 'PL_009_V4',
    'versao'      => '4.00',
    'urlChave'    => URL_CHAVE,
    'urlQRCode'   => URL_QR,
    'CSC'         => CSC,
    'CSCid'       => ID_TOKEN
];
$tools = new Tools(json_encode($toolsConfig), $cert);
$tools->model('65');

/* nNF transacional */
$nNF = null;
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT ultimo_numero_nfce FROM integracao_nfce WHERE empresa_id = :e FOR UPDATE");
    $st->execute([':e' => NFCE_EMPRESA_ID]);
    $nNF = (int)$st->fetchColumn() + 1;
    $pdo->prepare("UPDATE integracao_nfce SET ultimo_numero_nfce = :n WHERE empresa_id = :e")->execute([':n'=>$nNF, ':e'=>NFCE_EMPRESA_ID]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $nNF = mt_rand(100, 999999);
}

/* Chave e Identificação */
$cUF = pad(COD_UF, 2); $AAMM = date('ym'); $CNPJem = pad(EMIT_CNPJ, 14); $mod = '65'; $serie = pad(NFC_SERIE, 3); $nNFpad = pad($nNF, 9); $tpEmis = '1'; $cNF = pad(mt_rand(1, 99999999), 8);
$base44 = $cUF.$AAMM.$CNPJem.$mod.$serie.$nNFpad.$tpEmis.$cNF;
$cDV = (string)mod11($base44);
$chave = $base44.$cDV;

/* XML Construction */
$destXML = '';
if ($cpf || $cnpj) {
    $destXML = '<dest>'.($cpf?"<CPF>$cpf</CPF>":"<CNPJ>$cnpj</CNPJ>");
    if ($nomeDest) $destXML .= '<xNome>'.e($nomeDest).'</xNome>';
    $destXML .= '<indIEDest>9</indIEDest></dest>';
}

$detXML = ''; $i=1; $vProd=0;
foreach ($itens as $it) {
    $vL = round($it['qtd'] * $it['vun'], 2);
    $vProd += $vL;
    $detXML .= '<det nItem="'.$i.'"><prod><cProd>'.$i.'</cProd><cEAN>SEM GTIN</cEAN><xProd>'.e($it['desc']).'</xProd><NCM>'.$it['ncm'].'</NCM><CFOP>'.$it['cfop'].'</CFOP><uCom>'.e($it['unid']).'</uCom><qCom>'.number_format($it['qtd'],4,'.','').'</qCom><vUnCom>'.number_format($it['vun'],10,'.','').'</vUnCom><vProd>'.number_format($vL,2,'.','').'</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>'.e($it['unid']).'</uTrib><qTrib>'.number_format($it['qtd'],4,'.','').'</qTrib><vUnTrib>'.number_format($it['vun'],10,'.','').'</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det>';
    $i++;
}

$vNF = number_format($vProd, 2, '.', '');
$tPag = mapFormaToTPag($vendaRow['forma_pagamento'] ?? '01');
$vPag = number_format(max($vProd, (float)($vendaRow['valor_recebido'] ?? 0)), 2, '.', '');
$vTr  = number_format(max(0, (float)$vPag - $vProd), 2, '.', '');

$pagXML = '<pag><detPag><indPag>0</indPag><tPag>'.$tPag.'</tPag><vPag>'.$vPag.'</vPag></detPag>';
if ((float)$vTr > 0) $pagXML .= '<vTroco>'.$vTr.'</vTroco>';
$pagXML .= '</pag>';

$xml = '<?xml version="1.0" encoding="UTF-8"?><NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.$chave.'" versao="4.00"><ide><cUF>'.$cUF.'</cUF><cNF>'.$cNF.'</cNF><natOp>VENDA</natOp><mod>65</mod><serie>'.(int)NFC_SERIE.'</serie><nNF>'.$nNF.'</nNF><dhEmi>'.date('c').'</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>'.COD_MUN.'</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>'.$cDV.'</cDV><tpAmb>'.(int)TP_AMB.'</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>NFePHP</verProc></ide><emit><CNPJ>'.EMIT_CNPJ.'</CNPJ><xNome>'.e(EMIT_XNOME).'</xNome><xFant>'.e(EMIT_XFANT).'</xFant><enderEmit><xLgr>'.e(EMIT_XLGR).'</xLgr><nro>'.e(EMIT_NRO).'</nro><xBairro>'.e(EMIT_XBAIRRO).'</xBairro><cMun>'.COD_MUN.'</cMun><xMun>'.e(EMIT_XMUN).'</xMun><UF>'.EMIT_UF.'</UF><CEP>'.EMIT_CEP.'</CEP><cPais>1058</cPais><xPais>Brasil</xPais></enderEmit><IE>'.EMIT_IE.'</IE><CRT>'.EMIT_CRT.'</CRT></emit>'.$destXML.$detXML.'<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>'.$vNF.'</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>'.$vNF.'</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp>'.$pagXML.'<infAdic><infCpl>NFC-e Gerada pelo Sistema</infCpl></infAdic></infNFe></NFe>';

try {
    $sig = $tools->signNFe($xml);
    $res = $tools->sefazEnviaLote([$sig], mt_rand(1, 99999), 1);
    $std = (new Standardize)->toStd($res);
    if (in_array((int)($std->cStat ?? 0), [100, 104])) {
        if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $res, $m)) {
            $proc = nfeproc($sig, $m[1]);
            file_put_contents(__DIR__ . '/procNFCe_'.$chave.'.xml', $proc);
            while (ob_get_level()) ob_end_clean();
            header('Location: danfe_nfce.php?chave='.$chave.'&venda_id='.$venda_id.'&id='.NFCE_EMPRESA_ID);
            exit;
        }
    }
    die('Erro SEFAZ: ' . ($std->xMotivo ?? 'Erro desconhecido') . ' (cStat: ' . ($std->cStat ?? '?') . ')');
} catch (Throwable $e) {
    die('Erro na emissão: '.$e->getMessage());
}