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

/**
 * Valida CSRF em POST normal (form-data).
 * Para requisições JSON (fetch), use csrf_validate_token($token) no seu endpoint.
 */
function csrf_validate_or_redirect(string $redirectPath): void {
  $posted = (string)($_POST['csrf_token'] ?? '');
  $sess   = (string)($_SESSION['csrf_token'] ?? '');
  if (!$posted || !$sess || !hash_equals($sess, $posted)) {
    flash_set('danger', 'CSRF inválido. Recarregue a página.');
    redirect_to($redirectPath);
  }
}

/** Valida CSRF recebendo token (útil pra JSON/fetch) */
function csrf_validate_token(string $postedToken): bool {
  $posted = (string)$postedToken;
  $sess   = (string)($_SESSION['csrf_token'] ?? '');
  return ($posted !== '' && $sess !== '' && hash_equals($sess, $posted));
}

/** POST only */
function require_post_or_redirect(string $redirectPath): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('danger', 'Requisição inválida.');
    redirect_to($redirectPath);
  }
}