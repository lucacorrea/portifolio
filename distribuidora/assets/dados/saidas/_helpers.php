<?php
declare(strict_types=1);

/**
 * Arquivo: ./assets/dados/_helpers.php
 * Padrão do projeto: PDO + Flash + CSRF + Redirect
 */

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** =========================
 *  Redirect
 *  ========================= */
function redirect_to(string $url): void {
  header("Location: {$url}");
  exit;
}

/** =========================
 *  Flash (mensagens)
 *  ========================= */
function flash_set(string $type, string $msg): void {
  // types: success, danger, warning, info
  $_SESSION['flash'] = [
    'type' => $type,
    'msg'  => $msg,
  ];
}

function flash_pop(): ?array {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

/** =========================
 *  CSRF
 *  ========================= */
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_token'];
}

function csrf_validate_or_redirect(string $backUrl): void {
  $sess = (string)($_SESSION['csrf_token'] ?? '');
  $post = (string)($_POST['csrf_token'] ?? '');
  if ($sess === '' || $post === '' || !hash_equals($sess, $post)) {
    flash_set('danger', 'Falha de segurança (CSRF). Atualize a página e tente novamente.');
    redirect_to($backUrl);
  }
}

/** =========================
 *  Guardas de requisição
 *  ========================= */
function require_post_or_redirect(string $backUrl): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('warning', 'Ação inválida.');
    redirect_to($backUrl);
  }
}

/** =========================
 *  Helpers numéricos
 *  ========================= */
function brl_to_float(string $v): float {
  $s = trim($v);
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $n = (float)$s;
  return $n;
}

function float_to_brl($n): string {
  return 'R$ ' . number_format((float)$n, 2, ',', '.');
}

?>