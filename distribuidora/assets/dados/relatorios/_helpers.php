<?php
declare(strict_types=1);

/**
 * _helpers.php
 * Coloque em: /assets/dados/_helpers.php
 *
 * Usado pelos seus .php para:
 *  - escapar HTML (e)
 *  - CSRF (csrf_token, csrf_validate, csrf_input, csrf_validate_or_die)
 *  - flash messages (flash_set, flash_pop)
 *  - helpers de request (post, get, is_post)
 *  - helpers de número/moeda (brl_to_float, float_to_brl)
 *  - redirect e conversões seguras (redirect, to_int, to_str)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   BASICS
========================= */

function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function get(string $key, $default = null) {
  return $_GET[$key] ?? $default;
}

function post(string $key, $default = null) {
  return $_POST[$key] ?? $default;
}

function to_int($value, int $default = 0): int {
  if ($value === null) return $default;
  if (is_int($value)) return $value;
  $s = trim((string)$value);
  if ($s === '') return $default;
  if (!preg_match('/^-?\d+$/', $s)) return $default;
  return (int)$s;
}

function to_str($value, string $default = ''): string {
  if ($value === null) return $default;
  $s = trim((string)$value);
  return $s === '' ? $default : $s;
}

function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}

/* =========================
   CSRF
========================= */

function csrf_token(): string {
  if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['_csrf_token'];
}

function csrf_input(): string {
  $t = csrf_token();
  return '<input type="hidden" name="_csrf" value="' . e($t) . '">';
}

function csrf_validate(?string $token): bool {
  $token = (string)($token ?? '');
  $sess  = (string)($_SESSION['_csrf_token'] ?? '');
  if ($token === '' || $sess === '') return false;
  return hash_equals($sess, $token);
}

function csrf_validate_or_die(): void {
  if (!is_post()) return;

  $tok = post('_csrf', '');
  if (!csrf_validate($tok)) {
    http_response_code(419); // Authentication Timeout (comumente usado p/ CSRF)
    echo 'CSRF inválido. Atualize a página e tente novamente.';
    exit;
  }
}

/* =========================
   FLASH MESSAGES
========================= */

function flash_set(string $key, string $message, string $type = 'info'): void {
  if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
    $_SESSION['_flash'] = [];
  }
  $_SESSION['_flash'][$key] = [
    'message' => $message,
    'type' => $type,
    'time' => time(),
  ];
}

function flash_pop(string $key): ?array {
  if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) return null;
  if (!array_key_exists($key, $_SESSION['_flash'])) return null;
  $val = $_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return is_array($val) ? $val : null;
}

/**
 * Renderiza flash como alert bootstrap
 * Ex: echo flash_html('ok');
 */
function flash_html(string $key): string {
  $f = flash_pop($key);
  if (!$f) return '';

  $type = $f['type'] ?? 'info';
  $msg  = $f['message'] ?? '';

  $map = [
    'success' => 'success',
    'ok'      => 'success',
    'info'    => 'info',
    'warning' => 'warning',
    'warn'    => 'warning',
    'danger'  => 'danger',
    'error'   => 'danger',
  ];
  $cls = $map[strtolower((string)$type)] ?? 'info';

  return '<div class="alert alert-' . e($cls) . ' py-2 px-3 mb-3" role="alert">'
    . e((string)$msg)
    . '</div>';
}

/* =========================
   NUMBER / MONEY
========================= */

/**
 * "R$ 1.234,56" -> 1234.56
 */
function brl_to_float($value): float {
  $s = trim((string)$value);
  if ($s === '') return 0.0;
  $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
  // remove separador milhar
  $s = str_replace('.', '', $s);
  // vírgula vira ponto
  $s = str_replace(',', '.', $s);
  $n = (float)$s;
  return is_finite($n) ? $n : 0.0;
}

/**
 * 1234.56 -> "R$ 1.234,56"
 */
function float_to_brl($value): string {
  $n = (float)$value;
  if (!is_finite($n)) $n = 0.0;
  return 'R$ ' . number_format($n, 2, ',', '.');
}

/* =========================
   SMALL HELPERS
========================= */

function now_sql(): string {
  return date('Y-m-d H:i:s');
}

function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s) ?? '';
}

?>