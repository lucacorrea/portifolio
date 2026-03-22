<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

function pdo_conn(): PDO {
  $con1 = __DIR__ . '/../assets/php/conexao.php';
  if (file_exists($con1)) {
    require_once $con1;
    if (function_exists('db')) {
      $pdo = db();
    } elseif (isset($pdo) && $pdo instanceof PDO) {
      // $pdo provided by the included file
    } else {
      $pdo = null;
    }
    if ($pdo instanceof PDO) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }
  $con2 = __DIR__ . '/../assets/conexao.php';
  if (file_exists($con2)) {
    require_once $con2;
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }
  throw new RuntimeException('Conexão não encontrada.');
}

function norm_key(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8'); else $s = strtolower($s);
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
  if ($t !== false) $s = $t;
  $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s;
}

function rules(): array {
  return [
    ['nome' => 'CESTA BÁSICA', 'rx' => '/\b(cesta|alimento|rancho)\b/i'],
    ['nome' => 'ASSUNTO PARTICULAR', 'rx' => '/\b(assunto particular|motivo particular)\b/i'],
    ['nome' => 'ALUGUEL', 'rx' => '/\b(aluguel|aluga|alugado|alugada)\b/i'],
    ['nome' => 'LUZ', 'rx' => '/\b(luz|energia|eletric)\b/i'],
    ['nome' => 'REMÉDIO', 'rx' => '/\b(remedio|rem[eé]dio|medic|farm[aá]cia)\b/i'],
    ['nome' => 'EXAMES', 'rx' => '/\b(exame|ultrassom|raio[\s-]?x|tomografia|resson[aâ]ncia)\b/i'],
    ['nome' => 'COMBUSTÍVEL', 'rx' => '/\b(combust[ií]vel|gasolina|diesel)\b/i'],
    ['nome' => 'PASSAGEM', 'rx' => '/\b(passagem|passagens|viagem)\b/i'],
    ['nome' => 'AJUDA DE CUSTO', 'rx' => '/\b(ajuda de custo)\b/i'],
    ['nome' => 'DENTADURA', 'rx' => '/\b(dentadur|pr[oó]tese|dente)\b/i'],
    ['nome' => 'CIRURGIA', 'rx' => '/\b(cirurg|opera[cç][aã]o|procedimento)\b/i'],
    ['nome' => 'APARELHO DE AUDIÇÃO', 'rx' => '/\b(auditivo|audi[cç][aã]o|aparelho)\b/i'],
    ['nome' => 'EMPREGO', 'rx' => '/\b(emprego|trabalh|vaga|curricul|sine)\b/i'],
    ['nome' => 'TERRENO', 'rx' => '/\b(terreno|lote)\b/i'],
    ['nome' => 'REFORMA', 'rx' => '/\b(reforma|reformar)\b/i'],
    ['nome' => 'MATERIAL PARA CONSTRUÇÃO OU REFORMA', 'rx' => '/\b(material|cimento|tijolo|areia|seixo|telha|ferro|madeira|tinta)\b/i'],
    ['nome' => 'CASA', 'rx' => '/\b(constru|construir|fazer (uma )?casa)\b/i'],
    ['nome' => 'PONTO COMERCIAL', 'rx' => '/\b(ponto comercial)\b/i'],
  ];
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf = (string)($in['csrf'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok'=>false,'msg'=>'CSRF inválido.']); exit;
}

try {
  $pdo = pdo_conn();
  $pdo->beginTransaction();

  // ====== BACKUP (opcional, mas recomendado) ======
  // Descomente se quiser criar backup automático:
  // $pdo->exec("CREATE TABLE IF NOT EXISTS solicitantes_bkp_autofill AS SELECT * FROM solicitantes WHERE 1=0");
  // $pdo->exec("INSERT INTO solicitantes_bkp_autofill SELECT * FROM solicitantes");

  // ====== (1) Preenche por texto igual: resumo_caso ======
  $before = (int)$pdo->query("SELECT COUNT(*) FROM solicitantes WHERE ajuda_tipo_id IS NULL OR ajuda_tipo_id=0")->fetchColumn();

  $pdo->exec("
    UPDATE solicitantes s
    JOIN (
      SELECT
        LOWER(TRIM(REPLACE(REPLACE(resumo_caso, CHAR(13), ' '), CHAR(10), ' '))) AS k,
        MIN(ajuda_tipo_id) AS tipo
      FROM solicitantes
      WHERE ajuda_tipo_id IS NOT NULL AND ajuda_tipo_id<>0
        AND resumo_caso IS NOT NULL AND resumo_caso <> ''
      GROUP BY k
      HAVING COUNT(DISTINCT ajuda_tipo_id) = 1
    ) m ON LOWER(TRIM(REPLACE(REPLACE(s.resumo_caso, CHAR(13), ' '), CHAR(10), ' '))) = m.k
    SET s.ajuda_tipo_id = m.tipo
    WHERE (s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id=0)
  ");

  // ====== (1b) Preenche por texto igual: tipificacao ======
  $pdo->exec("
    UPDATE solicitantes s
    JOIN (
      SELECT
        LOWER(TRIM(REPLACE(REPLACE(tipificacao, CHAR(13), ' '), CHAR(10), ' '))) AS k,
        MIN(ajuda_tipo_id) AS tipo
      FROM solicitantes
      WHERE ajuda_tipo_id IS NOT NULL AND ajuda_tipo_id<>0
        AND tipificacao IS NOT NULL AND tipificacao <> ''
      GROUP BY k
      HAVING COUNT(DISTINCT ajuda_tipo_id) = 1
    ) m ON LOWER(TRIM(REPLACE(REPLACE(s.tipificacao, CHAR(13), ' '), CHAR(10), ' '))) = m.k
    SET s.ajuda_tipo_id = m.tipo
    WHERE (s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id=0)
  ");

  $mid = (int)$pdo->query("SELECT COUNT(*) FROM solicitantes WHERE ajuda_tipo_id IS NULL OR ajuda_tipo_id=0")->fetchColumn();
  $filled_exact = $before - $mid;

  // ====== (2) Regras “sem conflito” em PHP (só 1 match) ======
  $tipos = $pdo->query("SELECT id, nome FROM ajudas_tipos")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $tipoMap = [];
  foreach ($tipos as $t) $tipoMap[norm_key((string)$t['nome'])] = (int)$t['id'];

  $stmt = $pdo->query("
    SELECT id, tipificacao, resumo_caso
    FROM solicitantes
    WHERE ajuda_tipo_id IS NULL OR ajuda_tipo_id=0
  ");
  $cands = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // temp table para update em lote
  $pdo->exec("CREATE TEMPORARY TABLE tmp_fill (id BIGINT PRIMARY KEY, ajuda_tipo_id BIGINT NOT NULL)");

  $insert = $pdo->prepare("INSERT INTO tmp_fill (id, ajuda_tipo_id) VALUES (?, ?)");

  $filled_rules = 0;
  foreach ($cands as $c) {
    $text = trim((string)($c['tipificacao'] ?? '') . ' ' . (string)($c['resumo_caso'] ?? ''));
    $hits = [];

    foreach (rules() as $r) {
      if (preg_match($r['rx'], $text)) {
        $k = norm_key($r['nome']);
        if (isset($tipoMap[$k])) $hits[] = $tipoMap[$k];
      }
    }
    $hits = array_values(array_unique($hits));

    if (count($hits) === 1) {
      $insert->execute([(int)$c['id'], (int)$hits[0]]);
      $filled_rules++;
    }
  }

  $pdo->exec("
    UPDATE solicitantes s
    JOIN tmp_fill f ON f.id = s.id
    SET s.ajuda_tipo_id = f.ajuda_tipo_id
    WHERE (s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id=0)
  ");

  $remaining = (int)$pdo->query("SELECT COUNT(*) FROM solicitantes WHERE ajuda_tipo_id IS NULL OR ajuda_tipo_id=0")->fetchColumn();

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'filled_exact' => $filled_exact,
    'filled_rules' => $filled_rules,
    'remaining' => $remaining
  ]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
}
