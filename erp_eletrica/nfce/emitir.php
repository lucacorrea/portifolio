<?php
/**
 * nfce/emitir.php — Emissor unificado e robusto
 * Baseado na lógica 100% funcional do sistema Açaidinhos.
 * Adaptado para o banco de dados u784961086_pdv (ERP Elétrica).
 */
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

try {
    session_start();

    // Carrega Autoload do NFePHP
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }


// Carrega Configuração (Banco de Dados e constantes da Empresa do banco u784961086_pdv)
require_once __DIR__ . '/config.php';

// Validação Crítica de Configuração (Check early)
if (empty(EMIT_CNPJ) || strlen(EMIT_UF) < 2) {
    while (ob_get_level()) ob_end_clean();
    die('[V3-DEBUG] Configuração Fiscal Incompleta! ' .
        'empresaId resolvida: ' . (defined('NFCE_EMPRESA_ID') ? NFCE_EMPRESA_ID : 'não definida') . ', ' .
        'vendaId: ' . (isset($venda_id) ? $venda_id : 'não definida') . '. ' .
        'Verifique se a filial tem CNPJ e UF preenchidos no banco u784961086_pdv.');
}

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
$vendaRow = null; 

if ($venda_id) {
    try {
        // Se itens ainda não vieram via POST (ex: auto_emit), busca do banco u784961086_pdv
        if (empty($itens)) {
            $sti = $pdo->prepare("SELECT i.*, p.nome as produto_nome, p.unidade as p_unidade, p.ncm as p_ncm, p.origem as p_origem, p.cest as p_cest
                                    FROM vendas_itens i 
                                    JOIN produtos p ON i.produto_id = p.id 
                                   WHERE i.venda_id = :id ORDER BY i.id");
            $sti->execute([':id'=>$venda_id]);
            while ($r = $sti->fetch()) {
                $itens[] = [
                    'desc'   => (string)($r['produto_nome'] ?? $r['produto_id']),
                    'qtd'    => (float)$r['quantidade'],
                    'vun'    => (float)$r['preco_unitario'],
                    'ncm'    => (string)($r['ncm'] ?: $r['p_ncm'] ?: '21069090'),
                    'cfop'   => (string)($r['cfop'] ?: '5102'),
                    'unid'   => (string)($r['unidade'] ?: $r['p_unidade'] ?: 'UN'),
                    'origem' => (string)($r['origem'] ?: $r['p_origem'] ?: '0'),
                    'cest'   => (string)($r['cest'] ?: $r['p_cest'] ?: ''),
                ];
            }
        }
        
        // Busca dados da venda no banco u784961086_pdv (Trata cliente avulso)
        $stV = $pdo->prepare("SELECT v.*, 
                                     COALESCE(NULLIF(v.cpf_cliente,''), NULLIF(c.cpf_cnpj,'')) AS cliente_doc, 
                                     COALESCE(NULLIF(v.cliente_nome,''), NULLIF(v.nome_cliente_avulso,''), c.nome) AS cliente_nome 
                                FROM vendas v
                                LEFT JOIN clientes c ON v.cliente_id = c.id
                               WHERE v.id = :v LIMIT 1");
        $stV->execute([':v' => $venda_id]);
        $vendaRow = $stV->fetch();
    } catch (Throwable $e) { /* silencioso */ }
}

if (!$itens) die('[V2-DEBUG] Sem itens. Verifique se a venda #'.$venda_id.' possui produtos cadastrados na tabela vendas_itens no banco u784961086_pdv.');

// Normaliza itens (Garante campos mínimos)
$_norm = [];
foreach ($itens as $it) {
    $d = $it['desc'] ?? $it['produto_nome'] ?? '';
    $q = (float)($it['qtd'] ?? $it['quantidade'] ?? 1);
    $v = (float)($it['vun'] ?? $it['preco_unitario'] ?? 0);
    if ($d !== '' && $q > 0) {
        $_norm[] = [
            'desc'  => $d, 'qtd' => $q, 'vun' => $v,
            'unid'  => (string)($it['unid'] ?? $it['unidade'] ?? 'UN'),
            'ncm'   => (string)($it['ncm'] ?? '21069090'),
            'cfop'  => (string)($it['cfop'] ?? '5102'),
            'origem'=> (string)($it['origem'] ?? '0')
        ];
    }
}
$itens = $_norm;

/* ===== Destinatário — Regras de Identificação =====
 * 1. Se CPF 11 dígitos vier via POST (selecionado no banco ou avulso) → imprime com nome (se houver)
 * 2. Se nome_dest vier via POST E cliente encontrado no banco com nome+CPF → imprime ambos
 * 3. Se só nome sem CPF e não encontrado no banco → NÃO imprime (sem identificação)
 * 4. Fallback: tenta pegar o CPF salvo na própria venda
 */
$cpf       = '';
$cnpj      = '';
$nomeDest  = null;

// Veio CPF direto via POST (avulso ou selecionado)
$docInput = soDig($_POST['cpf'] ?? '');
if (strlen($docInput) === 11) {
    $cpf = $docInput;
    // Tenta pegar the nome se veio via POST (cliente selecionado do banco)
    $nomePost = trim($_POST['nome_dest'] ?? '');
    if ($nomePost !== '') {
        $nomeDest = $nomePost;
    } else {
        // Busca nome no banco pelo CPF (cpf_cnpj pode ser armazenado com pontuação)
        try {
            $stN = $pdo->prepare("SELECT nome FROM clientes 
                                   WHERE REGEXP_REPLACE(cpf_cnpj, '[^0-9]', '') = :d LIMIT 1");
            $stN->execute([':d' => $cpf]);
            $row = $stN->fetch();
            if (!$row) {
                // Fallback sem REGEXP_REPLACE
                $stN2 = $pdo->prepare("SELECT nome FROM clientes WHERE cpf_cnpj LIKE :d LIMIT 1");
                $stN2->execute([':d' => '%'.$cpf.'%']);
                $row = $stN2->fetch();
            }
            if ($row) $nomeDest = $row['nome'];
        } catch (Throwable $e) {
            // Fallback simples
            try {
                $stN3 = $pdo->prepare("SELECT nome FROM clientes WHERE cpf_cnpj LIKE :d LIMIT 1");
                $stN3->execute([':d' => '%'.$cpf.'%']);
                $rowF = $stN3->fetch();
                if ($rowF) $nomeDest = $rowF['nome'];
            } catch (Throwable $e2) {}
        }
    }
} elseif (strlen($docInput) === 14) {
    $cnpj = $docInput;
    $nomePost = trim($_POST['nome_dest'] ?? '');
    if ($nomePost !== '') $nomeDest = $nomePost;
} else {
    // Sem CPF via POST — tenta pegar da venda (cliente vinculado)
    if ($vendaRow) {
        $rDoc = soDig($vendaRow['cliente_doc'] ?? '');
        if (strlen($rDoc) === 11) {
            $cpf = $rDoc;
            $nomeDest = $vendaRow['cliente_nome'] ?? null;
        } elseif (strlen($rDoc) === 14) {
            $cnpj = $rDoc;
            $nomeDest = $vendaRow['cliente_nome'] ?? null;
        }
    }
}

    /* Certificado */
    $pfx = file_get_contents(PFX_PATH);
    $cert = Certificate::readPfx($pfx, PFX_PASSWORD);


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

/* nNF transacional - Atualizado para sefaz_config no banco u784961086_pdv */
$nNF = null;
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT ultimo_numero_nfce FROM sefaz_config LIMIT 1 FOR UPDATE");
    $st->execute();
    $res = $st->fetch();
    
    if ($res !== false) {
        $nNF = (int)$res['ultimo_numero_nfce'] + 1;
        $pdo->prepare("UPDATE sefaz_config SET ultimo_numero_nfce = :n")->execute([':n'=>$nNF]);
    } else {
        $nNF = mt_rand(100, 999999);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $nNF = mt_rand(100, 999999);
}

/* Chave e Identificação */
$cUF = pad(COD_UF, 2); $AAMM = date('ym'); $CNPJem = pad(EMIT_CNPJ, 14); $mod = '65'; $serie = pad(NFC_SERIE, 3); $nNFpad = pad($nNF, 9); $tpEmis = '1'; $cNF = pad(mt_rand(1, 99999999), 8);
$base44 = $cUF.$AAMM.$CNPJem.$mod.$serie.$nNFpad.$tpEmis.$cNF;
$cDV    = (string)mod11($base44);
$chave  = $base44.$cDV;
$IdNFe  = 'NFe'.$chave;

/* ===== ide / emit / dest ===== */
$ide = [
  'cUF'=>$cUF,'cNF'=>$cNF,'natOp'=>'VENDA','mod'=>65,'serie'=>(int)$serie,'nNF'=>$nNF,
  'dhEmi'=>date('c'),'tpNF'=>1,'idDest'=>1,'cMunFG'=>COD_MUN,'tpImp'=>4,'tpEmis'=>1,'cDV'=>(int)$cDV,
  'tpAmb'=>(int)TP_AMB,'finNFe'=>1,'indFinal'=>1,'indPres'=>1,'procEmi'=>0,'verProc'=>'PDV-ACAI-1.0'
];
$enderEmit = [
  'xLgr'=>EMIT_XLGR,'nro'=>EMIT_NRO,'xBairro'=>EMIT_XBAIRRO,'cMun'=>COD_MUN,'xMun'=>EMIT_XMUN,
  'UF'=>EMIT_UF,'CEP'=>EMIT_CEP,'cPais'=>1058,'xPais'=>'Brasil'
];
if (defined('EMIT_FONE') && preg_match('/^\d{6,14}$/', (string)EMIT_FONE)) {
  $enderEmit['fone'] = EMIT_FONE;
}

$emit = [
  'CNPJ'=>EMIT_CNPJ,'xNome'=>EMIT_XNOME,'xFant'=>EMIT_XFANT,'IE'=>EMIT_IE,'CRT'=>EMIT_CRT,
  'enderEmit'=>$enderEmit
];
$dest = [];
if ($cpf)  $dest = ['CPF'=>$cpf,'indIEDest'=>9];
if ($cnpj) $dest = ['CNPJ'=>$cnpj,'indIEDest'=>9];

/* XML Construction */
$destXML = '';
if ($cpf || $cnpj) {
    // xNome é obrigatório no elemento dest — usa nome do cliente ou 'Consumidor Final' para CPF avulso
    $xNomeDest = $nomeDest ?: 'Consumidor Final';
    $destXML = '<dest>'
              . ($cpf ? "<CPF>$cpf</CPF>" : "<CNPJ>$cnpj</CNPJ>")
              . '<xNome>' . e($xNomeDest) . '</xNome>'
              . '<indIEDest>9</indIEDest></dest>';
}

$detXML = ''; $i=1; $vProd=0;
foreach ($itens as $it) {
    $vL = round($it['qtd'] * $it['vun'], 2);
    $vProd += $vL;
    $detXML .= '<det nItem="'.$i.'"><prod><cProd>'.$i.'</cProd><cEAN>SEM GTIN</cEAN><xProd>'.e($it['desc']).'</xProd><NCM>'.$it['ncm'].'</NCM><CFOP>'.$it['cfop'].'</CFOP><uCom>'.e($it['unid']).'</uCom><qCom>'.number_format($it['qtd'],4,'.','').'</qCom><vUnCom>'.number_format($it['vun'],10,'.','').'</vUnCom><vProd>'.number_format($vL,2,'.','').'</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>'.e($it['unid']).'</uTrib><qTrib>'.number_format($it['qtd'],4,'.','').'</qTrib><vUnTrib>'.number_format($it['vun'],10,'.','').'</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>'.$it['origem'].'</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det>';
    $i++;
}

$vNF = number_format($vProd, 2, '.', '');
$tPag = mapFormaToTPag($vendaRow['forma_pagamento'] ?? '01');
$vPag = number_format(max($vProd, (float)($vendaRow['valor_recebido'] ?? 0)), 2, '.', '');
$vTr  = number_format(max(0, (float)$vPag - $vProd), 2, '.', '');

$pagXML = '<pag><detPag><indPag>0</indPag><tPag>'.$tPag.'</tPag><vPag>'.$vPag.'</vPag></detPag>';
if ((float)$vTr > 0) $pagXML .= '<vTroco>'.$vTr.'</vTroco>';
$pagXML .= '</pag>';
// ===== FIM DO NOVO BLOCO =====

$totXML = '<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>'.number_format($vProd, 2, '.', '').'</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>'.number_format($vProd, 2, '.', '').'</vNF></ICMSTot></total>';
$transpXML = '<transp><modFrete>9</modFrete></transp>';

$infAd   = '<infAdic><infCpl>PDV Açaiteria</infCpl></infAdic>';

/* ===== MONTA SÓ A NFe (para assinar) ===== */
$nfe = '<?xml version="1.0" encoding="UTF-8"?>'
     . '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
     .   '<infNFe Id="'.$IdNFe.'" versao="4.00">'
     .     '<ide>'
     .       '<cUF>'.$ide['cUF'].'</cUF><cNF>'.$ide['cNF'].'</cNF><natOp>'.$ide['natOp'].'</natOp>'
     .       '<mod>'.$ide['mod'].'</mod><serie>'.$ide['serie'].'</serie><nNF>'.$ide['nNF'].'</nNF>'
     .       '<dhEmi>'.$ide['dhEmi'].'</dhEmi><tpNF>'.$ide['tpNF'].'</tpNF><idDest>'.$ide['idDest'].'</idDest>'
     .       '<cMunFG>'.$ide['cMunFG'].'</cMunFG><tpImp>'.$ide['tpImp'].'</tpImp><tpEmis>'.$ide['tpEmis'].'</tpEmis>'
     .       '<cDV>'.$ide['cDV'].'</cDV><tpAmb>'.$ide['tpAmb'].'</tpAmb><finNFe>'.$ide['finNFe'].'</finNFe>'
     .       '<indFinal>'.$ide['indFinal'].'</indFinal><indPres>'.$ide['indPres'].'</indPres>'
     .       '<procEmi>'.$ide['procEmi'].'</procEmi><verProc>'.$ide['verProc'].'</verProc>'
     .     '</ide>'
     .     '<emit>'
     .       '<CNPJ>'.$emit['CNPJ'].'</CNPJ><xNome>'.$emit['xNome'].'</xNome><xFant>'.$emit['xFant'].'</xFant>'
     .       '<enderEmit><xLgr>'.$emit['enderEmit']['xLgr'].'</xLgr><nro>'.$emit['enderEmit']['nro'].'</nro><xBairro>'.$emit['enderEmit']['xBairro'].'</xBairro><cMun>'.$emit['enderEmit']['cMun'].'</cMun><xMun>'.$emit['enderEmit']['xMun'].'</xMun><UF>'.$emit['enderEmit']['UF'].'</UF><CEP>'.$emit['enderEmit']['CEP'].'</CEP><cPais>'.$emit['enderEmit']['cPais'].'</cPais><xPais>'.$emit['enderEmit']['xPais'].'</xPais>'.(!empty($emit['enderEmit']['fone'])?'<fone>'.$emit['enderEmit']['fone'].'</fone>':'').'</enderEmit>'
     .       '<IE>'.$emit['IE'].'</IE><CRT>'.$emit['CRT'].'</CRT>'
     .     '</emit>'
     .     $destXML
     .     $detXML
     .     $totXML
     .     $transpXML
     .     $pagXML
     .     $infAd
     .     '<infRespTec><CNPJ>65975879000132</CNPJ><xContato>l&j solucoes tecnologicas</xContato><email>lucasscorrea0@gmail.com</email><fone>9291515710</fone></infRespTec>'
     .   '</infNFe>'
     . '</NFe>';

/* ===== Assina SOMENTE a NFe ===== */
try { $nfeAss = $tools->signNFe($nfe); }
catch (Throwable $e) { die('<pre>Falha ao assinar: '.$e->getMessage().'</pre>'); }

/* ===== Envia — passa ARRAY de NFe assinadas ===== */
try {
  $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', 1);
} catch (Throwable $e) {
  try { $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', false, 1); }
  catch (Throwable $e2) { die('<pre>Falha na autorização: '.$e->getMessage()."\n\n".$e2->getMessage()."</pre>"); }
}

$stdEnv = (new Standardize)->toStd($respEnv);

/* ===== Trata retorno ===== */
if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 104) {
  if (!preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $respEnv, $mProt)) {
    die("Autorizado, mas não localizei protNFe no retorno.");
  }
  $proc = nfeproc($nfeAss, $mProt[1]);
  $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
  file_put_contents($xmlPath, $proc);


  // ===== Persistir retorno SEFAZ 104 em nfce_emitidas (antes do redirect) =====
  try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      $__found = false;
      foreach ([__DIR__ . '/../conexao/conexao.php', __DIR__ . '/../../conexao/conexao.php', __DIR__ . '/../../../conexao/conexao.php'] as $__p) {
        if (is_file($__p)) { require_once $__p; $__found = true; break; }
      }
    }
    if (isset($pdo) && ($pdo instanceof PDO)) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $cStat   = (string)($stdEnv->cStat ?? '');
      $xMotivo = (string)($stdEnv->xMotivo ?? '');
      $tpAmb   = (int)TP_AMB;
      $serieI  = (int)NFC_SERIE;
      $nnfI    = isset($nNF) ? (int)$nNF : 0;
      $vendaId = isset($venda_id) ? (string)$venda_id : (isset($_GET['venda_id']) ? (string)$_GET['venda_id'] : null);
      $chaveI  = isset($chave) ? (string)$chave : (isset($chaveI) ? (string)$chaveI : '');

      $xmlProcContent = isset($proc) ? $proc : (isset($xmlProcPath) && is_file($xmlProcPath) ? @file_get_contents($xmlProcPath) : null);
      $xmlEnvio       = isset($nfeAss) ? $nfeAss : (isset($nfe) ? $nfe : null);
      $xmlRetorno     = isset($respEnv) ? $respEnv : (isset($ret) ? $ret : null);

      // Extrai protocolo do protNFe capturado ($mProt)
      $nProt = null;
      if (isset($mProt[1]) && preg_match('~<nProt>([^<]+)</nProt>~', $mProt[1], $m2)) {
        $nProt = $m2[1];
      }

      // Monta JSON de pagamento se existir código de tPag
      $tpagJsonStr = isset($tPag) ? json_encode(['tPag' => $tPag], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

      // tenta identificar vNF
      $vNFnum = null;
      if (isset($proc) && preg_match('~<vNF>([0-9]+\.[0-9]{2})</vNF>~', $proc, $mV)) {
        $vNFnum = $mV[1];
      } elseif (isset($nfeAss) && preg_match('~<vNF>([0-9]+\.[0-9]{2})</vNF>~', $nfeAss, $mV)) {
        $vNFnum = $mV[1];
      }

      $vTrocoVal = isset($vTr) ? $vTr : null;

      $st = $pdo->prepare("
        INSERT INTO nfce_emitidas
          (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem,
           xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco, tpag_json)
        VALUES
          (:empresa_id, :venda_id, :ambiente, :serie, :numero, :chave, :protocolo, :status_sefaz, :mensagem,
           :xml_nfeproc, :xml_envio, :xml_retorno, :valor_total, :valor_troco, :tpag_json)
        ON DUPLICATE KEY UPDATE
          protocolo    = VALUES(protocolo),
          status_sefaz = VALUES(status_sefaz),
          mensagem     = VALUES(mensagem),
          xml_nfeproc  = VALUES(xml_nfeproc),
          xml_envio    = VALUES(xml_envio),
          xml_retorno  = VALUES(xml_retorno),
          valor_total  = VALUES(valor_total),
          valor_troco  = VALUES(valor_troco),
          tpag_json    = VALUES(tpag_json),
          created_at   = NOW()
      ");

      $st->execute([
        ':empresa_id'   => defined('NFCE_EMPRESA_ID') ? constant('NFCE_EMPRESA_ID') : (defined('EMPRESA_ID') ? constant('EMPRESA_ID') : ''),
        ':venda_id'     => $vendaId,
        ':ambiente'     => $tpAmb,
        ':serie'        => $serieI,
        ':numero'       => $nnfI,
        ':chave'        => $chaveI,
        ':protocolo'    => $nProt,
        ':status_sefaz' => $cStat,
        ':mensagem'     => $xMotivo,
        ':xml_nfeproc'  => $xmlProcContent,
        ':xml_envio'    => $xmlEnvio,
        ':xml_retorno'  => $xmlRetorno,
        ':valor_total'  => isset($vNFnum) ? number_format((float)$vNFnum, 2, '.', '') : null,
        ':valor_troco'  => isset($vTrocoVal) ? number_format((float)$vTrocoVal, 2, '.', '') : null,
        ':tpag_json'    => $tpagJsonStr
      ]);
    }
  } catch (Throwable $e) {
    error_log('Falha ao gravar nfce_emitidas (104): ' . $e->getMessage());
  }
   $empresaId = $_POST['empresa_id'] ?? ($_GET['id'] ?? '');
  // ======= SEM AUTO-PRINT: só mostra link para abrir/imprimir =======
  // ======= AGORA: redireciona direto para o DANFE =======
  $danfeUrl = 'danfe_nfce.php?chave=' . urlencode($chave)
     . '&venda_id=' . urlencode((string)$vendaId)
     . '&id=' . urlencode($empresaId);
  // limpa qualquer saída anterior para poder enviar headers
  while (ob_get_level() > 0) { ob_end_clean(); }
  if (!headers_sent()) {
    header('Location: ' . $danfeUrl);
    exit;
  }
  // Fallback JS se headers já enviados
  echo '<!doctype html><meta charset="utf-8">';
  echo '<script>location.replace(' . json_encode($danfeUrl) . ');</script>';
  exit;
}

if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 103 && !empty($stdEnv->infRec->nRec)) {
  $nRec = (string)$stdEnv->infRec->nRec;
  sleep(2);
  $ret = $tools->sefazConsultaRecibo($nRec);
  if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $ret, $mProt)) {
    $proc = nfeproc($nfeAss, $mProt[1]);
    $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
    file_put_contents($xmlPath, $proc);

    // ===== Persistir retorno SEFAZ no BD =====
    try {
      if (!isset($pdo) || !($pdo instanceof PDO)) {
        $__found = false;
        foreach ([ __DIR__ . '/../../assets/php/conexao.php',
  __DIR__ . '/../../ERP/assets/php/conexao.php',
  __DIR__ . '/../dashboard/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/ERP/assets/php/conexao.php',] as $__p) {
          if (is_file($__p)) { require_once $__p; $__found = true; break; }
        }
      }
      if (isset($pdo) && ($pdo instanceof PDO)) {
        $cStat   = (string)($stdEnv->cStat ?? '');
        $xMotivo = (string)($stdEnv->xMotivo ?? '');
        $tpAmb   = (string)TP_AMB;
        $serieI  = (int)NFC_SERIE;
        $nnfI    = (int)$nNF;
        $vendaId = isset($venda_id) ? (string)$venda_id : (isset($_GET['venda_id']) ? (string)$_GET['venda_id'] : null);
        $protXML = '';
        $nProt   = null;
        $dhRecb  = null;
        $chaveI  = (string)$chave;
        if (preg_match('~<protNFe[^>]*>(.*?)</protNFe>~s', $respEnv, $m)) { $protXML = $m[0]; }
        if (preg_match('~<nProt>([^<]+)</nProt>~', $mProt[1], $m2)) { $nProt = $m2[1]; }
        if (preg_match('~<dhRecbto>([^<]+)</dhRecbto>~', $mProt[1], $m3)) { $dhRecb = $m3[1]; }
        $xmlProcPath = __DIR__ . '/procNFCe_' . $chaveI . '.xml';
        // === Persistir em nfce_emitidas ===
$xmlProcContent = isset($proc) ? $proc : ((isset($xmlProcPath) && is_file($xmlProcPath)) ? @file_get_contents($xmlProcPath) : null);
$xmlEnvio       = isset($nfeAss) ? $nfeAss : (isset($nfe) ? $nfe : null);
$xmlRetorno     = isset($ret) ? $ret : (isset($respEnv) ? $respEnv : null);
$tpagJsonStr    = isset($tPag) ? json_encode(['tPag' => $tPag], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

$st = $pdo->prepare("INSERT INTO nfce_emitidas
  (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem, xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco, tpag_json)
  VALUES (:empresa_id, :venda_id, :ambiente, :serie, :numero, :chave, :protocolo, :status_sefaz, :mensagem, :xml_nfeproc, :xml_envio, :xml_retorno, :valor_total, :valor_troco, :tpag_json)
  ON DUPLICATE KEY UPDATE
    protocolo=VALUES(protocolo),
    status_sefaz=VALUES(status_sefaz),
    mensagem=VALUES(mensagem),
    xml_nfeproc=VALUES(xml_nfeproc),
    xml_envio=VALUES(xml_envio),
    xml_retorno=VALUES(xml_retorno),
    valor_total=VALUES(valor_total),
    valor_troco=VALUES(valor_troco),
    tpag_json=VALUES(tpag_json),
    created_at=NOW()
");
$st->execute([
  ':empresa_id'   => NFCE_EMPRESA_ID,
  ':venda_id'     => $vendaId,
  ':ambiente'     => (int)$tpAmb,
  ':serie'        => $serieI,
  ':numero'       => $nnfI,
  ':chave'        => $chaveI,
  ':protocolo'    => isset($nProt) ? $nProt : null,
  ':status_sefaz' => (string)$cStat,
  ':mensagem'     => (string)$xMotivo,
  ':xml_nfeproc'  => $xmlProcContent,
  ':xml_envio'    => $xmlEnvio,
  ':xml_retorno'  => $xmlRetorno,
  ':valor_total'  => isset($vNFnum) ? number_format((float)$vNFnum, 2, '.', '') : null,
  ':valor_troco'  => isset($vTr) ? number_format((float)$vTr, 2, '.', '') : null,
  ':tpag_json'    => $tpagJsonStr
]);
}
    } catch (Throwable $e) {
      // Não bloquear o fluxo por erro de log
      // error_log('Falha ao persistir retorno SEFAZ: '.$e->getMessage());
    }
    
    // ======= AGORA: redireciona direto para o DANFE =======
    $danfeUrl = 'danfe_nfce.php?chave=' . urlencode($chave)
       . '&venda_id=' . urlencode((string)$vendaId);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
      header('Location: ' . $danfeUrl);
      exit;
    }
    echo '<!doctype html><meta charset="utf-8">';
    echo '<script>location.replace(' . json_encode($danfeUrl) . ');</script>';
    exit;
  }
}

// === LOGAR REJEIÇÕES TAMBÉM (sem protNFe) ===
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach ([__DIR__ . '/../conexao/conexao.php', __DIR__ . '/../../conexao/conexao.php', __DIR__ . '/../../../conexao/conexao.php'] as $__p) {
        if (is_file($__p)) { require_once $__p; break; }
    }
}
die('Erro SEFAZ: ' . ($stdEnv->xMotivo ?? 'Erro desconhecido') . ' (cStat: ' . ($stdEnv->cStat ?? '?') . ')');

} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    die('[V3-DEBUG] Erro Fatal na Emissão: ' . $e->getMessage() . ' no arquivo ' . $e->getFile() . ':' . $e->getLine());
}