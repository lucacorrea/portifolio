<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   BASICS
 ========================= */
if (!function_exists('e')) {
  function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

function require_db_or_die(): void {
  if (!function_exists('db')) {
    http_response_code(500);
    echo "ERRO: função db():PDO não encontrada. Verifique assets/conexao.php";
    exit;
  }
}

/* =========================
   REQUEST
 ========================= */
function is_post(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function get_str(string $k, string $def = ''): string {
  return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}

function get_int(string $k, int $def = 0): int {
  $v = isset($_GET[$k]) ? (int)$_GET[$k] : $def;
  return $v > 0 ? $v : $def;
}

function post_str(string $k, string $def = ''): string {
  return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def;
}

function post_int(string $k, int $def = 0): int {
  $v = isset($_POST[$k]) ? (int)$_POST[$k] : $def;
  return $v > 0 ? $v : $def;
}

/* =========================
   URL BASE
 ========================= */
function base_path(): string {
  $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $dir === '' ? '' : $dir;
}

function url_here(string $file): string {
  return base_path() . '/' . ltrim($file, '/');
}

/* =========================
   REDIRECT + FLASH
 ========================= */
function redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

function flash_set(string $key, string $msg): void {
  $_SESSION[$key] = $msg;
}

function flash_pop(string $key): string {
  $v = (string)($_SESSION[$key] ?? '');
  unset($_SESSION[$key]);
  return $v;
}

/* =========================
   CSRF
 ========================= */
function csrf_token(): string {
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['_csrf'];
}

function safe_return_to(string $uri): string {
  $u = trim($uri);
  if ($u === '') return url_here('../../../usuarios.php');
  if (str_contains($u, "\n") || str_contains($u, "\r")) return url_here('usuarios.php');
  if (!str_starts_with($u, '/')) return url_here('../../../usuarios.php');
  return $u;
}

function csrf_validate_or_die(): void {
  $t = (string)($_POST['_csrf'] ?? '');
  $sess = (string)($_SESSION['_csrf'] ?? '');
  if ($t === '' || $sess === '' || !hash_equals($sess, $t)) {
    flash_set('flash_err', 'CSRF inválido. Atualize a página e tente novamente.');
    $ret = safe_return_to(post_str('return_to', url_here('usuarios.php')));
    redirect($ret);
  }
}
?>
