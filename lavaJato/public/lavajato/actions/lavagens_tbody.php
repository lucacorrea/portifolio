<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa','estoque']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Conexão indisponível'); }

require_once __DIR__ . '/../controllers/listaLavagens.php';

$q = trim((string)($_GET['q'] ?? ''));
$p = max(1, (int)($_GET['p'] ?? 1));
$pp = min(100, max(10, (int)($_GET['pp'] ?? 10)));

$vm = lavagens_abertas($pdo, ['q'=>$q,'page'=>$p,'per_page'=>$pp]);
$rows = $vm['dados'] ?? [];

/* função render_tbody pode ser copiada pra cá ou movida pra um helper */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function render_tbody(array $rows): string {
  if (!$rows) return '<tr><td colspan="9" class="text-center text-muted py-4">Nenhuma lavagem encontrada.</td></tr>';
  $html = '';
  foreach ($rows as $l) {
    $id = (int)($l['id'] ?? 0);
    $placa = h((string)($l['placa'] ?? '-'));
    $modelo = h((string)($l['modelo'] ?? '-'));
    $cor = h((string)($l['cor'] ?? '-'));
    $cat = h((string)($l['categoria_nome'] ?? '-'));
    $lavador = h((string)($l['lavador_nome'] ?? ($l['lavador_cpf'] ?? '-')));
    $valor = number_format((float)($l['valor'] ?? 0), 2, ',', '.');
    $status = strtolower((string)($l['status'] ?? ''));
    $badge = $status==='aberta'?'warning':($status==='lavando'?'info':($status==='concluida'?'success':'secondary'));
    $label = h(ucfirst($status ?: '-'));

    $html .= "
      <tr>
        <td class='text-muted small'>#{$id}</td>
        <td class='fw-semibold'>{$placa}</td>
        <td>{$modelo}</td>
        <td>{$cor}</td>
        <td>{$cat}</td>
        <td>{$lavador}</td>
        <td class='text-end'>R$ {$valor}</td>
        <td class='text-center'><span class='badge bg-{$badge}'>{$label}</span></td>
        <td class='text-end'>...</td>
      </tr>
    ";
  }
  return $html;
}

header('Content-Type: text/html; charset=utf-8');
echo render_tbody($rows);
