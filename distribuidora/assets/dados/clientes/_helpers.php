<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   HELPERS BÁSICOS
========================= */
if (!function_exists('e')) {
  function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

function json_out(array $payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

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

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/* =========================
   CSRF
========================= */
function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['_csrf'];
}

function csrf_validate(string $token): bool {
  $sess = (string)($_SESSION['_csrf'] ?? '');
  return $sess !== '' && hash_equals($sess, $token);
}

function csrf_validate_or_die(?string $token = null): void {
  $t = $token ?? (string)($_POST['_csrf'] ?? '');
  if ($t === '' || !csrf_validate($t)) {
    json_out(['ok' => false, 'msg' => 'CSRF inválido. Atualize a página e tente novamente.'], 419);
  }
}

/* =========================
   FORMATAÇÕES / VALIDADORES
========================= */
function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s) ?? '';
}

function cpf_is_valid(string $cpfDigits): bool {
  $cpf = only_digits($cpfDigits);
  if (strlen($cpf) !== 11) return false;
  if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

  // dígito 1
  $sum = 0;
  for ($i = 0, $w = 10; $i < 9; $i++, $w--) $sum += ((int)$cpf[$i]) * $w;
  $d1 = 11 - ($sum % 11);
  $d1 = ($d1 >= 10) ? 0 : $d1;
  if ($d1 !== (int)$cpf[9]) return false;

  // dígito 2
  $sum = 0;
  for ($i = 0, $w = 11; $i < 10; $i++, $w--) $sum += ((int)$cpf[$i]) * $w;
  $d2 = 11 - ($sum % 11);
  $d2 = ($d2 >= 10) ? 0 : $d2;
  return $d2 === (int)$cpf[10];
}

function tel_min_ok(string $telDigits): bool {
  $t = only_digits($telDigits);
  return strlen($t) >= 8;
}

function cpf_fmt(string $cpfDigits): string {
  $d = only_digits($cpfDigits);
  $d = substr($d, 0, 11);
  if (strlen($d) < 11) return $d;
  return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d) ?? $d;
}

function tel_fmt(string $telDigits): string {
  $d = only_digits($telDigits);
  $d = substr($d, 0, 11);
  if (strlen($d) <= 2) return $d ? "($d" : "";
  $dd = substr($d, 0, 2);
  $rest = substr($d, 2);

  if (strlen($rest) <= 4) return "($dd) $rest";
  if (strlen($rest) <= 8) return "($dd) " . substr($rest, 0, 4) . "-" . substr($rest, 4);
  return "($dd) " . substr($rest, 0, 5) . "-" . substr($rest, 5);
}

function now_sql(): string {
  return date('Y-m-d H:i:s');
}

/* =========================
   DB CHECK
========================= */
function require_db_or_die(): void {
  if (!function_exists('db')) {
    http_response_code(500);
    echo "ERRO: função db():PDO não encontrada. Verifique assets/conexao.php";
    exit;
  }
}
?>