<?php

declare(strict_types=1);

/* ====== Auth ====== */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ====== DB ====== */
require_once __DIR__ . '/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo "<script>alert('Erro de conexão com o banco.');location.href='./index.php';</script>";
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ====== Helpers (PHP) ====== */
function h($v): string
{
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
  return preg_replace('/\D+/', '', (string)$s);
}
function fmt_money($n): string
{
  return ($n === null || $n === '') ? '—' : 'R$ ' . number_format((float)$n, 2, ',', '.');
}
function fmt_phone(?string $f): string
{
  $d = only_digits($f);
  if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
  if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
  return $f ?: '—';
}
function fmt_cpf(?string $cpf): string
{
  $d = only_digits($cpf);
  if (strlen($d) !== 11) return $cpf ? $cpf : '—';
  return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}
function fmt_date_br(?string $ymd): string
{
  if (!$ymd || $ymd === '0000-00-00') return '—';
  $p = explode('-', $ymd);
  return (count($p) === 3) ? ($p[2] . '/' . $p[1] . '/' . $p[0]) : '—';
}
function safe_int($v): ?int
{
  return ($v !== null && $v !== '') ? (int)$v : null;
}
function mb_lower(string $s): string
{
  if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
  return strtolower($s);
}
function table_has_column(PDO $pdo, string $table, string $column): bool
{
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table_name
       AND COLUMN_NAME = :column_name
  ");
  $stmt->execute([
    ':table_name' => $table,
    ':column_name' => $column,
  ]);

  return (int)$stmt->fetchColumn() > 0;
}

function table_has_index(PDO $pdo, string $table, string $index): bool
{
  $stmt = $pdo->prepare("\n    SELECT COUNT(*)\n      FROM INFORMATION_SCHEMA.STATISTICS\n     WHERE TABLE_SCHEMA = DATABASE()\n       AND TABLE_NAME = :table_name\n       AND INDEX_NAME = :index_name\n  ");
  $stmt->execute([
    ':table_name' => $table,
    ':index_name' => $index,
  ]);

  return (int)$stmt->fetchColumn() > 0;
}

function ensure_solicitacao_photo_schema(PDO $pdo): void
{
  $pdo->exec("\n    CREATE TABLE IF NOT EXISTS solicitante_documentos (\n      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n      solicitante_id INT UNSIGNED NOT NULL,\n      solicitacao_id INT NULL,\n      arquivo_path VARCHAR(255) NOT NULL,\n      original_name VARCHAR(255) NOT NULL,\n      mime_type VARCHAR(120) NULL,\n      size_bytes BIGINT NULL,\n      created_at DATETIME NULL,\n      INDEX idx_docs_solicitante (solicitante_id),\n      INDEX idx_docs_solicitacao (solicitacao_id),\n      INDEX idx_docs_created (created_at)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n  ");

  if (!table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')) {
    $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN solicitacao_id INT NULL AFTER solicitante_id");
  }

  if (!table_has_index($pdo, 'solicitante_documentos', 'idx_docs_solicitacao')) {
    $pdo->exec("CREATE INDEX idx_docs_solicitacao ON solicitante_documentos (solicitacao_id)");
  }

  if (!table_has_column($pdo, 'solicitante_documentos', 'size_bytes')) {
    $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN size_bytes BIGINT NULL AFTER mime_type");
  }
}

function is_image_document(array $document): bool
{
  $mime = strtolower(trim((string)($document['mime_type'] ?? '')));
  if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
    return true;
  }

  return (bool)preg_match('/\.(jpe?g|png|webp)$/i', (string)($document['arquivo_path'] ?? ''));
}

function attach_solicitacao_photos(array $solicitacoes, array $documentos): array
{
  foreach ($solicitacoes as &$solicitacao) {
    $solicitacao['foto_solicitacao'] = null;
    $solicitacaoId = (int)($solicitacao['id'] ?? 0);
    if ($solicitacaoId <= 0) continue;

    $dataSolicitacao = trim((string)($solicitacao['data_solicitacao'] ?? ''));
    foreach ($documentos as $documento) {
      if (!is_image_document($documento)) continue;

      $documentSolicitacaoId = (int)($documento['solicitacao_id'] ?? 0);
      $arquivo = str_replace('\\', '/', (string)($documento['arquivo_path'] ?? ''));
      $original = (string)($documento['original_name'] ?? '');
      $createdAt = trim((string)($documento['created_at'] ?? ''));

      $matches = $documentSolicitacaoId === $solicitacaoId
        || strpos(basename($arquivo), 'solicitacao_' . $solicitacaoId . '_foto_') === 0
        || stripos($original, 'Foto da solicitação #' . $solicitacaoId . '.') === 0
        || ($dataSolicitacao !== '' && $createdAt === $dataSolicitacao);

      if ($matches && $arquivo !== '' && strpos($arquivo, '..') === false) {
        $solicitacao['foto_solicitacao'] = ltrim($arquivo, '/');
        break;
      }
    }
  }
  unset($solicitacao);

  return $solicitacoes;
}

function auto_link_solicitacao_photos(PDO $pdo): void
{
  ensure_solicitacao_photo_schema($pdo);

  $docsStmt = $pdo->query("\n    SELECT id, solicitante_id, arquivo_path, original_name, mime_type, created_at\n      FROM solicitante_documentos\n     WHERE solicitacao_id IS NULL\n     ORDER BY id ASC\n     LIMIT 500\n  ");

  $solById = $pdo->prepare("\n    SELECT id\n      FROM solicitacoes\n     WHERE id = :id\n       AND solicitante_id = :solicitante_id\n     LIMIT 1\n  ");

  $solByDate = $pdo->prepare("\n    SELECT id\n      FROM solicitacoes\n     WHERE solicitante_id = :solicitante_id\n       AND data_solicitacao = :created_at\n     ORDER BY id ASC\n  ");

  $updateDoc = $pdo->prepare("\n    UPDATE solicitante_documentos\n       SET solicitacao_id = :solicitacao_id\n     WHERE id = :id\n     LIMIT 1\n  ");

  while ($doc = $docsStmt->fetch(PDO::FETCH_ASSOC)) {
    if (!is_image_document($doc)) continue;

    $candidateId = 0;
    $text = (string)($doc['arquivo_path'] ?? '') . ' ' . (string)($doc['original_name'] ?? '');
    if (preg_match('/solicitacao[_ ]#?(\d+)/i', $text, $match)) {
      $candidateId = (int)$match[1];
    } elseif (preg_match('/Foto da solicita(?:ç|c)(?:ã|a)o #(\d+)/iu', $text, $match)) {
      $candidateId = (int)$match[1];
    }

    $solicitacaoId = 0;
    if ($candidateId > 0) {
      $solById->execute([
        ':id' => $candidateId,
        ':solicitante_id' => (int)$doc['solicitante_id'],
      ]);
      $solicitacaoId = (int)($solById->fetchColumn() ?: 0);
    }

    if ($solicitacaoId <= 0 && !empty($doc['created_at'])) {
      $solByDate->execute([
        ':solicitante_id' => (int)$doc['solicitante_id'],
        ':created_at' => (string)$doc['created_at'],
      ]);
      $matches = $solByDate->fetchAll(PDO::FETCH_COLUMN);
      if (count($matches) === 1) {
        $solicitacaoId = (int)$matches[0];
      }
    }

    if ($solicitacaoId > 0) {
      $updateDoc->execute([
        ':solicitacao_id' => $solicitacaoId,
        ':id' => (int)$doc['id'],
      ]);
    }
  }
}

function ensure_solicitacoes_table(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS solicitacoes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      solicitante_id INT UNSIGNED NOT NULL,
      ajuda_tipo_id INT NULL,
      resumo_caso TEXT,
      data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
      status VARCHAR(20) DEFAULT 'Aberto',
      created_by VARCHAR(100),
      origem VARCHAR(20) NULL,
      INDEX (solicitante_id),
      INDEX (data_solicitacao),
      INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
}

function ensure_solicitacoes_origem(PDO $pdo): void
{
  if (table_has_column($pdo, 'solicitacoes', 'origem')) return;

  try {
    $pdo->exec("ALTER TABLE solicitacoes ADD COLUMN origem VARCHAR(20) NULL AFTER created_by");
  } catch (Throwable $e) {
    if (!table_has_column($pdo, 'solicitacoes', 'origem')) {
      throw $e;
    }
  }
}

function ensure_cadastro_solicitacoes(PDO $pdo, int $sid, string $cpf): void
{
  ensure_solicitacoes_table($pdo);
  ensure_solicitacoes_origem($pdo);

  $hasAjuda = table_has_column($pdo, 'solicitantes', 'ajuda_tipo_id');
  $hasResumo = table_has_column($pdo, 'solicitantes', 'resumo_caso');
  if (!$hasAjuda && !$hasResumo) return;

  $hasCreated = table_has_column($pdo, 'solicitantes', 'created_at');
  $hasResp = table_has_column($pdo, 'solicitantes', 'responsavel');

  $where = [];
  $params = [];
  if ($sid > 0) {
    $where[] = 'id = :sid_lookup';
    $params[':sid_lookup'] = $sid;
  }
  if (strlen($cpf) === 11) {
    $where[] = 'cpf = :cpf_lookup';
    $params[':cpf_lookup'] = $cpf;
  }
  if (!$where) return;

  $fields = ['id'];
  $fields[] = $hasAjuda ? 'ajuda_tipo_id' : 'NULL AS ajuda_tipo_id';
  $fields[] = $hasResumo ? 'resumo_caso' : 'NULL AS resumo_caso';
  $fields[] = $hasCreated ? 'created_at' : 'NULL AS created_at';
  $fields[] = $hasResp ? 'responsavel' : 'NULL AS responsavel';

  $stmt = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM solicitantes WHERE " . implode(' OR ', $where));
  $stmt->execute($params);

  $findCadastro = $pdo->prepare("
    SELECT id
    FROM solicitacoes
    WHERE solicitante_id = :sid
      AND origem = 'cadastro'
    ORDER BY id ASC
    LIMIT 1
  ");
  $findLegacyExact = $pdo->prepare("
    SELECT id
    FROM solicitacoes
    WHERE solicitante_id = :sid
      AND COALESCE(ajuda_tipo_id, 0) = COALESCE(:aid, 0)
      AND COALESCE(TRIM(resumo_caso), '') = COALESCE(:resumo, '')
      AND DATE(COALESCE(data_solicitacao, '1000-01-01')) = DATE(COALESCE(:data_solicitacao, '1000-01-01'))
    ORDER BY id ASC
    LIMIT 1
  ");
  $findLegacyDate = $pdo->prepare("
    SELECT id
    FROM solicitacoes
    WHERE solicitante_id = :sid
      AND data_solicitacao = :data_solicitacao
    ORDER BY id ASC
    LIMIT 1
  ");
  $markCadastro = $pdo->prepare("
    UPDATE solicitacoes
       SET origem = 'cadastro'
     WHERE id = :id
     LIMIT 1
  ");
  $markDuplicadas = $pdo->prepare("
    UPDATE solicitacoes
       SET origem = 'cadastro_duplicada'
     WHERE solicitante_id = :sid
       AND id <> :cadastro_id
       AND data_solicitacao = :data_solicitacao
       AND COALESCE(origem, '') <> 'cadastro'
  ");
  $insert = $pdo->prepare("
    INSERT INTO solicitacoes
      (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, origem)
    VALUES
      (:sid, :aid, :resumo, :data_solicitacao, 'Aberto', :created_by, 'cadastro')
  ");

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rowSid = (int)($row['id'] ?? 0);
    $aid = ($row['ajuda_tipo_id'] ?? null) !== null ? (int)$row['ajuda_tipo_id'] : null;
    $resumo = trim((string)($row['resumo_caso'] ?? ''));
    $resumo = $resumo !== '' ? $resumo : null;
    if ($rowSid <= 0 || ($aid === null && $resumo === null)) continue;

    $dataSolicitacao = (string)($row['created_at'] ?? '');
    if ($dataSolicitacao === '' || $dataSolicitacao === '0000-00-00 00:00:00') {
      $dataSolicitacao = date('Y-m-d H:i:s');
    }

    $findCadastro->execute([':sid' => $rowSid]);
    $cadastroId = (int)($findCadastro->fetchColumn() ?: 0);
    if ($cadastroId > 0) {
      $markDuplicadas->execute([
        ':sid' => $rowSid,
        ':cadastro_id' => $cadastroId,
        ':data_solicitacao' => $dataSolicitacao,
      ]);
      continue;
    }

    $findLegacyDate->execute([
      ':sid' => $rowSid,
      ':data_solicitacao' => $dataSolicitacao,
    ]);
    $legacyId = (int)($findLegacyDate->fetchColumn() ?: 0);
    if ($legacyId > 0) {
      $markCadastro->execute([':id' => $legacyId]);
      $markDuplicadas->execute([
        ':sid' => $rowSid,
        ':cadastro_id' => $legacyId,
        ':data_solicitacao' => $dataSolicitacao,
      ]);
      continue;
    }

    $findLegacyExact->execute([
      ':sid' => $rowSid,
      ':aid' => $aid,
      ':resumo' => $resumo,
      ':data_solicitacao' => $dataSolicitacao,
    ]);
    $legacyId = (int)($findLegacyExact->fetchColumn() ?: 0);
    if ($legacyId > 0) {
      $markCadastro->execute([':id' => $legacyId]);
      $markDuplicadas->execute([
        ':sid' => $rowSid,
        ':cadastro_id' => $legacyId,
        ':data_solicitacao' => $dataSolicitacao,
      ]);
      continue;
    }

    $insert->execute([
      ':sid' => $rowSid,
      ':aid' => $aid,
      ':resumo' => $resumo,
      ':data_solicitacao' => $dataSolicitacao,
      ':created_by' => ($row['responsavel'] ?? null) ?: null,
    ]);
  }
}

/* ============================================================
   Detecta qual coluna em "solicitantes" guarda o responsável
   (servidor) do cadastro, para não quebrar caso o nome varie.
   ============================================================ */
$respCol = null;
$respExpr = "NULL"; // fallback
try {
  $cols = $pdo->query("
    SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'solicitantes'
  ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

  $colsMap = array_fill_keys(array_map('strtolower', $cols), true);

  $candidates = [
    'responsavel',
    'responsavel_cadastro',
    'servidor',
    'servidor_cadastro',
    'usuario_responsavel',
    'usuario_cadastro',
    'criado_por',
    'created_by',
    'usuario_nome'
  ];

  foreach ($candidates as $cand) {
    if (isset($colsMap[strtolower($cand)])) {
      $respCol = $cand;
      break;
    }
  }

  if ($respCol && preg_match('/^[a-zA-Z0-9_]+$/', $respCol)) {
    $respExpr = "s.`{$respCol}`";
  } else {
    $respCol = null;
    $respExpr = "NULL";
  }
} catch (Throwable $e) {
  $respCol = null;
  $respExpr = "NULL";
}

/* ====== AJAX: Detalhes por ID (JSON) ====== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  $sql = "SELECT s.*,
                 COALESCE(b.nome,'') AS bairro_nome,
                 {$respExpr} AS responsavel_cadastro,
                 at.nome as ajuda_tipo_nome,
                 at.categoria as ajuda_tipo_categoria
          FROM solicitantes s
          LEFT JOIN bairros b ON b.id = s.bairro_id
          LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
          WHERE s.id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $id]);
  $s = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$s) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
  }

  // Fallback para bases antigas: o cadastro inicial deve existir em solicitacoes.
  // Se a migration ainda nao foi rodada, mantemos a visualizacao sem duplicar.
  $solicitacaoInicial = null;
  ensure_cadastro_solicitacoes($pdo, $id, only_digits((string)($s['cpf'] ?? '')));

  // Buscar histórico de solicitações adicionais
  $solicitacoesAdicionais = [];
  try {
    $cpfDetalhe = only_digits((string)($s['cpf'] ?? ''));
    $whereSolic = ['s.solicitante_id = :sid'];
    $paramsSolic = [':sid' => $id];
    if (strlen($cpfDetalhe) === 11) {
      $whereSolic[] = 'p.cpf = :cpf_solic';
      $paramsSolic[':cpf_solic'] = $cpfDetalhe;
    }

    $hasSolicitacaoEntrega = table_has_column($pdo, 'ajudas_entregas', 'solicitacao_id');
    $entregaFields = $hasSolicitacaoEntrega
      ? "COALESCE(ent.entrega_id, 0) AS entrega_id,
         COALESCE(ent.entregas_count, 0) AS entregas_count,
         ent.data_entrega,
         ent.hora_entrega"
      : "0 AS entrega_id,
         0 AS entregas_count,
         NULL AS data_entrega,
         NULL AS hora_entrega";
    $entregaJoin = $hasSolicitacaoEntrega
      ? "
        LEFT JOIN (
          SELECT
            solicitacao_id,
            COUNT(*) AS entregas_count,
            MAX(id) AS entrega_id,
            MAX(data_entrega) AS data_entrega,
            MAX(hora_entrega) AS hora_entrega
          FROM ajudas_entregas
          WHERE solicitacao_id IS NOT NULL
            AND UPPER(entregue) = 'SIM'
          GROUP BY solicitacao_id
        ) ent ON ent.solicitacao_id = s.id"
      : "";

    $stmtSolic = $pdo->prepare("
      SELECT s.id, s.solicitante_id, p.cpf AS solicitante_cpf,
             s.ajuda_tipo_id, s.resumo_caso, s.data_solicitacao, s.status,
             s.created_by, s.origem, at.nome as ajuda_nome, at.categoria as ajuda_categoria,
             {$entregaFields}
      FROM solicitacoes s
      LEFT JOIN solicitantes p ON p.id = s.solicitante_id
      LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
      {$entregaJoin}
      WHERE (" . implode(' OR ', $whereSolic) . ")
        AND COALESCE(s.origem, '') <> 'cadastro_duplicada'
      ORDER BY s.data_solicitacao DESC, s.id DESC
    ");
    $stmtSolic->execute($paramsSolic);
    $solicitacoesAdicionais = $stmtSolic->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    // Se a tabela não existir ainda, não quebra
    $solicitacoesAdicionais = [];
  }

  $todasSolicitacoes = $solicitacoesAdicionais;
  if (!$todasSolicitacoes && ($s['ajuda_tipo_id'] || $s['resumo_caso'])) {
    $solicitacaoInicial = [
      'id' => 0,
      'ajuda_tipo_id' => $s['ajuda_tipo_id'],
      'ajuda_nome' => $s['ajuda_tipo_nome'],
      'ajuda_categoria' => $s['ajuda_tipo_categoria'],
      'resumo_caso' => $s['resumo_caso'],
      'data_solicitacao' => $s['created_at'],
      'status' => 'Cadastro',
      'origem' => 'cadastro',
      'created_by' => $s['responsavel_cadastro'] ?? null,
      'entrega_id' => 0,
      'entregas_count' => 0,
      'data_entrega' => null,
      'hora_entrega' => null
    ];
    $todasSolicitacoes[] = $solicitacaoInicial;
  }

  $fam = $pdo->prepare("SELECT nome, data_nascimento, parentesco, escolaridade, obs
                        FROM familiares
                        WHERE solicitante_id = :sid
                        ORDER BY id");
  $fam->execute([':sid' => $id]);
  $familiares = $fam->fetchAll(PDO::FETCH_ASSOC);

  /* documentos: tenta com size_bytes, se não existir cai no fallback */
  try {
    auto_link_solicitacao_photos($pdo);
  } catch (Throwable $e) {
    // Nao quebra a consulta de detalhes se o banco negar ALTER/INDEX.
  }

  $documentSolicitacaoField = table_has_column($pdo, 'solicitante_documentos', 'solicitacao_id')
    ? 'solicitacao_id'
    : 'NULL AS solicitacao_id';
  try {
    $doc = $pdo->prepare("SELECT id, {$documentSolicitacaoField}, arquivo_path, original_name, mime_type, size_bytes, created_at
                          FROM solicitante_documentos
                          WHERE solicitante_id = :sid
                          ORDER BY created_at DESC, id DESC");
    $doc->execute([':sid' => $id]);
    $documentos = $doc->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $doc = $pdo->prepare("SELECT id, {$documentSolicitacaoField}, arquivo_path, original_name, mime_type, created_at
                          FROM solicitante_documentos
                          WHERE solicitante_id = :sid
                          ORDER BY created_at DESC, id DESC");
    $doc->execute([':sid' => $id]);
    $documentos = $doc->fetchAll(PDO::FETCH_ASSOC);
  }

  $todasSolicitacoes = attach_solicitacao_photos($todasSolicitacoes, $documentos);

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'solicitante' => $s,
    'solicitacoes' => $todasSolicitacoes,
    'familiares' => $familiares,
    'documentos' => $documentos
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ====== Filtro (Bairro) & Busca (Nome/CPF/Responsável) ====== */
$bairro_id = safe_int($_GET['bairro_id'] ?? null);
$q = trim($_GET['q'] ?? '');
$qDigits = only_digits($q);

$where = [];
$params = [];

if ($bairro_id !== null && $bairro_id > 0) {
  $where[] = 's.bairro_id = :bid';
  $params[':bid'] = $bairro_id;
}

if ($q !== '') {
  $cond = '( s.nome LIKE :q_nome OR s.cpf LIKE :cpf_like OR s.id = :qid';
  if ($respCol) {
    $cond .= " OR {$respExpr} LIKE :q_resp";
  }
  $cond .= ' )';
  $where[] = $cond;

  $params[':q_nome'] = '%' . $q . '%';
  if ($respCol) {
    $params[':q_resp'] = '%' . $q . '%';
  }
  $params[':cpf_like'] = $qDigits !== '' ? '%' . $qDigits . '%' : '___INVALID___';
  $params[':qid'] = $qDigits !== '' ? (int)$qDigits : 0;
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sqlBase = "SELECT s.id, s.nome, s.cpf, s.telefone,
                   {$respExpr} AS responsavel_cadastro,
                   s.pbf, s.bpc,
                   s.beneficio_municipal, s.beneficio_estadual,
                   s.renda_familiar, s.renda_mensal_faixa, s.trabalho,s.local_trabalho,
                   COALESCE(b.nome,'—') AS bairro_nome,
                   s.created_at
            FROM solicitantes s
            LEFT JOIN bairros b ON b.id = s.bairro_id
            $whereSql
            ORDER BY s.created_at DESC, s.id DESC";
$stmt = $pdo->prepare($sqlBase);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Bairros para o filtro */
$bairros = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

/* Ajudas Tipos para Nova Solicitação (Modal) */
$ajudasTipos = [];
try {
  $ajudasTipos = $pdo->query("
    SELECT id, nome
    FROM ajudas_tipos
    WHERE nome IS NOT NULL AND TRIM(nome) <> ''
    ORDER BY nome
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $ajudasTipos = [];
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <title>Pessoas Cadastradas - ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bootstrap.css">
  <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

  <!-- (Opcional) DataTable CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.1.0/dist/style.css">

  <style>
    .table-actions .btn {
      margin: 0 2px;
    }

    .td-endereco {
      max-width: 280px;
    }

    @media (max-width:991.98px) {
      .td-endereco {
        max-width: 180px;
      }
    }

    /* ===== Modal layout ===== */
    .profile-wrap {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
      padding: .25rem 0 .75rem;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      margin-bottom: 1rem;
    }

    .modal-photo {
      width: 110px;
      height: 110px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f8f9fa;
    }

    .profile-info h5.profile-name {
      margin: 0 0 .25rem;
    }

    .profile-subline {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .25rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      font-size: .85rem;
    }

    .kv-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: .6rem .8rem;
    }

    .kv {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: .5rem;
      padding: .6rem .7rem;
    }

    .kv-label {
      font-size: .8rem;
      color: #6c757d;
    }

    .kv-value {
      font-weight: 600;
    }

    .scroll-x {
      overflow-x: auto;
    }

    /* ===== WhatsApp (telefone na modal) ===== */
    .whats-wrap {
      display: flex;
      align-items: center;
      gap: .55rem;
      flex-wrap: wrap;
    }

    .whats-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f1f3f5;
      text-decoration: none;
      color: #25D366;
    }

    .whats-link:hover {
      filter: brightness(.95);
    }

    .whats-link.disabled {
      opacity: .45;
      pointer-events: none;
    }

    /* ===== Documentos (linhas + botão Abrir) ===== */
    .docs-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5rem;
      flex-wrap: wrap;
      margin-bottom: 0;
    }

    #md-docs {
      margin-top: .35rem;
    }

    /* desktop */
    #md-docs .doc-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .5rem .6rem;
      border-radius: .65rem;
      background: #f8f9fa;
      margin: .4rem 0;
    }

    #md-docs .doc-meta {
      display: flex;
      align-items: start;
      gap: .5rem;
    }

    #md-docs .doc-name {
      font-weight: 600;
      word-break: break-word;
    }

    #md-docs .doc-sub {
      font-size: .8rem;
      color: #6c757d;
    }

    .nova-sol-photo .form-control[readonly] {
      background: #fff;
      cursor: default;
    }

    .nova-sol-photo-preview img {
      width: 100%;
      max-height: 180px;
      object-fit: cover;
      border-radius: .5rem;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f8f9fa;
    }

    .edit-solicitacao-toolbar {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
      background: #f8fafc;
    }

    .edit-solicitacao-photo {
      display: flex;
      align-items: center;
      gap: .75rem;
      min-width: 0;
      flex: 1;
    }

    .edit-solicitacao-photo img {
      width: 72px;
      height: 72px;
      object-fit: cover;
      border-radius: .65rem;
      border: 1px solid #dbe2ea;
      background: #fff;
    }

    .edit-solicitacao-photo a {
      font-weight: 700;
      text-decoration: none;
    }

    .modal-camera-nova-sol {
      z-index: 1090;
    }

    .modal-camera-nova-sol .modal-dialog {
      width: calc(100% - 1.5rem);
      max-width: 920px;
      margin-left: auto;
      margin-right: auto;
    }

    .nova-cam-wrap {
      border: 1px solid rgba(0, 0, 0, .12);
      border-radius: .6rem;
      padding: .9rem;
      background: #fff;
    }

    .nova-cam-frame {
      width: 100%;
      aspect-ratio: 16 / 9;
      border-radius: .6rem;
      overflow: hidden;
      background: #000;
      position: relative;
    }

    .nova-cam-frame video,
    .nova-cam-frame img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    #novaCamCanvas {
      display: none;
    }

    .nova-cam-hint {
      margin-top: .65rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .75rem;
      flex-wrap: wrap;
      font-size: .9rem;
      color: #6c757d;
    }

    /* ===== Tabela clean padrão ANEXO ===== */
    .people-table-card {
      background: #fff;
      border: 0;
      border-radius: 14px;
      box-shadow: none;
      overflow: hidden;
    }

    .people-card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      padding: 1.15rem 1.25rem .75rem;
      background: #fff;
      border-bottom: 0;
    }

    .people-card-title {
      margin: 0;
      color: #1f3563;
      font-size: 1.05rem;
      font-weight: 800;
    }

    .people-table-count {
      margin-top: .3rem;
      color: #7a86b6;
      font-size: .95rem;
      font-weight: 500;
    }

    .people-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: .55rem;
      flex-wrap: wrap;
      margin-left: auto;
    }

    .people-bairro-select {
      width: 210px;
      min-height: 38px;
      border: 1px solid #d0d7de;
      border-radius: 4px;
      color: #52677a;
      font-size: 14px;
      box-shadow: none;
    }

    .people-bairro-select:focus {
      border-color: #9ab0f5;
      box-shadow: 0 0 0 0.12rem rgba(67, 94, 190, .12);
    }

    .dt-search-wrap {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
    }

    .dt-search-input {
      width: min(420px, 52vw);
      height: 38px;
      padding: .55rem .9rem;
      border: 1px solid #b8c7f7;
      border-radius: 4px;
      background: #fff;
      color: #495057;
      font-size: 14px;
      box-shadow: none;
      outline: none;
      transition: border-color .2s ease, box-shadow .2s ease;
    }

    .dt-search-input::placeholder {
      color: #7f8a99;
      opacity: 1;
    }

    .dt-search-input:focus {
      border-color: #9ab0f5;
      box-shadow: 0 0 0 0.12rem rgba(67, 94, 190, .12);
    }

    .dt-search-clear {
      width: 38px;
      height: 38px;
      min-width: 38px;
      border: 1px solid #cfd6df;
      border-radius: 4px;
      background: #fff;
      color: #52677a;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: border-color .2s ease, color .2s ease, background .2s ease;
      padding: 0;
    }

    .dt-search-clear:hover {
      border-color: #435ebe;
      color: #435ebe;
      background: #f8f9ff;
    }

    .clean-table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
      color: #506478;
      font-size: 15px;
    }

    .clean-table thead th {
      background: #fff !important;
      color: #2f3f4f;
      border-top: 0;
      border-bottom: 1px solid #d7dde5;
      padding: .9rem .75rem;
      font-weight: 800;
      vertical-align: middle;
      white-space: nowrap;
    }

    .clean-table tbody td {
      border-top: 0;
      border-bottom: 1px solid #e1e6ec;
      padding: .82rem .75rem;
      vertical-align: middle;
      color: #506478;
      background: #fff;
    }

    .clean-table tbody tr:nth-child(even) td {
      background: #f7f8fa;
    }

    .clean-table tbody tr:hover td {
      background: #f3f6fb;
    }

    .clean-table .td-nome {
      max-width: 280px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-weight: 700;
      color: #445a70;
    }

    .clean-table .td-bairro,
    .clean-table .td-responsavel,
    .clean-table .td-profissao {
      max-width: 220px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .clean-table th.sortable {
      position: relative;
      cursor: pointer;
      user-select: none;
      padding-right: 1.65rem !important;
    }

    .clean-table th.sortable .sort-prisma {
      position: absolute;
      right: .55rem;
      top: 50%;
      width: 10px;
      height: 18px;
      transform: translateY(-50%);
      pointer-events: none;
    }

    .clean-table th.sortable .sort-prisma::before,
    .clean-table th.sortable .sort-prisma::after {
      position: absolute;
      left: 0;
      font-size: 10px;
      line-height: 1;
      color: #e1e5ea;
    }

    .clean-table th.sortable .sort-prisma::before {
      content: "▲";
      top: 0;
    }

    .clean-table th.sortable .sort-prisma::after {
      content: "▼";
      bottom: 0;
    }

    .clean-table th.sortable.sort-asc .sort-prisma::before,
    .clean-table th.sortable.sort-desc .sort-prisma::after {
      color: #8d97a3;
    }

    .clean-table th.sortable:hover .sort-prisma::before,
    .clean-table th.sortable:hover .sort-prisma::after {
      color: #b5bcc5;
    }

    .table-actions {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .35rem;
      white-space: nowrap;
    }

    .table-actions .btn {
      margin: 0;
      border-radius: 4px;
      font-weight: 600;
    }

    .tfoot-pager {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #e9ecef;
      flex-wrap: wrap;
    }

    .tfoot-pager .btn {
      min-width: 88px;
      border-radius: 4px;
      font-weight: 600;
    }

    #lblPagina {
      font-size: 1.02rem;
      color: #435ebe;
      font-weight: 800;
      white-space: nowrap;
    }

    #selPerPage {
      min-width: 72px;
      border: 1px solid #d0d7de;
      border-radius: 6px;
      color: #495057;
      font-weight: 600;
      box-shadow: none;
    }

    /* ===== Histórico de Solicitações ===== */
    .solicitacao-card {
      border: 1px solid #dee2e6;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1rem;
      background: #f8f9fa;
    }

    .solicitacao-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .solicitacao-tipo {
      font-weight: 600;
      color: #0d6efd;
    }

    .solicitacao-data {
      font-size: 0.875rem;
      color: #6c757d;
    }

    .solicitacao-status {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .status-cadastro {
      background-color: #6c757d;
      color: white;
    }

    .status-aberto {
      background-color: #d1ecf1;
      color: #0c5460;
    }

    .status-em-andamento {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-concluido {
      background-color: #d4edda;
      color: #155724;
    }

    .status-cancelado {
      background-color: #f8d7da;
      color: #721c24;
    }

    .solicitacao-resumo {
      margin-top: 0.5rem;
      padding-top: 0.5rem;
      border-top: 1px solid #dee2e6;
      font-size: 0.875rem;
    }

    /* ===== Mobile ===== */
    @media (max-width:576px) {
      .profile-wrap {
        justify-content: center;
        text-align: center;
      }

      .modal-photo {
        margin: 0 auto;
      }

      .profile-info {
        flex-basis: 100%;
        text-align: center;
      }

      .profile-subline {
        justify-content: center;
      }

      .docs-head {
        gap: .5rem;
        margin-bottom: 0;
      }

      #md-docs {
        margin-top: 1rem;
      }

      #md-docs .doc-row {
        flex-direction: column;
        align-items: stretch;
      }

      #md-docs .doc-row a.btn {
        width: 100%;
        margin-top: .25rem;
      }

      .solicitacao-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <!-- Sidebar -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
              <a href="dashboard.php"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo" style="height:48px"></a>
            </div>
            <div class="toggler">
              <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
            </div>
          </div>
        </div>

        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-item">
              <a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
            </li>

            <li class="sidebar-item has-sub active">
              <a href="#" class="sidebar-link"><i class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
              <ul class="submenu active">
                <li class="submenu-item active"><a href="#">Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
              </ul>
            </li>

            <?php
            $role = $_SESSION['user_role'] ?? '';

            if ($role === 'prefeito' || $role === 'secretario'):
            ?>
              <li class="sidebar-item has-sub">
                <a href="#" class="sidebar-link">
                  <i class="bi bi-person-fill"></i>
                  <span>Usuários</span>
                </a>
                <ul class="submenu">
                  <li class="submenu-item">
                    <a href="usuariosPermitidos.php">Permitidos</a>
                  </li>
                  <li class="submenu-item">
                    <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                  </li>
                </ul>
              </li>
            <?php endif; ?>

            <li class="sidebar-item">
              <a href="../../gpsemas/index.php" class="sidebar-link"><i class="bi bi-map-fill"></i><span>Rastreamento</span></a>
            </li>
            <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'secretario'): ?>
              <li class="sidebar-item">
                <a href="../admin/index.php" class="sidebar-link" target="_blank" rel="noopener">
                  <i class="bi bi-shield-lock-fill"></i>
                  <span>Administrador</span>
                </a>
              </li>
            <?php endif; ?>


            <li class="sidebar-item">
              <a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
            </li>
          </ul>
        </div>

      </div>
    </div>

    <!-- Main -->
    <div id="main">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row">
            <div class="col-12 col-md-6">
              <h3>Pessoas Cadastradas</h3>
            </div>
            <div class="col-12 col-md-6">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Pessoas</a></li>
                  <li class="breadcrumb-item active">Listar</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <div class="card people-table-card">
          <div class="card-header people-card-header">
            <div>
              <h5 class="people-card-title">Lista de Pessoas Cadastradas</h5>
              <div class="people-table-count"><?= count($rows) ?> registros encontrados</div>
            </div>

            <div class="people-toolbar">
              <select id="selBairro" class="form-select people-bairro-select" aria-label="Filtrar por bairro">
                <option value="">Todos os bairros</option>
                <?php foreach ($bairros as $b): ?>
                  <option value="<?= (int)$b['id'] ?>" <?= ($bairro_id && $bairro_id == (int)$b['id']) ? 'selected' : '' ?>>
                    <?= h((string)$b['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <div class="dt-search-wrap">
                <input type="text" id="inpSearch" class="dt-search-input" placeholder="Buscar por nome/CPF/responsável..."
                  value="<?= h($q) ?>" autocomplete="off">
                <button class="dt-search-clear" id="btnLimpar" type="button" title="Limpar filtros" aria-label="Limpar filtros">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="table-responsive">
              <table class="table clean-table align-middle text-nowrap w-100" id="tbl">
                <thead>
                  <tr>
                    <th class="sortable text-start" data-sort="nome">Nome <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="bairro">Bairro <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="cpf">CPF <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="telefone">Telefone <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="responsavel">Responsável (Servidor) <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="pbf">PBF <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="bpc">BPC <span class="sort-prisma"></span></th>
                    <th class="sortable text-center" data-sort="renda" data-type="number">Renda Fam. <span class="sort-prisma"></span></th>
                    <th class="sortable text-start" data-sort="trabalho">S. Profissional <span class="sort-prisma"></span></th>
                    <th class="text-center">Detalhes</th>
                  </tr>
                </thead>

                <tbody id="tbody">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="10" class="text-center text-muted">Nenhum registro encontrado.</td>
                    </tr>
                    <?php else: foreach ($rows as $r): ?>
                      <?php
                      $resp = (string)($r['responsavel_cadastro'] ?? '');
                      $respLower = mb_lower($resp);
                      ?>
                      <tr
                        data-id="<?= (int)$r['id'] ?>"
                        data-nome="<?= h(mb_lower((string)($r['nome'] ?? ''))) ?>"
                        data-cpf="<?= h(only_digits($r['cpf'])) ?>"
                        data-bairro="<?= h(mb_lower((string)$r['bairro_nome'])) ?>"
                        data-telefone="<?= h(only_digits($r['telefone'])) ?>"
                        data-responsavel="<?= h($respLower) ?>"
                        data-pbf="<?= h(mb_lower((string)($r['pbf'] ?? 'Não'))) ?>"
                        data-bpc="<?= h(mb_lower((string)($r['bpc'] ?? 'Não'))) ?>"
                        data-renda="<?= h((string)((float)($r['renda_familiar'] ?? 0))) ?>"
                        data-trabalho="<?= h(mb_lower((string)($r['trabalho'] ?? ''))) ?>">

                        <td class="td-nome" title="<?= h((string)$r['nome']) ?>"><?= h((string)$r['nome']) ?></td>
                        <td class="td-bairro text-center" title="<?= h((string)$r['bairro_nome']) ?>"><?= h((string)$r['bairro_nome']) ?></td>
                        <td class="nowrap text-center"><?= h(fmt_cpf($r['cpf'])) ?></td>
                        <td class="nowrap text-center"><?= h(fmt_phone($r['telefone'])) ?></td>
                        <td class="td-responsavel text-center" title="<?= h($resp !== '' ? $resp : '—') ?>"><?= h($resp !== '' ? $resp : '—') ?></td>

                        <td class="text-center"><?= h($r['pbf'] ?? 'Não') ?></td>
                        <td class="text-center"><?= h($r['bpc'] ?? 'Não') ?></td>

                        <td class="text-center"><?= h(fmt_money($r['renda_familiar'])) ?></td>
                        <td class="td-profissao" title="<?= h($r['trabalho'] ?? '—') ?>"><?= h($r['trabalho'] ?? '—') ?></td>

                        <td class="text-center">
                          <div class="table-actions">
                            <button class="btn btn-sm btn-outline-secondary btnDetalhes" title="Ver detalhes">Ver</button>
                            <a href="editarSolicitante.php?id=<?= (int)$r['id'] ?>&cpf=<?= h(only_digits($r['cpf'])) ?>"
                              class="btn btn-sm btn-outline-primary" title="Editar">Editar</a>
                          </div>
                        </td>
                      </tr>
                  <?php endforeach;
                  endif; ?>
                </tbody>

              </table>
            </div>

            <!-- Paginação client-side -->
            <div class="mt-2 tfoot-pager">
              <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
              </div>
              <div class="flex-grow-1 d-flex justify-content-center">
                <strong id="lblPagina">Página 1 de 1</strong>
              </div>
              <div class="d-flex align-items-center gap-2">
                <label for="selPerPage" class="form-label m-0">por página</label>
                <select id="selPerPage" class="form-select form-select-sm" style="width:auto">
                  <option>10</option>
                  <option>20</option>
                  <option>50</option>
                  <option>100</option>
                </select>
              </div>
            </div>

          </div>

        </div>

      </div>

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
          </div>
          <div class="float-end text-black">
            <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal Detalhes -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Beneficiário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <!-- Cabeçalho do perfil -->
          <div class="profile-wrap">
            <img id="md-foto" class="modal-photo" src="assets/images/user.png" alt="Foto">
            <div class="profile-info">
              <h5 class="profile-name" id="md-nome">—</h5>
              <div class="profile-subline">
                <span class="pill"><i class="bi bi-person"></i> <span id="md-genero">—</span></span>
                <span class="pill"><i class="bi bi-heart"></i> <span id="md-ec">—</span></span>
                <span class="pill"><i class="bi bi-calendar2"></i> <span id="md-nasc">—</span></span>
                <span class="pill"><i class="bi bi-person-badge"></i> <span id="md-resp-pill">—</span></span>
              </div>
              <div class="text-muted mt-1" style="font-size:.875rem;">Cadastro: <span id="md-criado">—</span></div>
            </div>
          </div>

          <!-- Seção de Histórico de Solicitações -->
          <h6 class="mb-2">Histórico de Solicitações</h6>
          <div id="md-solicitacoes" class="mb-3">
            <div class="alert alert-info">
              Carregando histórico de solicitações...
            </div>
          </div>

          <!-- I. Identificação -->
          <h6 class="mb-2">I. Identificação</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Situação Profissional</div>
              <div class="kv-value" id="md-trabalho">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">Local de Trabalho</div>
              <div class="kv-value" id="md-local">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">CPF</div>
              <div class="kv-value" id="md-cpf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">NIS</div>
              <div class="kv-value" id="md-nis">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">RG</div>
              <div class="kv-value" id="md-rg">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Emissão do RG</div>
              <div class="kv-value" id="md-rg-emissao">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">UF (RG)</div>
              <div class="kv-value" id="md-rg-uf">—</div>
            </div>

            <!-- Telefone + WhatsApp -->
            <div class="kv">
              <div class="kv-label">Telefone</div>
              <div class="kv-value whats-wrap">
                <span id="md-tel">—</span>
                <a id="md-whats" class="whats-link disabled" href="#" target="_blank" rel="noopener" title="Abrir WhatsApp">
                  <i class="bi bi-whatsapp fs-5"></i>
                </a>
              </div>
            </div>

            <!-- Responsável (Servidor) do cadastro -->
            <div class="kv">
              <div class="kv-label">Responsável (Servidor)</div>
              <div class="kv-value" id="md-responsavel">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">Gênero</div>
              <div class="kv-value" id="md-genero-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Estado Civil</div>
              <div class="kv-value" id="md-ec-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nascimento</div>
              <div class="kv-value" id="md-nasc-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nacionalidade</div>
              <div class="kv-value" id="md-nac">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Naturalidade</div>
              <div class="kv-value" id="md-nat">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tempo de Moradia</div>
              <div class="kv-value" id="md-tempo">—</div>
            </div>
          </div>

          <!-- II. Endereço -->
          <h6 class="mb-2">II. Endereço</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Endereço</div>
              <div class="kv-value" id="md-endereco">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Número</div>
              <div class="kv-value" id="md-numero">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Complemento</div>
              <div class="kv-value" id="md-complemento">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Bairro</div>
              <div class="kv-value" id="md-bairro">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Referência</div>
              <div class="kv-value" id="md-referencia">—</div>
            </div>
          </div>

          <!-- III. Grupos, Benefícios e Renda -->
          <h6 class="mb-2">III. Grupos, Benefícios e Renda</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Grupo Tradicional</div>
              <div class="kv-value" id="md-grupo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Grupo (Outros)</div>
              <div class="kv-value" id="md-grupo-outros">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">PCD</div>
              <div class="kv-value" id="md-pcd">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipo PCD</div>
              <div class="kv-value" id="md-pcd-tipo">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">BPC</div>
              <div class="kv-value" id="md-bpc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC Valor</div>
              <div class="kv-value" id="md-bpc-valor">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">PBF</div>
              <div class="kv-value" id="md-pbf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PBF Valor</div>
              <div class="kv-value" id="md-pbf-valor">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">Benef. Municipal</div>
              <div class="kv-value" id="md-ben-mun">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Valor Municipal</div>
              <div class="kv-value" id="md-ben-mun-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Benef. Estadual</div>
              <div class="kv-value" id="md-ben-est">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Valor Estadual</div>
              <div class="kv-value" id="md-ben-est-valor">—</div>
            </div>

            <div class="kv">
              <div class="kv-label">Faixa Renda</div>
              <div class="kv-value" id="md-faixa">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda (Outros)</div>
              <div class="kv-value" id="md-faixa-outros">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Trabalho</div>
              <div class="kv-value" id="md-trabalho">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda Individual</div>
              <div class="kv-value" id="md-renda-ind">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda Familiar</div>
              <div class="kv-value" id="md-renda-fam">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Total Rendimentos</div>
              <div class="kv-value" id="md-rend-tot">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipificação</div>
              <div class="kv-value" id="md-tipificacao">—</div>
            </div>
          </div>

          <!-- IV. Composição Familiar (Totais) -->
          <h6 class="mb-2">IV. Composição Familiar (Totais)</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Total Moradores</div>
              <div class="kv-value" id="md-tot-mor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Total Famílias</div>
              <div class="kv-value" id="md-tot-fam">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PCD na Residência</div>
              <div class="kv-value" id="md-pcd-res">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Qtde PCD</div>
              <div class="kv-value" id="md-tot-pcd">—</div>
            </div>
          </div>

          <!-- V. Condições Habitacionais -->
          <h6 class="mb-2">V. Condições Habitacionais</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Situação do Imóvel</div>
              <div class="kv-value" id="md-sit">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Aluguel (R$)</div>
              <div class="kv-value" id="md-sit-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipo da Moradia</div>
              <div class="kv-value" id="md-tipo-moradia">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Abastecimento</div>
              <div class="kv-value" id="md-abast">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Iluminação</div>
              <div class="kv-value" id="md-ilum">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Esgoto</div>
              <div class="kv-value" id="md-esgoto">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Lixo</div>
              <div class="kv-value" id="md-lixo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Entorno</div>
              <div class="kv-value" id="md-entorno">—</div>
            </div>
          </div>

          <!-- VI. Documentos -->
          <div class="docs-head">
            <h6 class="mb-2">VI. Documentos Anexados</h6>
            <a class="btn btn-outline-primary btn-sm" id="btnSocio" target="_blank" href="#"><i class="bi bi-file-text"></i> Ver Folha Socioeconômica</a>
          </div>
          <div id="md-docs" class="mb-3"></div>

          <!-- VII. Familiares -->
          <h6 class="mb-2">VII. Familiares</h6>
          <div class="scroll-x mb-3">
            <table class="table table-sm table-striped align-middle text-nowrap">
              <thead class="table-light">
                <tr>
                  <th>Nome</th>
                  <th>Nascimento</th>
                  <th>Parentesco</th>
                  <th>Escolaridade</th>
                  <th>Observação</th>
                </tr>
              </thead>
              <tbody id="md-familiares"></tbody>
            </table>
          </div>

          <!-- VIII. Cônjuge -->
          <h6 class="mb-2">VIII. Cônjuge</h6>
          <div class="kv-grid mb-1">
            <div class="kv">
              <div class="kv-label">Nome</div>
              <div class="kv-value" id="md-conj-nome">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">NIS</div>
              <div class="kv-value" id="md-conj-nis">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">CPF</div>
              <div class="kv-value" id="md-conj-cpf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">RG</div>
              <div class="kv-value" id="md-conj-rg">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nascimento</div>
              <div class="kv-value" id="md-conj-nasc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Gênero</div>
              <div class="kv-value" id="md-conj-gen">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nacionalidade</div>
              <div class="kv-value" id="md-conj-nac">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Naturalidade</div>
              <div class="kv-value" id="md-conj-nat">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Trabalho</div>
              <div class="kv-value" id="md-conj-trab">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda</div>
              <div class="kv-value" id="md-conj-renda">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PCD</div>
              <div class="kv-value" id="md-conj-pcd">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC</div>
              <div class="kv-value" id="md-conj-bpc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC Valor</div>
              <div class="kv-value" id="md-conj-bpc-valor">—</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'secretario'): ?>
            <a id="btnAtrib" href="#" class="btn btn-primary">Selecionar Solicitação</a>
          <?php endif; ?>

          <button type="button" class="btn btn-success text-white" id="btnNovaSol">Nova Solicitação</button>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Nova Solicitação -->
  <div class="modal fade" id="modalNovaSolicitacao" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nova Solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <form id="formNovaSol">

            <input type="hidden" id="novaSol_pid">

            <div class="mb-3">
              <label class="form-label">Tipo de Ajuda</label>
              <select id="novaSol_ajuda" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($ajudasTipos as $at): ?>
                  <option value="<?= (int)$at['id'] ?>"><?= h($at['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Resumo do Caso</label>
              <textarea
                id="novaSol_resumo"
                class="form-control"
                rows="4"
                required
                placeholder="Descreva a nova necessidade..."></textarea>
            </div>

            <div class="mb-3 nova-sol-photo">
              <label class="form-label">Foto da Solicitação</label>
              <div class="input-group">
                <input
                  type="text"
                  class="form-control"
                  id="novaSol_foto_nome"
                  value="Nenhuma foto capturada"
                  readonly>
                <button type="button" class="btn btn-outline-secondary" id="btnNovaSolCamera" title="Tirar foto com a câmera">
                  <i class="bi bi-camera"></i> Câmera
                </button>
                <button type="button" class="btn btn-outline-danger d-none" id="btnNovaSolRemoverFoto" title="Remover foto">
                  <i class="bi bi-x-circle"></i>
                </button>
              </div>
              <div class="form-text">A foto será anexada junto aos documentos do beneficiário.</div>
              <div class="nova-sol-photo-preview mt-2 d-none" id="novaSolFotoPreviewWrap">
                <img id="novaSolFotoPreview" alt="Pré-visualização da foto da solicitação">
              </div>
            </div>

            <!-- Campo hidden com data/hora em tempo real -->
            <input type="hidden" name="data_solicitacao" id="data_solicitacao">

            <div class="mb-2">
              <small class="text-muted">
                Data/Hora da solicitação:
                <strong><span id="dataHoraExibicao">--/--/---- --:--:--</span></strong>
              </small>
            </div>

          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btnSalvarSol">Salvar Solicitação</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Câmera - Nova Solicitação -->
  <div class="modal fade modal-camera-nova-sol" id="modalCameraNovaSol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-camera"></i>
            <h5 class="modal-title mb-0">Capturar foto</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info mb-3 d-none" id="novaCamInfo">
            O navegador pode solicitar permissão para usar a câmera.
          </div>

          <div class="nova-cam-wrap">
            <div class="nova-cam-frame">
              <video id="novaCamVideo" autoplay playsinline muted></video>
              <canvas id="novaCamCanvas"></canvas>
              <img id="novaCamPhoto" alt="Foto capturada" style="display:none;">
            </div>

            <div class="nova-cam-hint">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNovaSolAlternarCam">
                <i class="bi bi-arrow-repeat"></i> Alternar câmera
              </button>
              <span>Por padrão abrimos a câmera traseira quando disponível.</span>
            </div>
          </div>

          <div class="alert alert-warning mt-3 d-none" id="novaCamWarn"></div>
        </div>

        <div class="modal-footer">
          <div class="w-100 d-flex justify-content-end gap-2" id="novaCamLiveActions">
            <button type="button" class="btn btn-primary" id="btnNovaSolTirarFoto">
              <i class="bi bi-camera"></i> Tirar foto
            </button>
          </div>

          <div class="w-100 d-none justify-content-end gap-2" id="novaCamReviewActions">
            <button type="button" class="btn btn-outline-secondary" id="btnNovaSolTirarOutra">
              <i class="bi bi-arrow-counterclockwise"></i> Tirar outra
            </button>
            <button type="button" class="btn btn-success" id="btnNovaSolUsarFoto">
              <i class="bi bi-check-circle"></i> Usar foto
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* =========================
   CONTROLE DE DATA/HORA
========================= */

    let intervaloDataHora = null;

    // Retorna data/hora no formato MySQL
    function getCurrentDateTimeForMySQL() {
      const agora = new Date();

      const ano = agora.getFullYear();
      const mes = String(agora.getMonth() + 1).padStart(2, '0');
      const dia = String(agora.getDate()).padStart(2, '0');
      const hora = String(agora.getHours()).padStart(2, '0');
      const minuto = String(agora.getMinutes()).padStart(2, '0');
      const segundo = String(agora.getSeconds()).padStart(2, '0');

      return `${ano}-${mes}-${dia} ${hora}:${minuto}:${segundo}`;
    }

    // Retorna data/hora para exibição
    function getFormattedDateTime() {
      const agora = new Date();
      return agora.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }

    // Atualiza campo hidden + texto visível
    function atualizarDataHora() {
      document.getElementById('data_solicitacao').value = getCurrentDateTimeForMySQL();
      document.getElementById('dataHoraExibicao').textContent = getFormattedDateTime();
    }

    /* =========================
       EVENTOS DO MODAL
    ========================= */

    const modalNovaSolEl = document.getElementById('modalNovaSolicitacao');

    if (modalNovaSolEl) {

      // Quando abrir o modal → começa o tempo real
      modalNovaSolEl.addEventListener('shown.bs.modal', function() {
        atualizarDataHora();

        intervaloDataHora = setInterval(atualizarDataHora, 1000);
      });

      // Quando fechar o modal → para o relógio
      modalNovaSolEl.addEventListener('hidden.bs.modal', function() {
        if (intervaloDataHora) {
          clearInterval(intervaloDataHora);
          intervaloDataHora = null;
        }
      });

    }
  </script>


  <!-- Modal Selecionar Solicitação -->
  <div class="modal fade" id="modalSelecionarSolicitacao" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selecionar Solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            Este usuário possui múltiplas solicitações. Escolha para qual delas o benefício será atribuído.
          </div>
          <div class="list-group" id="listaSolicitacoes">
            <!-- JS preenche -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Editar Solicitação -->
  <div class="modal fade" id="modalEditarSolicitacao" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Editar Solicitação</h5>
          <div class="d-flex align-items-center gap-2">
            <?php if (can_delete_solicitacao()): ?>
              <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSolicitacaoModal">
                <i class="bi bi-trash me-1"></i> Excluir
              </button>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
        </div>
        <div class="edit-solicitacao-toolbar" id="editarSolicitacaoFotoWrap">
          <div class="edit-solicitacao-photo">
            <a id="editarSolicitacaoFotoLink" href="#" target="_blank" rel="noopener">
              <img id="editarSolicitacaoFoto" src="" alt="Foto da solicitação">
            </a>
            <div>
              <div class="fw-bold">Foto adicionada na nova solicitação</div>
              <a id="editarSolicitacaoFotoAbrir" href="#" target="_blank" rel="noopener">Abrir imagem original</a>
            </div>
          </div>
        </div>
        <div class="modal-body p-0">
          <iframe
            id="iframeEditarSolicitacao"
            title="Editar solicitação"
            style="width:100%;height:78vh;border:0;"
            loading="lazy"></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      'use strict';

      // Ano rodapé
      const cy = document.getElementById('current-year');
      if (cy) cy.textContent = String(new Date().getFullYear());

      // ====== Filtros ======
      const selBairro = document.getElementById('selBairro');
      const inpSearch = document.getElementById('inpSearch');
      const btnLimpar = document.getElementById('btnLimpar');

      selBairro?.addEventListener('change', () => {
        const params = new URLSearchParams(window.location.search);
        const val = selBairro.value;
        if (val) params.set('bairro_id', val);
        else params.delete('bairro_id');

        const q = inpSearch.value.trim();
        if (q) params.set('q', q);
        else params.delete('q');

        window.location.search = params.toString();
      });

      btnLimpar?.addEventListener('click', () => {
        inpSearch.value = '';
        selBairro.value = '';
        const params = new URLSearchParams(window.location.search);
        params.delete('q');
        params.delete('bairro_id');
        window.location.search = params.toString();
      });

      // ====== Paginação + busca + ordenação client-side ======
      const tbody = document.getElementById('tbody');
      const allRows = Array.from(tbody?.querySelectorAll('tr[data-id]') || []);
      let emptyRow = tbody?.querySelector('tr:not([data-id])') || null;
      if (!emptyRow && tbody) {
        emptyRow = document.createElement('tr');
        emptyRow.innerHTML = '<td colspan="10" class="text-center text-muted py-4">Nenhum registro encontrado.</td>';
        emptyRow.style.display = 'none';
        tbody.appendChild(emptyRow);
      }
      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');
      const sortableHeaders = Array.from(document.querySelectorAll('#tbl thead th.sortable'));

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10);
      let filtered = allRows.slice();
      let sortState = { key: '', direction: 'asc', type: 'string' };

      function normalizeSortValue(value) {
        return String(value || '')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .toLowerCase()
          .trim();
      }

      function rowValue(row, key, type) {
        if (type === 'number') {
          const numberValue = Number(String(row.dataset[key] || '0').replace(',', '.'));
          return Number.isFinite(numberValue) ? numberValue : 0;
        }

        return normalizeSortValue(row.dataset[key] || '');
      }

      function updateSortIndicators() {
        sortableHeaders.forEach((th) => {
          const active = th.dataset.sort === sortState.key;
          th.classList.toggle('sort-asc', active && sortState.direction === 'asc');
          th.classList.toggle('sort-desc', active && sortState.direction === 'desc');
          th.setAttribute('aria-sort', !active ? 'none' : (sortState.direction === 'asc' ? 'ascending' : 'descending'));
        });
      }

      function applySort(rows) {
        if (!sortState.key) return rows.slice();

        return rows.slice().sort((a, b) => {
          const aValue = rowValue(a, sortState.key, sortState.type);
          const bValue = rowValue(b, sortState.key, sortState.type);
          let result = 0;

          if (sortState.type === 'number') {
            result = aValue - bValue;
          } else {
            result = String(aValue).localeCompare(String(bValue), 'pt-BR', { sensitivity: 'base', numeric: true });
          }

          return sortState.direction === 'asc' ? result : -result;
        });
      }

      function paginateAndShow() {
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;

        const start = (page - 1) * perPage;
        const end = start + perPage;

        allRows.forEach(r => (r.style.display = 'none'));
        if (emptyRow) emptyRow.style.display = total === 0 ? '' : 'none';
        filtered.slice(start, end).forEach(r => (r.style.display = ''));

        if (lblPagina) lblPagina.textContent = `Página ${page} de ${pages}`;
        if (btnPrev) btnPrev.disabled = page <= 1;
        if (btnNext) btnNext.disabled = page >= pages;
      }

      function filterAndRender() {
        const qRaw = (inpSearch.value || '').trim();
        const q = normalizeSortValue(qRaw);
        const qDigits = qRaw.replace(/\D+/g, '');

        filtered = allRows.filter(r => {
          const nome = normalizeSortValue(r.dataset.nome || '');
          const cpf = (r.dataset.cpf || '');
          const resp = normalizeSortValue(r.dataset.responsavel || '');

          if (!q) return true;

          return (
            nome.includes(q) ||
            resp.includes(q) ||
            (qDigits && cpf.startsWith(qDigits))
          );
        });

        filtered = applySort(filtered);
        page = 1;
        paginateAndShow();
        updateSortIndicators();
      }

      sortableHeaders.forEach((th) => {
        th.setAttribute('role', 'button');
        th.setAttribute('tabindex', '0');
        th.addEventListener('click', () => {
          const key = th.dataset.sort || '';
          const type = th.dataset.type || 'string';
          if (!key) return;

          if (sortState.key === key) {
            sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
          } else {
            sortState = { key, direction: 'asc', type };
          }

          filterAndRender();
        });

        th.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            th.click();
          }
        });
      });

      selPerPage?.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        paginateAndShow();
      });

      btnPrev?.addEventListener('click', () => {
        if (page > 1) {
          page--;
          paginateAndShow();
        }
      });

      btnNext?.addEventListener('click', () => {
        page++;
        paginateAndShow();
      });

      inpSearch?.addEventListener('input', filterAndRender);
      filterAndRender();

      // ====== Utilitários do Modal ======
      const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = (val ?? '').toString().trim() !== '' ? String(val) : '—';
      };

      const escapeHtml = (s) => (s ?? '').toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      } [m]));

      const money = (n) => (n === null || n === undefined || n === '') ?
        '—' :
        'R$ ' + (Number(n) || 0).toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

      const brDate = (ymd) => {
        const p = (ymd || '').split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : '—';
      };

      const brDateTime = (dt) => {
        const [d, t] = (dt || '').split(' ');
        return d ? (brDate(d) + (t ? (' ' + t) : '')) : '—';
      };

      const formatCPF = (cpf) => {
        const d = (cpf || '').replace(/\D+/g, '');
        return d.length !== 11 ? (cpf || '—') : `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
      };

      const formatPhone = (f) => {
        const d = (f || '').replace(/\D+/g, '');
        if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
        return f || '—';
      };

      const num = (x) => Number.isFinite(Number(x)) ? Number(x) : 0;

      const getResp = (s) => {
        const keys = [
          'responsavel_cadastro', 'responsavel', 'servidor', 'servidor_cadastro',
          'usuario_responsavel', 'usuario_cadastro', 'criado_por', 'created_by', 'usuario_nome'
        ];
        for (const k of keys) {
          const v = (s && s[k] != null) ? String(s[k]).trim() : '';
          if (v) return v;
        }
        return '';
      };

      const setWhatsAppLink = (telefone) => {
        const a = document.getElementById('md-whats');
        if (!a) return;

        let d = String(telefone || '').replace(/\D+/g, '');
        d = d.replace(/^0+/, '');

        if (d.startsWith('55') && (d.length === 12 || d.length === 13)) {
          // ok
        } else if (d.length === 10 || d.length === 11) {
          d = '55' + d;
        } else {
          a.href = '#';
          a.classList.add('disabled');
          return;
        }

        a.href = `https://wa.me/${encodeURIComponent(d)}`;
        a.classList.remove('disabled');
      };

      // ====== Editar Solicitação ======
      let solicitacaoEmEdicao = null;
      let reabrirDetalhesAposEdicao = false;

      function abrirEdicaoSolicitacao(solicitacao) {
        const id = Number(solicitacao?.id ?? solicitacao?.solicitacao_id ?? 0);
        if (!id || Number(id) <= 0) return;
        const iframe = document.getElementById('iframeEditarSolicitacao');
        const modalEl = document.getElementById('modalEditarSolicitacao');
        if (!iframe || !modalEl) return;

        solicitacaoEmEdicao = solicitacao;

        const foto = String(solicitacao?.foto_solicitacao || '').replace(/\\/g, '/');
        const fotoSegura = foto !== '' && !foto.includes('..') && !/^[a-z][a-z0-9+.-]*:/i.test(foto);
        const fotoWrap = document.getElementById('editarSolicitacaoFotoWrap');
        const fotoImg = document.getElementById('editarSolicitacaoFoto');
        const fotoLink = document.getElementById('editarSolicitacaoFotoLink');
        const fotoAbrir = document.getElementById('editarSolicitacaoFotoAbrir');
        fotoWrap?.classList.toggle('d-none', !fotoSegura);
        if (fotoSegura) {
          if (fotoImg) {
            fotoImg.onerror = () => fotoWrap?.classList.add('d-none');
            fotoImg.src = foto;
          }
          if (fotoLink) fotoLink.href = foto;
          if (fotoAbrir) fotoAbrir.href = foto;
        }

        const btnExcluir = document.getElementById('btnExcluirSolicitacaoModal');
        if (btnExcluir) btnExcluir.disabled = false;

        iframe.src = `editarSolicitacao.php?id=${encodeURIComponent(id)}&modal=1`;
        const detalhesEl = document.getElementById('modalDetalhes');
        const detalhesModal = detalhesEl ? bootstrap.Modal.getInstance(detalhesEl) : null;
        reabrirDetalhesAposEdicao = Boolean(detalhesModal);

        if (detalhesModal && detalhesEl) {
          detalhesEl.addEventListener('hidden.bs.modal', () => {
            new bootstrap.Modal(modalEl).show();
          }, { once: true });
          detalhesModal.hide();
        } else {
          new bootstrap.Modal(modalEl).show();
        }
      }

      let currentPessoaCpf = '';

      // ====== Histórico de Solicitações ======
      function renderSolicitacoes(solicitacoes) {
        const container = document.getElementById('md-solicitacoes');
        if (!container) return;

        if (!Array.isArray(solicitacoes) || solicitacoes.length === 0) {
          container.innerHTML = '<div class="alert alert-info">Nenhuma solicitação registrada para este beneficiário.</div>';
          return;
        }

        let html = '';

        solicitacoes.forEach(sol => {
          const solId = Number(sol.id ?? sol.solicitacao_id ?? 0);
          const isCadastro = solId === 0 || String(sol.origem || '').toLowerCase() === 'cadastro';
          const hasSolicitacaoId = solId > 0;
          const entregasCount = Number(sol.entregas_count || 0);
          const solicitanteRef = Number(sol.solicitante_id || 0);

          let statusClass = 'status-aberto';
          if (sol.status === 'Cadastro') statusClass = 'status-cadastro';
          else if (sol.status === 'Em andamento') statusClass = 'status-em-andamento';
          else if (sol.status === 'Concluído' || sol.status === 'Concluido') statusClass = 'status-concluido';
          else if (sol.status === 'Cancelado') statusClass = 'status-cancelado';

          const tipoLabel = isCadastro ? 'Solicitação Inicial (Cadastro)' : 'Solicitação Adicional';

          const btnEditar = hasSolicitacaoId ?
            `
            <button
              type="button"
              class="btn btn-sm btn-outline-primary btn-editar-solicitacao"
              data-id="${solId}"
              title="Editar solicitação"
            >
              <i class="bi bi-pencil-square"></i>
            </button>
          ` :
            '';

          const btnAtribuir = (hasSolicitacaoId && currentPessoaCpf) ?
            `
            <a
              class="btn btn-sm ${entregasCount > 0 ? 'btn-outline-success' : 'btn-success'}"
              href="atribuirBeneficio.php?cpf=${encodeURIComponent(currentPessoaCpf)}&solicitacao_id=${encodeURIComponent(solId)}"
              title="Atribuir benefício nesta solicitação"
            >
              <i class="bi bi-check2-circle"></i> ${entregasCount > 0 ? 'Atribuir novamente' : 'Atribuir'}
            </a>
          ` :
            '';

          const entregaBadge = hasSolicitacaoId ? (
            entregasCount > 0 ?
            `<span class="badge bg-success ms-2">${entregasCount} entrega(s)</span>` :
            `<span class="badge bg-light text-dark border ms-2">Sem entrega</span>`
          ) : '';

          html += `
          <div class="solicitacao-card">
            <div class="solicitacao-header">
              <div>
                <span class="solicitacao-tipo">${escapeHtml(sol.ajuda_nome || sol.ajuda_tipo_nome || 'Não informado')}</span>
                ${
                  sol.ajuda_categoria || sol.ajuda_tipo_categoria
                    ? `<span class="badge bg-secondary ms-2">${escapeHtml(sol.ajuda_categoria || sol.ajuda_tipo_categoria)}</span>`
                    : ''
                }
                <span class="badge bg-info ms-2">${tipoLabel}</span>
                ${solicitanteRef > 0 ? `<span class="badge bg-light text-dark border ms-2">Pessoa #${solicitanteRef}</span>` : ''}
                ${entregaBadge}
              </div>

              <div class="d-flex align-items-center gap-2">
                <div class="solicitacao-data">${brDateTime(sol.data_solicitacao)}</div>
                ${btnAtribuir}
                ${btnEditar}
              </div>
            </div>

            <div>
              <span class="solicitacao-status ${statusClass}">${escapeHtml(sol.status || '—')}</span>
              ${sol.created_by ? `<span class="ms-2 text-muted">Criado por: ${escapeHtml(sol.created_by)}</span>` : ''}
              ${entregasCount > 0 ? `<span class="ms-2 text-muted">Última entrega: ${brDate(sol.data_entrega || '')} ${escapeHtml(sol.hora_entrega || '')}</span>` : ''}
            </div>

            <div class="solicitacao-resumo">
              <strong>Resumo:</strong> ${escapeHtml(sol.resumo_caso || 'Não informado')}
            </div>
          </div>
        `;
        });

        container.innerHTML = html;

        container.querySelectorAll('.btn-editar-solicitacao').forEach(btn => {
          btn.addEventListener('click', () => {
            const solicitacao = solicitacoes.find(item => Number(item.id ?? item.solicitacao_id ?? 0) === Number(btn.dataset.id));
            abrirEdicaoSolicitacao(solicitacao);
          });
        });
      }

      // ====== Abrir modal com AJAX ======
      let currentPessoaId = null;

      const modalEditarSolicitacaoEl = document.getElementById('modalEditarSolicitacao');
      document.getElementById('iframeEditarSolicitacao')?.addEventListener('load', () => {
        const btnExcluir = document.getElementById('btnExcluirSolicitacaoModal');
        if (btnExcluir) btnExcluir.disabled = false;
      });

      modalEditarSolicitacaoEl?.addEventListener('hidden.bs.modal', () => {
        const iframe = document.getElementById('iframeEditarSolicitacao');
        if (iframe) iframe.src = 'about:blank';

        const fotoWrap = document.getElementById('editarSolicitacaoFotoWrap');
        fotoWrap?.classList.add('d-none');
        const btnExcluir = document.getElementById('btnExcluirSolicitacaoModal');
        if (btnExcluir) btnExcluir.disabled = false;

        if (reabrirDetalhesAposEdicao && currentPessoaId) {
          reabrirDetalhesAposEdicao = false;
          const detalheBtn = Array
            .from(document.querySelectorAll('tr[data-id] .btnDetalhes'))
            .find(btn => btn.closest('tr')?.dataset?.id === String(currentPessoaId));
          detalheBtn?.click();
        }
      });

      document.getElementById('btnExcluirSolicitacaoModal')?.addEventListener('click', () => {
        if (!solicitacaoEmEdicao) return;
        if (!confirm('Excluir permanentemente esta solicitação e a imagem anexada? Esta ação não pode ser desfeita.')) return;

        const iframe = document.getElementById('iframeEditarSolicitacao');
        iframe?.contentWindow?.postMessage({ type: 'excluirSolicitacao' }, window.location.origin);
      });

      window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const tipo = event.data?.type || '';
        if (tipo !== 'solicitacaoAtualizada' && tipo !== 'solicitacaoExcluida' && tipo !== 'fecharEdicaoSolicitacao') return;

        const modalEl = document.getElementById('modalEditarSolicitacao');
        bootstrap.Modal.getInstance(modalEl)?.hide();
      });

      document.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('.btnDetalhes');
        if (!btn) return;

        const tr = btn.closest('tr');
        const id = tr?.dataset?.id;
        if (!id) return;

        currentPessoaId = id;

        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'detalhes');
        url.searchParams.set('id', id);

        let j;
        try {
          const res = await fetch(url.toString(), {
            headers: {
              'X-Requested-With': 'fetch'
            }
          });
          j = await res.json();
        } catch {
          j = {
            ok: false
          };
        }

        if (!j.ok) {
          alert('Falha ao carregar detalhes.');
          return;
        }

        const s = j.solicitante || {};
        const foto = (s.foto_path && String(s.foto_path).trim() !== '') ?
          String(s.foto_path) :
          'assets/images/user.png';
        currentPessoaCpf = String(s.cpf || '').replace(/\D+/g, '');

        const img = document.getElementById('md-foto');
        if (img) {
          img.onerror = () => {
            img.onerror = null;
            img.src = 'assets/images/user.png';
          };
          img.src = foto;
        }

        const respCad = getResp(s);

        setText('md-nome', s.nome || '—');
        setText('md-genero', s.genero || '—');
        setText('md-ec', s.estado_civil || '—');
        setText('md-nasc', s.data_nascimento ? brDate(s.data_nascimento) : '—');
        setText('md-criado', s.created_at ? brDateTime(s.created_at) : '—');

        setText('md-resp-pill', respCad || '—');
        setText('md-responsavel', respCad || '—');

        renderSolicitacoes(j.solicitacoes || []);

        setText('md-cpf', formatCPF(s.cpf || ''));
        setText('md-rg', s.rg || '—');
        setText('md-nis', s.nis || '—');
        setText('md-trabalho', s.trabalho || '—');
        setText('md-local', s.local_trabalho || '—');
        setText('md-rg-emissao', s.rg_emissao ? brDate(s.rg_emissao) : '—');
        setText('md-rg-uf', s.rg_uf || '—');

        setText('md-tel', formatPhone(s.telefone || ''));
        setWhatsAppLink(s.telefone || '');

        setText('md-genero-2', s.genero || '—');
        setText('md-ec-2', s.estado_civil || '—');
        setText('md-nasc-2', s.data_nascimento ? brDate(s.data_nascimento) : '—');
        setText('md-nac', s.nacionalidade || '—');
        setText('md-nat', s.naturalidade || '—');
        setText('md-tempo', `${num(s.tempo_anos) || 0} ano(s)${s.tempo_meses ? `, ${num(s.tempo_meses)} mês(es)` : ''}`);

        setText('md-endereco', s.endereco || '—');
        setText('md-numero', s.numero || '—');
        setText('md-complemento', s.complemento || '—');
        setText('md-bairro', s.bairro_nome || '—');
        setText('md-referencia', s.referencia || '—');

        setText('md-grupo', s.grupo_tradicional || '—');
        setText('md-grupo-outros', s.grupo_outros || '—');
        setText('md-pcd', s.pcd || 'Não');
        setText('md-pcd-tipo', s.pcd_tipo || '—');
        setText('md-bpc', s.bpc || 'Não');
        setText('md-bpc-valor', money(s.bpc_valor));
        setText('md-pbf', s.pbf || 'Não');
        setText('md-pbf-valor', money(s.pbf_valor));
        setText('md-ben-mun', s.beneficio_municipal || 'Não');
        setText('md-ben-mun-valor', money(s.beneficio_municipal_valor));
        setText('md-ben-est', s.beneficio_estadual || 'Não');
        setText('md-ben-est-valor', money(s.beneficio_estadual_valor));
        setText('md-faixa', s.renda_mensal_faixa || '—');
        setText('md-faixa-outros', s.renda_mensal_outros || '—');
        setText('md-trabalho', s.trabalho || '—');
        setText('md-renda-ind', money(s.renda_individual));
        setText('md-renda-fam', money(s.renda_familiar));
        setText('md-rend-tot', money(s.total_rendimentos));
        setText('md-tipificacao', s.tipificacao || '—');

        setText('md-tot-mor', (s.total_moradores ?? '—'));
        setText('md-tot-fam', (s.total_familias ?? '—'));
        setText('md-pcd-res', s.pcd_residencia || '—');
        setText('md-tot-pcd', (s.total_pcd ?? '—'));

        setText('md-sit', s.situacao_imovel || '—');
        setText('md-sit-valor', (s.situacao_imovel === 'Alugado') ? money(s.situacao_imovel_valor) : '—');
        setText('md-tipo-moradia', s.tipo_moradia || '—');
        setText('md-abast', s.abastecimento || '—');
        setText('md-ilum', s.iluminacao || '—');
        setText('md-esgoto', s.esgoto || '—');
        setText('md-lixo', s.lixo || '—');
        setText('md-entorno', s.entorno || '—');

        const docsWrap = document.getElementById('md-docs');
        docsWrap.innerHTML = '';
        if (Array.isArray(j.documentos) && j.documentos.length) {
          j.documentos.forEach(d => {
            const row = document.createElement('div');
            row.className = 'doc-row';

            const left = document.createElement('div');
            left.className = 'doc-meta';
            const sizeMb = d.size_bytes ? (Number(d.size_bytes) / 1024 / 1024).toFixed(2) + ' MB' : '';
            const when = d.created_at ? brDateTime(d.created_at) : '';
            left.innerHTML = `
            <i class="bi bi-paperclip fs-5"></i>
            <div>
              <div class="doc-name">${escapeHtml(d.original_name || 'Documento')}</div>
              <div class="doc-sub">${[sizeMb, when].filter(Boolean).join(' • ')}</div>
            </div>`;

            const a = document.createElement('a');
            a.className = 'btn btn-sm btn-outline-primary';
            a.target = '_blank';
            a.rel = 'noopener';
            a.href = (d.arquivo_path || '#');
            a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Abrir';

            row.appendChild(left);
            row.appendChild(a);
            docsWrap.appendChild(row);
          });
        } else {
          docsWrap.innerHTML = '<span class="text-muted">Nenhum documento anexado.</span>';
        }

        const tb = document.getElementById('md-familiares');
        if (tb) {
          tb.innerHTML = '';
          if (Array.isArray(j.familiares) && j.familiares.length) {
            j.familiares.forEach(f => {
              const trFam = document.createElement('tr');
              trFam.innerHTML = `
              <td>${escapeHtml(f.nome || '—')}</td>
              <td>${f.data_nascimento ? brDate(f.data_nascimento) : '—'}</td>
              <td>${escapeHtml(f.parentesco || '—')}</td>
              <td>${escapeHtml(f.escolaridade || '—')}</td>
              <td>${escapeHtml(f.obs || '')}</td>`;
              tb.appendChild(trFam);
            });
          } else {
            const trFam = document.createElement('tr');
            trFam.innerHTML = `<td colspan="5" class="text-center text-muted">Sem familiares cadastrados.</td>`;
            tb.appendChild(trFam);
          }
        }

        setText('md-conj-nome', s.conj_nome || '—');
        setText('md-conj-nis', s.conj_nis || '—');
        setText('md-conj-cpf', formatCPF(s.conj_cpf || ''));
        setText('md-conj-rg', s.conj_rg || '—');
        setText('md-conj-nasc', s.conj_nasc ? brDate(s.conj_nasc) : '—');
        setText('md-conj-gen', s.conj_genero || '—');
        setText('md-conj-nac', s.conj_nacionalidade || '—');
        setText('md-conj-nat', s.conj_naturalidade || '—');
        setText('md-conj-trab', s.conj_trabalho || '—');
        setText('md-conj-renda', money(s.conj_renda));
        setText('md-conj-pcd', s.conj_pcd || '—');
        setText('md-conj-bpc', s.conj_bpc || '—');
        setText('md-conj-bpc-valor', money(s.conj_bpc_valor));

        const cpfDigits = (s.cpf || '').replace(/\D+/g, '');

        const btnSocio = document.getElementById('btnSocio');
        if (btnSocio) {
          btnSocio.href = `imprimirSocioeconomico.php?cpf=${encodeURIComponent(cpfDigits)}`;
        }

        const btnAtrib = document.getElementById('btnAtrib');
        if (btnAtrib) {
          const solicitacoesAtivas = (j.solicitacoes || []).filter(sol => Number(sol.id ?? sol.solicitacao_id ?? 0) !== 0);

          if (solicitacoesAtivas.length > 1) {
            btnAtrib.href = '#';
            btnAtrib.onclick = (ev) => {
              ev.preventDefault();
              mostrarModalSelecaoSolicitacao(solicitacoesAtivas, cpfDigits);
            };
          } else if (solicitacoesAtivas.length === 1) {
            const solId = Number(solicitacoesAtivas[0].id ?? solicitacoesAtivas[0].solicitacao_id ?? 0);
            btnAtrib.href = `atribuirBeneficio.php?cpf=${encodeURIComponent(cpfDigits)}&solicitacao_id=${solId}`;
            btnAtrib.onclick = null;
          } else {
            btnAtrib.href = '#';
            btnAtrib.onclick = (ev) => {
              ev.preventDefault();
              alert('Crie ou selecione uma solicitação antes de atribuir o benefício.');
            };
          }
        }

        const modalEl = document.getElementById('modalDetalhes');
        if (modalEl) new bootstrap.Modal(modalEl).show();
      });

      // ====== Modal de Seleção de Solicitação ======
      function mostrarModalSelecaoSolicitacao(solicitacoes, cpfDigits) {
        const list = document.getElementById('listaSolicitacoes');
        list.innerHTML = '';

        solicitacoes.forEach(s => {
          const solId = Number(s.id ?? s.solicitacao_id ?? 0);
          const entregasCount = Number(s.entregas_count || 0);
          const solicitanteRef = Number(s.solicitante_id || 0);

          const a = document.createElement('a');
          a.className = 'list-group-item list-group-item-action';
          a.style.cursor = 'pointer';

          let statusClass = 'bg-secondary';
          if (s.status === 'Em andamento') statusClass = 'bg-warning';
          else if (s.status === 'Concluído' || s.status === 'Concluido') statusClass = 'bg-success';
          else if (s.status === 'Cancelado') statusClass = 'bg-danger';

          a.innerHTML = `
          <div class="d-flex w-100 justify-content-between">
            <h6 class="mb-1">${escapeHtml(s.ajuda_nome || s.ajuda_tipo_nome || 'Não informado')}</h6>
            <small>${brDateTime(s.data_solicitacao)}</small>
          </div>
          <p class="mb-1">${escapeHtml((s.resumo_caso || '').substring(0, 100) || 'Sem resumo')}${(s.resumo_caso || '').length > 100 ? '...' : ''}</p>
          <div class="d-flex justify-content-between align-items-center">
            <small>
              <span class="badge ${statusClass}">${escapeHtml(s.status || '—')}</span>
              ${solicitanteRef > 0 ? `<span class="ms-1">Pessoa #${solicitanteRef}</span>` : ''}
              ${(s.ajuda_categoria || s.ajuda_tipo_categoria) ? `<span class="ms-1">${escapeHtml(s.ajuda_categoria || s.ajuda_tipo_categoria)}</span>` : ''}
              <span class="ms-1">${entregasCount > 0 ? `${entregasCount} entrega(s) registrada(s)` : 'Sem entrega registrada'}</span>
            </small>
            <button class="btn btn-sm btn-outline-primary">Selecionar</button>
          </div>
        `;

          a.onclick = () => {
            window.location.href = `atribuirBeneficio.php?cpf=${encodeURIComponent(cpfDigits)}&solicitacao_id=${solId}`;
          };

          list.appendChild(a);
        });

        const modalSelSol = new bootstrap.Modal(document.getElementById('modalSelecionarSolicitacao'));
        modalSelSol.show();
      }

      // ====== Nova Solicitação ======
      const btnNovaSol = document.getElementById('btnNovaSol');
      const modalNovaSolEl = document.getElementById('modalNovaSolicitacao');
      const bsNovaSol = modalNovaSolEl ? new bootstrap.Modal(modalNovaSolEl) : null;

      const novaSolFotoNome = document.getElementById('novaSol_foto_nome');
      const novaSolFotoPreviewWrap = document.getElementById('novaSolFotoPreviewWrap');
      const novaSolFotoPreview = document.getElementById('novaSolFotoPreview');
      const btnNovaSolRemoverFoto = document.getElementById('btnNovaSolRemoverFoto');
      let novaSolFotoFile = null;

      function resetNovaSolFoto() {
        novaSolFotoFile = null;
        if (novaSolFotoNome) novaSolFotoNome.value = 'Nenhuma foto capturada';
        if (novaSolFotoPreview) novaSolFotoPreview.removeAttribute('src');
        novaSolFotoPreviewWrap?.classList.add('d-none');
        btnNovaSolRemoverFoto?.classList.add('d-none');
      }

      function dataURLtoFile(dataUrl, filename) {
        const parts = String(dataUrl || '').split(',');
        const mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        const binary = atob(parts[1] || '');
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
          bytes[i] = binary.charCodeAt(i);
        }
        return new File([bytes], filename, {
          type: mime,
          lastModified: Date.now()
        });
      }

      function applyNovaSolFoto(dataUrl, mime) {
        if (!dataUrl) return;
        novaSolFotoFile = dataURLtoFile(dataUrl, 'foto_solicitacao.jpg');
        if (novaSolFotoNome) novaSolFotoNome.value = 'Foto capturada.jpg';
        if (novaSolFotoPreview) novaSolFotoPreview.src = dataUrl;
        novaSolFotoPreviewWrap?.classList.remove('d-none');
        btnNovaSolRemoverFoto?.classList.remove('d-none');
      }

      btnNovaSolRemoverFoto?.addEventListener('click', resetNovaSolFoto);

      // ====== Câmera da Nova Solicitação ======
      const modalCameraNovaSolEl = document.getElementById('modalCameraNovaSol');
      const bsCameraNovaSol = modalCameraNovaSolEl ? new bootstrap.Modal(modalCameraNovaSolEl, {
        backdrop: 'static'
      }) : null;
      const btnNovaSolCamera = document.getElementById('btnNovaSolCamera');
      const btnNovaSolAlternarCam = document.getElementById('btnNovaSolAlternarCam');
      const btnNovaSolTirarFoto = document.getElementById('btnNovaSolTirarFoto');
      const btnNovaSolTirarOutra = document.getElementById('btnNovaSolTirarOutra');
      const btnNovaSolUsarFoto = document.getElementById('btnNovaSolUsarFoto');
      const novaCamVideo = document.getElementById('novaCamVideo');
      const novaCamCanvas = document.getElementById('novaCamCanvas');
      const novaCamPhoto = document.getElementById('novaCamPhoto');
      const novaCamWarn = document.getElementById('novaCamWarn');
      const novaCamInfo = document.getElementById('novaCamInfo');
      const novaCamLiveActions = document.getElementById('novaCamLiveActions');
      const novaCamReviewActions = document.getElementById('novaCamReviewActions');

      let novaCamStream = null;
      let novaCamFacingMode = 'environment';
      let novaCamDataUrl = '';

      function novaCamShowWarn(msg) {
        if (!novaCamWarn) return;
        novaCamWarn.textContent = msg;
        novaCamWarn.classList.remove('d-none');
      }

      function novaCamHideWarn() {
        if (!novaCamWarn) return;
        novaCamWarn.textContent = '';
        novaCamWarn.classList.add('d-none');
      }

      function novaCamShowInfo() {
        novaCamInfo?.classList.remove('d-none');
      }

      function novaCamHideInfo() {
        novaCamInfo?.classList.add('d-none');
      }

      function novaCamSetLive() {
        novaCamDataUrl = '';
        if (novaCamPhoto) {
          novaCamPhoto.src = '';
          novaCamPhoto.style.display = 'none';
        }
        if (novaCamVideo) {
          novaCamVideo.style.display = 'block';
          novaCamVideo.classList.remove('d-none');
        }
        novaCamReviewActions?.classList.add('d-none');
        novaCamReviewActions?.classList.remove('d-flex');
        novaCamLiveActions?.classList.remove('d-none');
        novaCamLiveActions?.classList.add('d-flex');
      }

      function novaCamSetReview(dataUrl) {
        novaCamDataUrl = dataUrl || '';
        if (novaCamPhoto) {
          novaCamPhoto.src = novaCamDataUrl;
          novaCamPhoto.style.display = 'block';
        }
        if (novaCamVideo) {
          novaCamVideo.style.display = 'none';
          novaCamVideo.classList.add('d-none');
        }
        novaCamLiveActions?.classList.add('d-none');
        novaCamLiveActions?.classList.remove('d-flex');
        novaCamReviewActions?.classList.remove('d-none');
        novaCamReviewActions?.classList.add('d-flex');
      }

      async function novaCamStop() {
        try {
          if (novaCamStream) novaCamStream.getTracks().forEach(track => track.stop());
        } catch (e) {}
        novaCamStream = null;
        if (novaCamVideo) novaCamVideo.srcObject = null;
      }

      async function novaCamPermissionState() {
        try {
          if (navigator.permissions?.query) {
            const permission = await navigator.permissions.query({
              name: 'camera'
            });
            return permission.state;
          }
        } catch (e) {}
        return 'unknown';
      }

      async function novaCamStart() {
        novaCamHideWarn();
        await novaCamStop();

        if (!navigator.mediaDevices?.getUserMedia) {
          novaCamShowWarn('Seu navegador não suporta acesso à câmera.');
          novaCamShowInfo();
          return false;
        }

        const state = await novaCamPermissionState();
        if (state === 'granted') novaCamHideInfo();
        else novaCamShowInfo();

        if (state === 'denied') {
          novaCamShowWarn('Permissão de câmera bloqueada. Libere a câmera nas configurações do navegador e tente novamente.');
          return false;
        }

        try {
          novaCamStream = await navigator.mediaDevices.getUserMedia({
            video: {
              facingMode: {
                ideal: novaCamFacingMode
              }
            },
            audio: false
          });

          if (!novaCamVideo) return false;
          novaCamVideo.srcObject = novaCamStream;
          await novaCamVideo.play();
          novaCamHideInfo();
          return true;
        } catch (err) {
          console.error(err);
          if (err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError') {
            novaCamShowWarn('Permissão negada. Clique em Permitir no navegador ou libere a câmera nas configurações do site.');
          } else if (err?.name === 'NotFoundError') {
            novaCamShowWarn('Nenhuma câmera foi encontrada no dispositivo.');
          } else if (err?.name === 'NotReadableError') {
            novaCamShowWarn('A câmera está sendo usada por outro aplicativo. Feche o aplicativo e tente novamente.');
          } else {
            novaCamShowWarn('Não foi possível acessar a câmera. Verifique as permissões do navegador e do dispositivo.');
          }
          novaCamShowInfo();
          return false;
        }
      }

      btnNovaSolCamera?.addEventListener('click', async () => {
        if (!bsCameraNovaSol) return;
        bsCameraNovaSol.show();
        novaCamSetLive();
        await novaCamStart();
      });

      btnNovaSolAlternarCam?.addEventListener('click', async () => {
        novaCamFacingMode = novaCamFacingMode === 'environment' ? 'user' : 'environment';
        novaCamSetLive();
        await novaCamStart();
      });

      btnNovaSolTirarFoto?.addEventListener('click', () => {
        if (!novaCamVideo || !novaCamCanvas || !novaCamVideo.videoWidth || !novaCamVideo.videoHeight) {
          novaCamShowWarn('Câmera ainda não está pronta. Aguarde um instante e tente novamente.');
          return;
        }

        const width = novaCamVideo.videoWidth;
        const height = novaCamVideo.videoHeight;
        const maxSide = 1600;
        const scale = Math.min(1, maxSide / Math.max(width, height));
        const targetWidth = Math.round(width * scale);
        const targetHeight = Math.round(height * scale);

        novaCamCanvas.width = targetWidth;
        novaCamCanvas.height = targetHeight;

        const ctx = novaCamCanvas.getContext('2d', {
          willReadFrequently: true
        });
        if (!ctx) {
          novaCamShowWarn('Não foi possível capturar a imagem da câmera neste navegador.');
          return;
        }

        if (novaCamFacingMode === 'user') {
          ctx.save();
          ctx.translate(targetWidth, 0);
          ctx.scale(-1, 1);
          ctx.drawImage(novaCamVideo, 0, 0, targetWidth, targetHeight);
          ctx.restore();
        } else {
          ctx.drawImage(novaCamVideo, 0, 0, targetWidth, targetHeight);
        }

        novaCamSetReview(novaCamCanvas.toDataURL('image/jpeg', 0.88));
      });

      btnNovaSolTirarOutra?.addEventListener('click', async () => {
        novaCamSetLive();
        if (!novaCamStream) {
          await novaCamStart();
        } else {
          try {
            await novaCamVideo?.play();
          } catch (e) {}
        }
      });

      btnNovaSolUsarFoto?.addEventListener('click', () => {
        if (!novaCamDataUrl) return;
        applyNovaSolFoto(novaCamDataUrl, 'image/jpeg');
        bsCameraNovaSol?.hide();
      });

      modalCameraNovaSolEl?.addEventListener('shown.bs.modal', () => {
        const backdrops = document.querySelectorAll('.modal-backdrop.show');
        const lastBackdrop = backdrops[backdrops.length - 1];
        if (lastBackdrop) lastBackdrop.style.zIndex = '1085';
      });

      modalCameraNovaSolEl?.addEventListener('hidden.bs.modal', async () => {
        await novaCamStop();
        novaCamHideWarn();
        novaCamSetLive();
        if (modalNovaSolEl?.classList.contains('show')) {
          document.body.classList.add('modal-open');
        }
      });

      document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('.btnDetalhes');
        if (btn) {
          const tr = btn.closest('tr');
          if (tr) currentPessoaId = tr.dataset.id;
        }
      });

      btnNovaSol?.addEventListener('click', () => {
        if (!currentPessoaId) {
          alert('Nenhum beneficiário selecionado.');
          return;
        }
        document.getElementById('novaSol_pid').value = currentPessoaId;
        document.getElementById('novaSol_ajuda').value = '';
        document.getElementById('novaSol_resumo').value = '';
        resetNovaSolFoto();
        bsNovaSol.show();
      });

      document.getElementById('btnSalvarSol')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnSalvarSol');
        const pid = document.getElementById('novaSol_pid').value;
        const aid = document.getElementById('novaSol_ajuda').value;
        const resu = document.getElementById('novaSol_resumo').value;

        atualizarDataHora();
        const dataSolic = document.getElementById('data_solicitacao').value;

        if (!aid || !resu) {
          alert('Preencha todos os campos!');
          return;
        }

        const fd = new FormData();
        fd.append('solicitante_id', pid);
        fd.append('ajuda_tipo_id', aid);
        fd.append('resumo_caso', resu);
        fd.append('data_solicitacao', dataSolic);
        if (novaSolFotoFile) {
          fd.append('foto_solicitacao', novaSolFotoFile, novaSolFotoFile.name);
        }

        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
          const res = await fetch('dados/novaSolicitacao.php', {
            method: 'POST',
            body: fd
          });
          const j = await res.json();

          if (j.ok) {
            alert('Solicitação criada com sucesso!');
            bsNovaSol.hide();
            location.reload();
          } else {
            alert('Erro: ' + (j.msg || 'Desconhecido'));
          }
        } catch (e) {
          alert('Erro de conexão ao salvar.');
          console.error(e);
        }

        btn.disabled = false;
        btn.textContent = 'Salvar Solicitação';
      });

    })();
  </script>

  <script src="assets/js/main.js"></script>
</body>

</html>
