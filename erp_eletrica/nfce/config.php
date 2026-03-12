<?php
/**
 * nfce/config.php — CONEXÃO PADRONIZADA (Baseada no index.php funcional)
 */

if (defined('NFCE_CONFIG_LOADED')) { return; }
define('NFCE_CONFIG_LOADED', true);

date_default_timezone_set('America/Manaus');

// ========== Conexão DIRETA (Garantida) ==========
$pdo = null;
try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=u920914488_ERP;charset=utf8mb4",
    "u920914488_ERP",
    "N8r=$&Wrs$",
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (PDOException $e) {
  // Se falhar a principal, tenta fallback de urgência (PdV)
  try {
     $pdo = new PDO("mysql:host=localhost;dbname=u784961086_pdv;charset=utf8mb4", "u784961086_pdv", "Uv$1NhLlkRub");
  } catch(Exception $e2) {
     http_response_code(500);
     die('Erro na conexão NFC-e: '.$e->getMessage());
  }
}

// ========== Helpers ==========
if (!function_exists('so_digitos')) {
  function so_digitos(?string $v): ?string {
    if ($v === null) return null;
    $v = preg_replace('/\D+/', '', $v);
    return $v !== '' ? $v : null;
  }
}

/**
 * Resolve o caminho local do certificado .pfx/.p12
 */
function resolve_cert_path(?string $fileFromDb, array &$attempts = []): ?string {
  if (!$fileFromDb) return null;
  $base = basename($fileFromDb);
  $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
  
  $candidates = [
    $docroot . '/assets/img/certificado/' . $base,
    __DIR__   . '/certificados/' . $base,
    __DIR__   . '/' . $base,
  ];
  foreach ($candidates as $c) {
    $attempts[] = $c;
    if (is_file($c)) return $c;
  }
  return null;
}

// ========== Qual empresa carregar? ==========
$empresaId = isset($_GET['id']) ? (string)$_GET['id'] : (isset($_SESSION['empresa_id']) ? (string)$_SESSION['empresa_id'] : '1');

// ========== Lê integracao_nfce ou filiais ==========
$row = null;
try {
  $st = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :emp LIMIT 1");
  $st->execute([':emp' => $empresaId]);
  $row = $st->fetch();
} catch (Throwable $e) {}

if (!$row) {
  try {
    $st = $pdo->prepare("SELECT id AS empresa_id, cnpj, razao_social, nome AS nome_fantasia, inscricao_estadual, cep, logradouro, numero AS numero_endereco, bairro, municipio AS cidade, uf, certificado_pfx AS certificado_digital, certificado_senha AS senha_certificado, ambiente, crt AS regime_tributario, csc_token AS csc, csc_id FROM filiais WHERE id = :emp LIMIT 1");
    $st->execute([':emp' => preg_replace('/\D+/', '', $empresaId)]);
    $row = $st->fetch();
  } catch (Throwable $e) {}
}

if (!$row) die("Config NFC-e: empresa não encontrada: $empresaId");

// ========== Constantes ==========
define('TP_AMB',     (string)($row['ambiente'] ?? '2'));
define('ID_TOKEN',   (string)($row['csc_id'] ?? ''));
define('CSC',        (string)($row['csc'] ?? ''));
define('NFC_SERIE',  (string)($row['serie_nfce'] ?? '1'));

define('EMIT_CNPJ',  so_digitos($row['cnpj']));
define('EMIT_XNOME', trim($row['razao_social'] ?? ''));
define('EMIT_XFANT', trim($row['nome_fantasia'] ?? ''));
define('EMIT_IE',    so_digitos($row['inscricao_estadual']));
define('EMIT_CRT',   (string)($row['regime_tributario'] ?? '1'));

define('EMIT_XLGR',    trim($row['logradouro'] ?? ''));
define('EMIT_NRO',     trim($row['numero_endereco'] ?? ''));
define('EMIT_XBAIRRO', trim($row['bairro'] ?? ''));
define('EMIT_XMUN',    trim($row['cidade'] ?? ''));
define('EMIT_UF',      trim($row['uf'] ?? ''));
define('EMIT_CEP',     so_digitos($row['cep'] ?? ''));
define('EMIT_CMUN',    (string)($row['codigo_municipio'] ?? ''));
define('COD_MUN',      EMIT_CMUN);
define('COD_UF',       substr(EMIT_CMUN, 0, 2));

$PFX_PASSWORD = (string)($row['senha_certificado'] ?? '');
$PFX_PATH     = resolve_cert_path($row['certificado_digital']);

if (!$PFX_PATH) die("PFX não encontrado: " . $row['certificado_digital']);
define('PFX_PATH',     $PFX_PATH);
define('PFX_PASSWORD', $PFX_PASSWORD);

if (!defined('NFCE_EMPRESA_ID')) define('NFCE_EMPRESA_ID', $row['empresa_id']);

$URL_QR = ($row['uf'] == 'AM') 
    ? (TP_AMB == '1' ? 'https://nfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp' : 'https://homnfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp')
    : (TP_AMB == '1' ? 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx' : 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx');
define('URL_QR', $URL_QR);
define('URL_CHAVE', $URL_QR);
