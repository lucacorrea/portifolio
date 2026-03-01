<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/** Escape */
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Flash */
function flash_set(string $type, string $msg): void {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function flash_pop(): ?array {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

/** Redirect */
function redirect_to(string $path): void {
  header('Location: ' . $path);
  exit;
}

/** CSRF */
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_token'];
}
function csrf_validate_token(string $postedToken): bool {
  $posted = (string)$postedToken;
  $sess   = (string)($_SESSION['csrf_token'] ?? '');
  return ($posted !== '' && $sess !== '' && hash_equals($sess, $posted));
}

/** Helpers JSON */
function json_input(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '', true);
  return is_array($data) ? $data : [];
}

function to_int($v, int $default = 0): int {
  if ($v === null) return $default;
  if (is_numeric($v)) return (int)$v;
  return $default;
}

function to_float($v, float $default = 0.0): float {
  if ($v === null) return $default;
  if (is_numeric($v)) return (float)$v;
  $s = (string)$v;
  $s = preg_replace('/[^\d,.\-]/', '', $s ?? '');
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  return is_numeric($s) ? (float)$s : $default;
}

?>