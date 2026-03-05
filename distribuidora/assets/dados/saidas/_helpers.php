<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$global = __DIR__ . '/../_helpers.php';
if (is_file($global)) {
  require_once $global;
} else {
  // fallback mínimo (se o global não existir)
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
  function redirect_to(string $url): void { header("Location: {$url}"); exit; }

  function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
  }
  function flash_pop(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
  }

  function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
  function require_post_or_redirect(string $backUrl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      flash_set('warning', 'Ação inválida.');
      redirect_to($backUrl);
    }
  }

  function brl_to_float(string $v): float {
    $s = trim($v);
    $s = str_replace(['R$', ' '], '', $s);
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return (float)$s;
  }
}

?>