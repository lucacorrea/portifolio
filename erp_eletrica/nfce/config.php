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

// 1. Busca Global (sefaz_config) - Certificado e CSC Central
$global = [];
try {
    $stG = $pdo->query("SELECT * FROM sefaz_config LIMIT 1");
    $global = $stG->fetch() ?: [];
} catch (Throwable $e) { $errorLog[] = "Tabela 'sefaz_config' inacessível: " . $e->getMessage(); }

// 2. Busca Filial (Unidade atual)
$filial = [];
try {
    $stF = $pdo->prepare("SELECT * FROM filiais WHERE id = ?");
    $stF->execute([$empresaId]);
    $filial = $stF->fetch() ?: [];
} catch (Throwable $e) { $errorLog[] = "Tabela 'filiais' inacessível: " . $e->getMessage(); }

// 3. Busca Matriz (Identidade corporativa base)
$matriz = [];
try {
    $stM = $pdo->query("SELECT * FROM filiais WHERE principal = 1 LIMIT 1");
    $matriz = $stM->fetch() ?: [];
} catch (Throwable $e) {}

if (empty($global) && empty($filial) && empty($matriz)) {
    echo "<h2>Erro de Configuração NFC-e</h2>";
    echo "<p>Não foi possível localizar os dados fiscais no banco.</p>";
    die();
}

// Consolidação com Herança (Lógica idêntica ao NfceService.php)
$fiscal = [];
$globalFields = ['certificado_path', 'certificado_senha', 'ambiente', 'csc', 'csc_id', 'cnpj', 'razao_social', 'inscricao_estadual'];

// Lista de campos para mapeamento (conforme NfceService)
$fields = [
    'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_estadual', 'ambiente', 
    'csc', 'csc_id', 'certificado_path', 'certificado_senha',
    'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'cep', 'codigo_municipio',
    'serie_nfce', 'regime_tributario', 'crt', 'telefone'
];

foreach ($fields as $field) {
    $filialKey = $field;
    if ($field === 'certificado_path') $filialKey = 'certificado_pfx';
    if ($field === 'csc')              $filialKey = 'csc_token';
    if ($field === 'razao_social')     $filialKey = 'razao_social'; // consistente
    
    if (in_array($field, $globalFields)) {
        // Obrigatório vir do Global ou Matriz
        $val = (!empty($global[$field])) ? $global[$field] : ($matriz[$filialKey] ?? null);
    } else {
        // Preferência Filial -> Fallback Matriz
        $val = (!empty($filial[$filialKey])) ? $filial[$filialKey] : ($matriz[$filialKey] ?? null);
    }
    $fiscal[$field] = $val;
}

// ========== Mapeamento de Campos (27 campos) ==========
// ========== Mapeamento de Campos (Tratamento de Vazios) ==========
define('TP_AMB',     (!empty($fiscal['ambiente']) ? (string)$fiscal['ambiente'] : '2'));
define('ID_TOKEN',   (string)($fiscal['csc_id'] ?? ''));
define('CSC',        (string)($fiscal['csc'] ?? $fiscal['csc_token'] ?? ''));
define('NFC_SERIE',  (string)($fiscal['serie_nfce'] ?? '1'));

define('EMIT_CNPJ',  so_digitos($fiscal['cnpj']));
define('EMIT_XNOME', trim((string)($fiscal['razao_social'] ?? $fiscal['nome'] ?? 'EMPRESA SEM RAZAO SOCIAL')));
define('EMIT_XFANT', trim((string)($fiscal['nome'] ?? $fiscal['nome_fantasia'] ?? EMIT_XNOME)));
define('EMIT_IE',    so_digitos($fiscal['inscricao_estadual']));
define('EMIT_CRT',   (string)($fiscal['regime_tributario'] ?? $fiscal['crt'] ?? '1'));

define('EMIT_XLGR',    trim((string)($fiscal['logradouro'] ?? 'Logradouro não informado')));
define('EMIT_NRO',     trim((string)($fiscal['numero_endereco'] ?? $fiscal['numero'] ?? 'S/N')));
define('EMIT_XBAIRRO', trim((string)($fiscal['bairro'] ?? 'Bairro')));
define('EMIT_XMUN',    trim((string)($fiscal['cidade'] ?? $fiscal['municipio'] ?? 'SAO PAULO')));
define('EMIT_UF',      (!empty($fiscal['uf']) ? trim((string)$fiscal['uf']) : 'SP'));
define('EMIT_CEP',     so_digitos($fiscal['cep'] ?? '01001000'));
define('EMIT_CMUN',    (!empty($fiscal['codigo_municipio']) ? (string)$fiscal['codigo_municipio'] : '3550308'));
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
