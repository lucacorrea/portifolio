<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../cadastrarBeneficio.php?err=' . rawurlencode('Sem conexão'));
  exit;
}

function redirect_back($id, $ok=0, $err=''): never {
  $to = '../cadastrarBeneficio.php?id='.(int)$id;
  $qs = $ok ? '&ok=1' : ('&err='.rawurlencode($err));
  header('Location: '.$to.$qs);
  exit;
}

function normalize_money(?string $v): ?float {
  $v = trim((string)$v);
  if ($v==='') return null;
  $v = str_replace([' ', 'R$', 'r$', 'R$ ', 'r$ '], '', $v);
  $v = str_replace('.', '', $v); // milhar
  $v = str_replace(',', '.', $v); // decimal
  if (!is_numeric($v)) return null;
  $n = (float)$v;
  return $n < 0 ? 0.0 : $n;
}

/* Somente POST */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ../beneficiosCadastrados.php');
  exit;
}

/* Coleta */
$id            = (int)($_POST['id'] ?? 0);
$nome          = trim((string)($_POST['nome'] ?? ''));
$categoria     = trim((string)($_POST['categoria'] ?? ''));
$descricao     = trim((string)($_POST['descricao'] ?? ''));
$valor_padrao  = normalize_money($_POST['valor_padrao'] ?? null);
$periodicidade = trim((string)($_POST['periodicidade'] ?? 'Única'));
$qtd_padrao    = (int)($_POST['qtd_padrao'] ?? 1);
$doc_exigido   = trim((string)($_POST['doc_exigido'] ?? ''));
$status        = trim((string)($_POST['status'] ?? 'Ativa'));

/* Validação básica */
if ($id <= 0) redirect_back($id, 0, 'ID inválido.');
if ($nome === '') redirect_back($id, 0, 'Informe o nome.');
$allowPer = ['Única','Mensal','Trimestral','Eventual'];
if (!in_array($periodicidade, $allowPer, true)) $periodicidade = 'Única';
$allowSt = ['Ativa','Inativa'];
if (!in_array($status, $allowSt, true)) $status = 'Ativa';
if ($qtd_padrao < 1) $qtd_padrao = 1;

/* Existe? */
$st = $pdo->prepare("SELECT id FROM ajudas_tipos WHERE id=:id");
$st->execute([':id'=>$id]);
if (!$st->fetchColumn()) redirect_back($id, 0, 'Benefício não encontrado.');

/* Duplicidade de nome (excluindo o próprio id) */
$st = $pdo->prepare("SELECT id FROM ajudas_tipos WHERE nome=:n AND id<>:id LIMIT 1");
$st->execute([':n'=>$nome, ':id'=>$id]);
if ($st->fetchColumn()) redirect_back($id, 0, 'Já existe outro benefício com esse nome.');

/* Update */
$sql = "UPDATE ajudas_tipos
        SET nome=:nome, categoria=:categoria, descricao=:descricao,
            valor_padrao=:valor_padrao, periodicidade=:periodicidade,
            qtd_padrao=:qtd_padrao, doc_exigido=:doc_exigido, status=:status
        WHERE id=:id";
$ok = $pdo->prepare($sql)->execute([
  ':nome' => $nome,
  ':categoria' => ($categoria!==''? $categoria : null),
  ':descricao' => ($descricao!==''? $descricao : null),
  ':valor_padrao' => $valor_padrao,
  ':periodicidade' => $periodicidade,
  ':qtd_padrao' => $qtd_padrao,
  ':doc_exigido' => ($doc_exigido!==''? $doc_exigido : null),
  ':status' => $status,
  ':id' => $id
]);

if (!$ok) redirect_back($id, 0, 'Falha ao atualizar.');
redirect_back($id, 1, '');
