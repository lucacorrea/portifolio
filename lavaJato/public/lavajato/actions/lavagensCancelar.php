<?php
// autoErp/public/lavajato/actions/lavagensCancelar.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

require_post();
guard_empresa_user(['dono', 'administrativo', 'caixa']);

/* ========= Conexão ========= */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;

if (!($pdo instanceof PDO)) {
  header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('Conexão indisponível.'));
  exit;
}

/* ========= CSRF ========= */
$csrfSess = (string)($_SESSION['csrf_lavagens'] ?? '');
$csrfPost = (string)($_POST['csrf'] ?? '');
if ($csrfSess === '' || $csrfPost === '' || !hash_equals($csrfSess, $csrfPost)) {
  header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('CSRF inválido.'));
  exit;
}

/* ========= Input ========= */
$id = (int)($_POST['id'] ?? 0);
$motivo = trim((string)($_POST['motivo'] ?? ''));

if ($id <= 0) {
  header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('ID inválido.'));
  exit;
}

/* ========= Empresa ========= */
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('Empresa não identificada.'));
  exit;
}

$cpfUser = (string)($_SESSION['user_cpf'] ?? $_SESSION['usuario_cpf'] ?? '');

try {
  // Confere se existe, se é da empresa e se pode cancelar (aberta/lavando)
  $st = $pdo->prepare("SELECT id, status FROM lavagens_peca WHERE id = :id AND empresa_cnpj = :cnpj LIMIT 1");
  $st->execute([':id' => $id, ':cnpj' => $empresaCnpj]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('Lavagem não encontrada.'));
    exit;
  }

  $status = strtolower((string)($row['status'] ?? ''));
  if (!in_array($status, ['aberta', 'lavando'], true)) {
    header('Location: ../pages/lavagens.php?err=1&msg=' . urlencode('Só é possível cancelar lavagens em aberta/lavando.'));
    exit;
  }

  // ✅ Cancela (ela some da lista pq o controller só traz aberta/lavando)
  $up = $pdo->prepare("
    UPDATE lavagens_peca
       SET status = 'cancelada'
     WHERE id = :id
       AND empresa_cnpj = :cnpj
     LIMIT 1
  ");
  $up->execute([':id' => $id, ':cnpj' => $empresaCnpj]);

  // Se você quiser guardar motivo/quem cancelou, precisa ter colunas.
  // (Opcional) tenta salvar em 'observacoes' se existir.
  if ($motivo !== '') {
    try {
      // Isso só funciona se sua tabela tiver a coluna 'observacoes'
      $upObs = $pdo->prepare("
        UPDATE lavagens_peca
           SET observacoes = CONCAT(COALESCE(observacoes,''), IF(COALESCE(observacoes,'')='', '', '\n'),
                                    '[CANCELADA ', DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), '] ',
                                    :motivo,
                                    IF(:cpf = '', '', CONCAT(' (por ', :cpf, ')')))
         WHERE id = :id AND empresa_cnpj = :cnpj
         LIMIT 1
      ");
      $upObs->execute([
        ':motivo' => $motivo,
        ':cpf' => $cpfUser,
        ':id' => $id,
        ':cnpj' => $empresaCnpj
      ]);
    } catch (\Throwable $e) {
      // se não tiver coluna observacoes, ignora
    }
  }

  header('Location: ../pages/lavagensLista.php?ok=1&msg=' . urlencode('Lavagem cancelada.'));
  exit;

} catch (Throwable $e) {
  header('Location: ../pages/lavagensLista.php?err=1&msg=' . urlencode('Erro ao cancelar: ' . $e->getMessage()));
  exit;
}
