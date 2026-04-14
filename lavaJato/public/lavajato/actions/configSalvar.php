<?php
// autoErp/public/lavajato/actions/configSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Guarda e conexão ---
require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo']); // apenas quem pode configurar

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!$pdo instanceof PDO) {
  http_response_code(500);
  exit('Conexão indisponível.');
}

// --- Utilidades ---
function redirect_cfg(string $msg, bool $err = false): void {
  $q = http_build_query([
    $err ? 'err' : 'ok' => 1,
    'msg' => $msg,
  ]);
  header('Location: ../pages/configuracoes.php?' . $q);
  exit;
}

function normaliza_pct(string $raw): float {
  // aceita "12,5" ou "12.5" e limita 0..100
  $v = str_replace(['.', ','], ['.', '.'], trim($raw));
  $num = is_numeric($v) ? (float)$v : 0.0;
  if ($num < 0)   $num = 0.0;
  if ($num > 100) $num = 100.0;
  // 2 casas para banco DECIMAL(5,2) por ex.
  return round($num, 2);
}

function bool_from_checkbox(?string $v): int {
  return !empty($v) ? 1 : 0;
}

// --- Somente POST ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  redirect_cfg('Método inválido.', true);
}

// --- CSRF ---
$csrfForm = (string)($_POST['csrf'] ?? '');
$csrfSess = (string)($_SESSION['csrf_lavajato_cfg'] ?? '');
if (!$csrfForm || !$csrfSess || !hash_equals($csrfSess, $csrfForm)) {
  redirect_cfg('Falha de segurança (CSRF). Atualize a página e tente novamente.', true);
}

// --- Empresa (CNPJ “limpo”) ---
$cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));
if (!$cnpj) {
  redirect_cfg('Empresa não identificada.', true);
}

// --- Leitura e saneamento dos campos ---
$utilidades_pct       = normaliza_pct((string)($_POST['utilidades_pct'] ?? '0'));
$comissao_lavador_pct = normaliza_pct((string)($_POST['comissao_lavador_pct'] ?? '0'));

$permitir_publico_qr  = bool_from_checkbox($_POST['permitir_publico_qr'] ?? null);
$imprimir_auto        = bool_from_checkbox($_POST['imprimir_auto'] ?? null);

$allowed_pag = ['dinheiro','pix','debito','credito','boleto','outro'];
$forma_padrao = (string)($_POST['forma_pagamento_padrao'] ?? 'dinheiro');
if (!in_array($forma_padrao, $allowed_pag, true)) {
  $forma_padrao = 'dinheiro';
}

$obs = trim((string)($_POST['obs'] ?? ''));

// --- Regras simples (exemplo de alerta, não bloqueia) ---
// Ex.: se desejar bloquear quando soma > 100, troque para redirect_cfg(..., true)
$alerta = null;
if ($utilidades_pct + $comissao_lavador_pct > 100) {
  $alerta = 'Atenção: soma de percentuais ultrapassa 100%.';
}

// --- Persistência: UPDATE -> INSERT (se não existir) ---
try {
  $pdo->beginTransaction();

  $sqlUpd = "
    UPDATE lavjato_config_peca
       SET utilidades_pct = :u,
           comissao_lavador_pct = :c,
           permitir_publico_qr = :qr,
           imprimir_auto = :imp,
           forma_pagamento_padrao = :fp,
           obs = :obs
     WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :cnpj
     LIMIT 1
  ";
  $st = $pdo->prepare($sqlUpd);
  $st->execute([
    ':u'    => $utilidades_pct,
    ':c'    => $comissao_lavador_pct,
    ':qr'   => $permitir_publico_qr,
    ':imp'  => $imprimir_auto,
    ':fp'   => $forma_padrao,
    ':obs'  => $obs,
    ':cnpj' => $cnpj,
  ]);

  if ($st->rowCount() === 0) {
    // não existia registro -> INSERT
    $sqlIns = "
      INSERT INTO lavjato_config_peca
        (empresa_cnpj, utilidades_pct, comissao_lavador_pct, permitir_publico_qr, imprimir_auto, forma_pagamento_padrao, obs)
      VALUES
        (:empresa_cnpj, :u, :c, :qr, :imp, :fp, :obs)
    ";
    $si = $pdo->prepare($sqlIns);
    $si->execute([
      ':empresa_cnpj' => $cnpj, // pode ser com máscara ou não, usamos como veio da sessão
      ':u'    => $utilidades_pct,
      ':c'    => $comissao_lavador_pct,
      ':qr'   => $permitir_publico_qr,
      ':imp'  => $imprimir_auto,
      ':fp'   => $forma_padrao,
      ':obs'  => $obs,
    ]);
  }

  $pdo->commit();

  $msgOk = 'Configurações salvas com sucesso.';
  if ($alerta) $msgOk .= ' ' . $alerta;
  redirect_cfg($msgOk, false);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  // Log opcional
  // error_log('configSalvar erro: ' . $e->getMessage());

  redirect_cfg('Erro ao salvar configurações: ' . $e->getMessage(), true);
}
