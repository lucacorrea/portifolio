<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

/* ========= Helpers ========= */
function redirect_with(int $ok = 0, string $err = ''): never {
    $loc = '../cadastrarBeneficio.php';
    $qs  = $ok ? '?ok=1' : ('?err=' . rawurlencode($err));
    header('Location: ' . $loc . $qs);
    exit;
}

function normalize_money(?string $v): ?float {
    $v = trim((string)$v);
    if ($v === '') return null;
    // aceita "1.234,56" ou "1234.56"
    $v = str_replace([' ', 'R$', 'r$', 'R$ ', 'r$ '], '', $v);
    $v = str_replace(['.'], '', $v);   // remove separador de milhar
    $v = str_replace([','], '.', $v);  // troca vírgula por ponto
    if (!is_numeric($v)) return null;
    $n = (float)$v;
    return $n < 0 ? 0.0 : $n;
}

/* ========= Conexão ========= */
// ajuste caminho conforme sua estrutura
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../dist/assets/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) redirect_with(0, 'Conexão indisponível.');

/* ========= Somente POST ========= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_with(0, 'Método inválido.');
}

/* ========= Coleta ========= */
$nome          = trim((string)($_POST['nome'] ?? ''));
$categoria     = trim((string)($_POST['categoria'] ?? ''));
$descricao     = trim((string)($_POST['descricao'] ?? ''));
$valor_padrao  = normalize_money($_POST['valor_padrao'] ?? null);
$periodicidade = trim((string)($_POST['periodicidade'] ?? 'Única'));
$qtd_padrao    = (int)($_POST['qtd_padrao'] ?? 1);
$doc_exigido   = trim((string)($_POST['doc_exigido'] ?? ''));
$status        = trim((string)($_POST['status'] ?? 'Ativa'));

/* ========= Validação ========= */
if ($nome === '') redirect_with(0, 'Informe o nome do benefício.');
$allowPeriod = ['Única','Mensal','Trimestral','Eventual'];
if (!in_array($periodicidade, $allowPeriod, true)) $periodicidade = 'Única';
$allowStatus = ['Ativa','Inativa'];
if (!in_array($status, $allowStatus, true)) $status = 'Ativa';
if ($qtd_padrao < 1) $qtd_padrao = 1;

/* ========= Garante tabela (safe) =========
   Você pode remover este bloco se já rodou a migration.
*/
$pdo->exec("
CREATE TABLE IF NOT EXISTS ajudas_tipos (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(120) NOT NULL,
  categoria      VARCHAR(60)  NULL,
  descricao      TEXT         NULL,
  valor_padrao   DECIMAL(10,2) NULL,
  periodicidade  ENUM('Única','Mensal','Trimestral','Eventual') DEFAULT 'Única',
  qtd_padrao     INT UNSIGNED DEFAULT 1,
  doc_exigido    VARCHAR(120) NULL,
  status         ENUM('Ativa','Inativa') DEFAULT 'Ativa',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_ajudas_nome (nome),
  INDEX ix_ajudas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ========= Checa duplicidade (nome) ========= */
$st = $pdo->prepare("SELECT id FROM ajudas_tipos WHERE nome = :n LIMIT 1");
$st->execute([':n' => $nome]);
if ($st->fetchColumn()) {
    redirect_with(0, 'Já existe um benefício com esse nome.');
}

/* ========= Insert ========= */
$sql = "INSERT INTO ajudas_tipos
        (nome, categoria, descricao, valor_padrao, periodicidade, qtd_padrao, doc_exigido, status)
        VALUES
        (:nome, :categoria, :descricao, :valor_padrao, :periodicidade, :qtd_padrao, :doc_exigido, :status)";
$stm = $pdo->prepare($sql);
$ok = $stm->execute([
    ':nome'          => $nome,
    ':categoria'     => ($categoria !== '' ? $categoria : null),
    ':descricao'     => ($descricao !== '' ? $descricao : null),
    ':valor_padrao'  => $valor_padrao,
    ':periodicidade' => $periodicidade,
    ':qtd_padrao'    => $qtd_padrao,
    ':doc_exigido'   => ($doc_exigido !== '' ? $doc_exigido : null),
    ':status'        => $status,
]);

if (!$ok) redirect_with(0, 'Falha ao salvar.');

/* ========= Sucesso ========= */
redirect_with(1, '');
