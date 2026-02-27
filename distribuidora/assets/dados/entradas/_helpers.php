<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/**
 * Escape HTML
 */
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect_to(string $path): void {
  header('Location: ' . $path);
  exit;
}

/**
 * Force POST or redirect
 */
function require_post_or_redirect(string $redirectTo): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_to($redirectTo);
  }
}

/**
 * CSRF token
 */
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_token'];
}

function csrf_is_valid(?string $token): bool {
  $sess = $_SESSION['csrf_token'] ?? '';
  if (!is_string($sess) || $sess === '') return false;
  if (!is_string($token) || $token === '') return false;
  return hash_equals($sess, $token);
}

function csrf_validate_or_redirect(string $redirectTo): void {
  $token = $_POST['csrf_token'] ?? '';
  if (!csrf_is_valid(is_string($token) ? $token : null)) {
    flash_set('danger', 'Sessão expirada (CSRF). Recarregue a página e tente novamente.');
    redirect_to($redirectTo);
  }
}

/**
 * Flash messages
 * type: success | danger | warning | info
 */
function flash_set(string $type, string $msg): void {
  $type = trim(strtolower($type));
  $allowed = ['success','danger','warning','info','primary','secondary','light','dark'];
  if (!in_array($type, $allowed, true)) $type = 'info';

  $_SESSION['flash'] = [
    'type' => $type,
    'msg'  => $msg,
  ];
}

function flash_pop(): ?array {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  if (!is_array($f) || !isset($f['type'], $f['msg'])) return null;
  return $f;
}

?>