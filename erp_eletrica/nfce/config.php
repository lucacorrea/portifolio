<?php
/**
 * nfce/config.php — CONEXÃO MULTI-BANCO ROBUSTA
 * Sincronizado para encontrar Empresa em qualquer banco conhecido (Açaidinhos pattern).
 */

if (defined('NFCE_CONFIG_LOADED')) { return; }
define('NFCE_CONFIG_LOADED', true);

if(session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Manaus');

// ========== Lista de Bancos Candidatos (Hostinger/Local) ==========
$dbCandidates = [
    'u922223647_erp' => ['u'=>'u922223647_erp', 'p'=>'*V5z7GqLfa~E'], // Açaidinhos Master Config
    'u920914488_ERP' => ['u'=>'u920914488_ERP', 'p'=>'N8r=$&Wrs$'],    // ERP Sales / Review
    'u784961086_pdv' => ['u'=>'u784961086_pdv', 'p'=>'Uv$1NhLlkRub'],  // Local/PDV
];

$pdo = null;
$row = null;
$empresaId = isset($_GET['id']) ? (string)$_GET['id'] : (isset($_SESSION['empresa_id']) ? (string)$_SESSION['empresa_id'] : '1');

// Tenta encontrar a empresa em qualquer um dos bancos
foreach ($dbCandidates as $dbName => $cred) {
    try {
        $tmpPdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", $cred['u'], $cred['p'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        // Tenta buscar em integracao_nfce
        $st = $tmpPdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :e LIMIT 1");
        $st->execute([':e'=>$empresaId]);
        $row = $st->fetch();
        
        // Se não achou, tenta em filiais
        if (!$row) {
            $st = $tmpPdo->prepare("SELECT id AS empresa_id, cnpj, razao_social, nome AS nome_fantasia, inscricao_estadual, cep, logradouro, numero AS numero_endereco, bairro, municipio AS cidade, uf, certificado_pfx AS certificado_digital, certificado_senha AS senha_certificado, ambiente, crt AS regime_tributario, csc_token AS csc, csc_id, serie_nfce FROM filiais WHERE id = :e LIMIT 1");
            $st->execute([':e'=>preg_replace('/\D+/', '', $empresaId)]);
            $row = $st->fetch();
        }
        
        if ($row) {
            $pdo = $tmpPdo;
            break; // SUCESSO: Empresa encontrada!
        }
    } catch (Throwable $e) { /* continua para o próximo */ }
}

if (!$row || !$pdo) {
    http_response_code(404);
    die("Config NFC-e: empresa '$empresaId' não encontrada em nenhum banco conhecido.");
}

// ========== Constantes de Configuração ==========
define('TP_AMB',     (string)($row['ambiente'] ?? '2'));
define('ID_TOKEN',   (string)($row['csc_id'] ?? ''));
define('CSC',        (string)($row['csc'] ?? ''));
define('NFC_SERIE',  (string)($row['serie_nfce'] ?? '1'));

define('EMIT_CNPJ',  preg_replace('/\D+/', '', (string)($row['cnpj'] ?? '')));
define('EMIT_XNOME', trim((string)($row['razao_social'] ?? '')));
define('EMIT_XFANT', trim((string)($row['nome_fantasia'] ?? '')));
define('EMIT_IE',    preg_replace('/\D+/', '', (string)($row['inscricao_estadual'] ?? '')));
define('EMIT_CRT',   (string)($row['regime_tributario'] ?? '1'));

define('EMIT_XLGR',    trim((string)($row['logradouro'] ?? '')));
define('EMIT_NRO',     trim((string)($row['numero_endereco'] ?? '')));
define('EMIT_XBAIRRO', trim((string)($row['bairro'] ?? '')));
define('EMIT_XMUN',    trim((string)($row['cidade'] ?? '')));
define('EMIT_UF',      trim((string)($row['uf'] ?? '')));
define('EMIT_CEP',     preg_replace('/\D+/', '', (string)($row['cep'] ?? '')));
define('EMIT_CMUN',    (string)($row['codigo_municipio'] ?? ''));
define('COD_MUN',      EMIT_CMUN);
define('COD_UF',       substr(EMIT_CMUN, 0, 2));

/* Certificado */
function resolve_pfx($file) {
    if (!$file) return null;
    $base = basename($file);
    $paths = [
        $_SERVER['DOCUMENT_ROOT'].'/assets/img/certificado/'.$base,
        __DIR__.'/certificados/'.$base,
        __DIR__.'/'.$base
    ];
    foreach($paths as $p) if(is_file($p)) return $p;
    return null;
}
$PFX_PATH = resolve_pfx($row['certificado_digital']);
if (!$PFX_PATH) die("PFX não encontrado: ".$row['certificado_digital']);

define('PFX_PATH',     $PFX_PATH);
define('PFX_PASSWORD', (string)$row['senha_certificado']);
define('NFCE_EMPRESA_ID', $row['empresa_id']);

/* URLs SEFAZ */
$URL_QR = ($row['uf'] == 'AM') 
    ? (TP_AMB == '1' ? 'https://nfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp' : 'https://homnfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp')
    : (TP_AMB == '1' ? 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx' : 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx');
define('URL_QR', $URL_QR);
define('URL_CHAVE', $URL_QR);

// A partir daqui, $pdo está conectado ao banco onde a empresa foi achada.
// $pdoSales é o banco onde ficam as vendas (Review/PDV)
$pdoSales = null;
try {
    $c = $dbCandidates['u920914488_ERP'];
    $pdoSales = new PDO("mysql:host=localhost;dbname=u920914488_ERP;charset=utf8mb4", $c['u'], $c['p'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) { $pdoSales = $pdo; } // Fallback pro mesmo da config
