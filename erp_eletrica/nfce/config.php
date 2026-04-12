<?php
/**
 * nfce/config.php — CONEXÃO CENTRALIZADA (u784961086_pdv)
 * Alinhado com NfceService.php do projeto ERP Elétrica.
 */

if (defined('NFCE_CONFIG_LOADED')) { return; }
define('NFCE_CONFIG_LOADED', true);

if(session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Manaus');

// ========== Conexão DIRETA (u784961086_pdv) ==========
$pdo = null;
try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=u784961086_pdv;charset=utf8mb4",
    "u784961086_pdv",
    'Uv$1NhLlkRub',
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  die('Erro na conexão NFC-e (u784961086_pdv): '.$e->getMessage());
}

// Para este sistema, pdoSales é o mesmo que pdo
$pdoSales = $pdo;

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
 * No erp_eletrica, os certificados podem estar em /storage/certificados/ ou /assets/img/certificado/
 */
function resolve_cert_path(?string $fileFromDb): ?string {
  if (!$fileFromDb) return null;
  $base = basename($fileFromDb);
  $root = dirname(__DIR__); // Root do projeto
  
  $candidates = [
    $root . '/storage/certificados/' . $base,
    $root . '/assets/img/certificado/' . $base,
    __DIR__ . '/certificados/' . $base,
    __DIR__ . '/' . $base,
  ];
  foreach ($candidates as $c) {
    if (is_file($c)) return $c;
  }
  return null;
}

// ========== Carregamento de Configuração (Lógica NfceService) ==========
$vendaId   = isset($_REQUEST['venda_id']) ? (int)$_REQUEST['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);
$empresaId = isset($_REQUEST['id']) ? (string)$_REQUEST['id'] : null;

// Se tem venda_id, a filial OBRIGATORIAMENTE deve ser a da venda
if ($vendaId > 0) {
    try {
        $stV = $pdo->prepare("SELECT filial_id FROM vendas WHERE id = ?");
        $stV->execute([$vendaId]);
        $vid = $stV->fetchColumn();
        if ($vid) $empresaId = (string)$vid;
    } catch (Throwable $e) {}
}

// Se não resolveu pela venda, tenta sessão ou padrão
if (!$empresaId) {
    $empresaId = isset($_SESSION['empresa_id']) ? (string)$_SESSION['empresa_id'] : '1';
}

// Debug (opcional, logar no servidor)
error_log("[NFC-e] Resolvido empresaId=$empresaId para vendaId=$vendaId");

// 1. Busca Global (sefaz_config)
$global = [];
try {
    $stG = $pdo->query("SELECT * FROM sefaz_config LIMIT 1");
    $global = $stG->fetch() ?: [];
} catch (Throwable $e) { $errorLog[] = "Tabela 'sefaz_config' inacessível: " . $e->getMessage(); }

// 2. Busca Matriz (principal = 1)
$matriz = [];
try {
    $stM = $pdo->query("SELECT * FROM filiais WHERE principal = 1 LIMIT 1");
    $matriz = $stM->fetch() ?: [];
} catch (Throwable $e) {}

// 3. Busca Filial Atual
$filial = [];
try {
    $stF = $pdo->prepare("SELECT * FROM filiais WHERE id = ?");
    $stF->execute([$empresaId]);
    $filial = $stF->fetch() ?: [];
} catch (Throwable $e) { $errorLog[] = "Tabela 'filiais' inacessível: " . $e->getMessage(); }

if (empty($global) && empty($filial) && empty($matriz)) {
    echo "<h2>Erro de Configuração NFC-e</h2>";
    echo "<p>Não foi possível encontrar os dados da empresa no banco de dados <b>u784961086_pdv</b>.</p>";
    die();
}

// ========== Lógica de Hierarquia (Centralização Fiscal) ==========
// Campos que DEVEM ser globais/matriz
$globalFields = ['ambiente', 'certificado_path', 'certificado_senha', 'csc', 'csc_id', 'cnpj', 'razao_social'];

$fiscal = [];
$allKeys = array_unique(array_merge(array_keys($global), array_keys($matriz), array_keys($filial)));

foreach ($allKeys as $key) {
    // Mapeamento de nomes de colunas (filiais usa nomes diferentes em alguns campos)
    $filialKey = $key;
    if ($key === 'certificado_path') $filialKey = 'certificado_pfx';
    if ($key === 'csc')              $filialKey = 'csc_token';
    if ($key === 'razao_social')     $filialKey = 'nome'; // No banco filiais o campo é 'nome'
    
    if (in_array($key, $globalFields)) {
        // Prioridade: Global -> Matriz -> Filial
        $val = $global[$key] ?? $matriz[$filialKey] ?? $filial[$filialKey] ?? null;
    } else {
        // Prioridade: Filial -> Matriz -> Global
        $val = $filial[$filialKey] ?? $matriz[$filialKey] ?? $global[$key] ?? null;
    }
    $fiscal[$key] = $val;
}

// ========== Mapeamento de Campos (27 campos) ==========
define('TP_AMB',     (string)($fiscal['ambiente'] ?? '2'));
define('ID_TOKEN',   (string)($fiscal['csc_id'] ?? ''));
define('CSC',        (string)($fiscal['csc'] ?? $fiscal['csc_token'] ?? ''));
define('NFC_SERIE',  (string)($fiscal['serie_nfce'] ?? '1'));

define('EMIT_CNPJ',  so_digitos($fiscal['cnpj']));
define('EMIT_XNOME', trim((string)($fiscal['razao_social'] ?? $fiscal['nome'] ?? '')));
define('EMIT_XFANT', trim((string)($fiscal['nome'] ?? $fiscal['nome_fantasia'] ?? '')));
define('EMIT_IE',    so_digitos($fiscal['inscricao_estadual']));
define('EMIT_CRT',   (string)($fiscal['regime_tributario'] ?? $fiscal['crt'] ?? '1'));

define('EMIT_XLGR',    trim((string)($fiscal['logradouro'] ?? '')));
define('EMIT_NRO',     trim((string)($fiscal['numero_endereco'] ?? $fiscal['numero'] ?? '')));
define('EMIT_XBAIRRO', trim((string)($fiscal['bairro'] ?? '')));
define('EMIT_XMUN',    trim((string)($fiscal['cidade'] ?? $fiscal['municipio'] ?? '')));
define('EMIT_UF',      trim((string)($fiscal['uf'] ?? '')));
define('EMIT_CEP',     so_digitos($fiscal['cep']));
define('EMIT_CMUN',    (string)($fiscal['codigo_municipio'] ?? ''));
define('EMIT_FONE',    so_digitos($fiscal['fone'] ?? $fiscal['telefone'] ?? ''));
define('COD_MUN',      EMIT_CMUN);
define('COD_UF',       substr(EMIT_CMUN, 0, 2));

$PFX_PASSWORD = (string)($fiscal['certificado_senha'] ?? '');
$PFX_PATH     = resolve_cert_path($fiscal['certificado_pfx'] ?? $fiscal['certificado_path'] ?? null);

if (!$PFX_PATH) {
    // Se não achou arquivo, mas tem nome no banco, avisa onde procurou
    die("Certificado NFC-e não encontrado. Verifique se o arquivo .pfx existe na pasta /storage/certificados/ ou /assets/img/certificado/");
}

define('PFX_PATH',     $PFX_PATH);
define('PFX_PASSWORD', $PFX_PASSWORD);
define('NFCE_EMPRESA_ID', $empresaId);

$URL_QR = (EMIT_UF == 'AM') 
    ? (TP_AMB == '1' ? 'https://nfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp' : 'https://homnfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp')
    : (TP_AMB == '1' ? 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx' : 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx');
define('URL_QR', $URL_QR);
define('URL_CHAVE', $URL_QR);
