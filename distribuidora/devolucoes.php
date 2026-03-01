<?php

declare(strict_types=1);

@ini_set('display_errors', '0');
@error_reporting(0);
if (function_exists('ob_start')) {
  @ob_start();
}

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/devolucoes/_helpers.php';

$pdo = db();

function json_out(array $data, int $code = 200): void
{
  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function table_missing(Throwable $e): bool
{
  $m = strtolower($e->getMessage());
  return str_contains($m, "doesn't exist") || str_contains($m, "unknown table") || str_contains($m, "not found");
}

/* =========================================================
   RESUMO PRODUTOS (TOTAL)
   - grava "produto" e "qtd" no TOTAL para aparecer na listagem
========================================================= */
function build_total_summary(PDO $pdo, int $saleNo): array
{
  if ($saleNo <= 0) return ['produto' => '', 'qtd' => 0, 'items' => 0];

  $st = $pdo->prepare("SELECT nome, qtd FROM venda_itens WHERE venda_id = ? ORDER BY id ASC");
  $st->execute([$saleNo]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $sumQty = 0;
  $items = count($rows);

  if (!$rows) return ['produto' => "Venda #{$saleNo}", 'qtd' => 0, 'items' => 0];

  $parts = [];
  foreach ($rows as $r) {
    $nm = trim((string)($r['nome'] ?? ''));
    $q  = (int)($r['qtd'] ?? 0);
    if ($nm === '' || $q <= 0) continue;
    $sumQty += $q;
    $parts[] = "{$nm} x{$q}";
  }

  // monta string limitada (VARCHAR 255)
  $max = 240;
  $txt = '';
  $used = 0;
  $shown = 0;

  foreach ($parts as $p) {
    $add = ($txt === '' ? $p : '; ' . $p);
    if (strlen($txt . $add) > $max) break;
    $txt .= $add;
    $shown++;
  }

  $rest = max(0, count($parts) - $shown);
  if ($rest > 0) {
    $suf = " (+{$rest})";
    if (strlen($txt . $suf) <= 255) $txt .= $suf;
  }

  return [
    'produto' => $txt !== '' ? $txt : "Venda #{$saleNo}",
    'qtd' => $sumQty,
    'items' => $items
  ];
}

/* =========================================================
   ESTOQUE (reposição ao concluir devolução)
   - Só mexe no estoque quando status = CONCLUIDO
   - Se mudar de CONCLUIDO -> ABERTO/CANCELADO, desfaz
========================================================= */
function extract_product_code(string $product): string
{
  $p = trim(str_replace(["\r", "\n", "\t"], ' ', $product));
  if ($p === '') return '';
  if (strpos($p, ' - ') !== false) {
    $parts = explode(' - ', $p, 2);
    return trim((string)($parts[0] ?? ''));
  }
  if (preg_match('/^([A-Za-z0-9._-]+)/', $p, $m)) return trim($m[1]);
  return '';
}
function devolucao_effect(PDO $pdo, array $dev): array
{
  $status = strtoupper(trim((string)($dev['status'] ?? '')));
  if ($status !== 'CONCLUIDO') return [];

  $type   = strtoupper(trim((string)($dev['type'] ?? $dev['tipo'] ?? 'TOTAL')));
  $saleNo = (int)($dev['saleNo'] ?? $dev['venda_no'] ?? 0);
  $effect = [];

  if ($type === 'TOTAL') {
    if ($saleNo <= 0) return [];
    $st = $pdo->prepare("SELECT codigo, qtd FROM venda_itens WHERE venda_id = ? ORDER BY id ASC");
    $st->execute([$saleNo]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
      $code = trim((string)($r['codigo'] ?? ''));
      $qty  = (int)($r['qtd'] ?? 0);
      if ($code === '' || $qty <= 0) continue;
      $effect[$code] = ($effect[$code] ?? 0) + $qty;
    }
    return $effect;
  }

  $product = (string)($dev['product'] ?? $dev['produto'] ?? '');
  $qty     = (int)($dev['qty'] ?? $dev['qtd'] ?? 0);
  if ($qty <= 0) return [];
  $code = extract_product_code($product);
  if ($code === '') return [];
  $effect[$code] = ($effect[$code] ?? 0) + $qty;
  return $effect;
}
function apply_stock_delta(PDO $pdo, array $deltaMap): array
{
  $missing = [];
  $up = $pdo->prepare("
    UPDATE produtos
    SET estoque = GREATEST(0, estoque + :delta)
    WHERE codigo = :codigo
    LIMIT 1
  ");
  foreach ($deltaMap as $code => $delta) {
    $code = trim((string)$code);
    $delta = (int)$delta;
    if ($code === '' || $delta === 0) continue;
    $up->execute([':delta' => $delta, ':codigo' => $code]);
    if ($up->rowCount() === 0) $missing[] = $code;
  }
  return $missing;
}
function map_add(array $a, array $b): array
{
  foreach ($b as $k => $v) {
    $a[$k] = (int)($a[$k] ?? 0) + (int)$v;
    if ((int)$a[$k] === 0) unset($a[$k]);
  }
  return $a;
}

/* =========================================================
   PRODUTOS (fallback) - carrega 1x
========================================================= */
$PRODUTOS_CACHE = [];
try {
  $stP = $pdo->query("
    SELECT id, codigo, nome, status
    FROM produtos
    WHERE (status IS NULL OR status = '' OR UPPER(TRIM(status))='ATIVO')
    ORDER BY nome ASC
    LIMIT 6000
  ");
  $rowsP = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $PRODUTOS_CACHE = array_map(static function (array $r): array {
    return [
      'id'   => (int)($r['id'] ?? 0),
      'code' => (string)($r['codigo'] ?? ''),
      'name' => (string)($r['nome'] ?? ''),
    ];
  }, $rowsP);
} catch (Throwable $e) {
  $PRODUTOS_CACHE = [];
}

/* =========================================================
   AJAX
========================================================= */
if (isset($_GET['ajax'])) {
  $ajax = (string)($_GET['ajax'] ?? '');

  try {

    if ($ajax === 'buscarVendas') {
      $q = trim((string)($_GET['q'] ?? ''));
      if ($q === '') json_out(['ok' => true, 'items' => []]);

      if (preg_match('/^\d+$/', $q)) {
        $st = $pdo->prepare("
          SELECT id, created_at, cliente, total, canal
          FROM vendas
          WHERE CAST(id AS CHAR) LIKE :start
          ORDER BY id DESC
          LIMIT 20
        ");
        $st->execute([':start' => $q . '%']);
      } else {
        $st = $pdo->prepare("
          SELECT id, created_at, cliente, total, canal
          FROM vendas
          WHERE cliente LIKE :like
          ORDER BY id DESC
          LIMIT 20
        ");
        $st->execute([':like' => '%' . $q . '%']);
      }

      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $items = array_map(static function (array $r): array {
        return [
          'id' => (int)($r['id'] ?? 0),
          'date' => (string)($r['created_at'] ?? ''),
          'customer' => (string)($r['cliente'] ?? ''),
          'total' => (float)($r['total'] ?? 0),
          'canal' => (string)($r['canal'] ?? 'PRESENCIAL'),
        ];
      }, $rows);

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($ajax === 'itensVenda') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => true, 'items' => []]);

      $st = $pdo->prepare("
        SELECT id, codigo, nome, qtd, preco_unit, subtotal, unidade
        FROM venda_itens
        WHERE venda_id = ?
        ORDER BY id ASC
      ");
      $st->execute([$id]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $items = array_map(static function (array $r): array {
        return [
          'id' => (int)($r['id'] ?? 0),
          'code' => (string)($r['codigo'] ?? ''),
          'name' => (string)($r['nome'] ?? ''),
          'qty' => (int)($r['qtd'] ?? 0),
          'unit' => (string)($r['unidade'] ?? ''),
          'price' => (float)($r['preco_unit'] ?? 0),
          'subtotal' => (float)($r['subtotal'] ?? 0),
        ];
      }, $rows);

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($ajax === 'list') {
      $st = $pdo->query("SELECT * FROM devolucoes ORDER BY id DESC LIMIT 1500");
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $items = array_map(static function (array $r): array {
        return [
          'id'      => (int)($r['id'] ?? 0),
          'saleNo'  => ($r['venda_no'] !== null ? (int)$r['venda_no'] : null),
          'customer' => (string)($r['cliente'] ?? ''),
          'date'    => (string)($r['data'] ?? ''),
          'time'    => (string)($r['hora'] ?? ''),
          'type'    => (string)($r['tipo'] ?? 'TOTAL'),
          'product' => (string)($r['produto'] ?? ''),
          'qty'     => ($r['qtd'] !== null ? (int)$r['qtd'] : null),
          'amount'  => (float)($r['valor'] ?? 0),
          'reason'  => (string)($r['motivo'] ?? 'OUTRO'),
          'note'    => (string)($r['obs'] ?? ''),
          'status'  => (string)($r['status'] ?? 'ABERTO'),
          'created_at' => (string)($r['created_at'] ?? ''),
        ];
      }, $rows);

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($ajax === 'save') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $id = to_int($payload['id'] ?? 0);

      $saleNoRaw = trim((string)($payload['saleNo'] ?? ''));
      $saleNo = ($saleNoRaw !== '' && ctype_digit($saleNoRaw)) ? (int)$saleNoRaw : null;

      $customer = trim((string)($payload['customer'] ?? ''));
      $date = trim((string)($payload['date'] ?? ''));
      $time = trim((string)($payload['time'] ?? ''));
      if ($date === '') json_out(['ok' => false, 'msg' => 'Informe a data.'], 400);
      if ($time === '') json_out(['ok' => false, 'msg' => 'Informe a hora.'], 400);

      $type = strtoupper(trim((string)($payload['type'] ?? 'TOTAL')));
      if (!in_array($type, ['TOTAL', 'PARCIAL'], true)) $type = 'TOTAL';

      $product_in = trim((string)($payload['product'] ?? ''));
      $qty_in = to_int($payload['qty'] ?? 0, 0);

      $amount = (float)to_float($payload['amount'] ?? 0);
      if ($amount <= 0) json_out(['ok' => false, 'msg' => 'Informe um valor (R$) maior que zero.'], 400);

      $reason = strtoupper(trim((string)($payload['reason'] ?? 'OUTRO')));
      $allowReason = ['DEFEITO', 'TROCA', 'ARREPENDIMENTO', 'AVARIA_TRANSPORTE', 'OUTRO'];
      if (!in_array($reason, $allowReason, true)) $reason = 'OUTRO';

      $note = trim((string)($payload['note'] ?? ''));

      $status = strtoupper(trim((string)($payload['status'] ?? 'ABERTO')));
      $allowStatus = ['ABERTO', 'CONCLUIDO', 'CANCELADO'];
      if (!in_array($status, $allowStatus, true)) $status = 'ABERTO';

      // ✅ aqui é a mudança: TOTAL também guarda produto e qtd (resumo da venda)
      $db_produto = null;
      $db_qtd = null;

      if ($type === 'TOTAL') {
        if ($saleNo && $saleNo > 0) {
          $sum = build_total_summary($pdo, (int)$saleNo);
          $db_produto = $sum['produto'] ?: ("Venda #" . $saleNo);
          $db_qtd = (int)($sum['qtd'] ?? 0);
        } else {
          // sem venda vinculada: usa o que tiver (se tiver), senão null
          $db_produto = ($product_in !== '' ? $product_in : null);
          $db_qtd = ($qty_in > 0 ? $qty_in : null);
        }

        if ($status === 'CONCLUIDO' && (!$saleNo || $saleNo <= 0)) {
          json_out(['ok' => false, 'msg' => 'Para concluir devolução TOTAL e repor estoque, informe o nº da venda.'], 400);
        }
      } else {
        // PARCIAL
        if ($product_in === '') json_out(['ok' => false, 'msg' => 'Informe o produto para devolução parcial.'], 400);
        $q = to_int($payload['qty'] ?? 1, 1);
        if ($q < 1) json_out(['ok' => false, 'msg' => 'Informe a quantidade (mín. 1).'], 400);

        if ($status === 'CONCLUIDO') {
          $code = extract_product_code($product_in);
          if ($code === '') {
            json_out(['ok' => false, 'msg' => 'Para concluir PARCIAL, informe com código (ex: P0001 - Arroz).'], 400);
          }
        }

        $db_produto = $product_in;
        $db_qtd = $q;
      }

      // ===== TRANSAÇÃO (salva + estoque delta)
      $pdo->beginTransaction();
      $missing = [];

      try {
        $old = null;
        if ($id > 0) {
          $stOld = $pdo->prepare("SELECT * FROM devolucoes WHERE id = ? FOR UPDATE");
          $stOld->execute([$id]);
          $old = $stOld->fetch(PDO::FETCH_ASSOC);
          if (!$old) {
            $pdo->rollBack();
            json_out(['ok' => false, 'msg' => 'Devolução não encontrada para editar.'], 404);
          }
        }

        $oldEffect = $old ? devolucao_effect($pdo, [
          'status' => (string)($old['status'] ?? ''),
          'tipo'   => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd'    => (int)($old['qtd'] ?? 0),
        ]) : [];

        $newEffect = devolucao_effect($pdo, [
          'status' => $status,
          'type'   => $type,
          'saleNo' => (int)($saleNo ?? 0),
          'product' => (string)$db_produto,
          'qty'    => (int)($db_qtd ?? 0),
        ]);

        $delta = $newEffect;
        $negOld = [];
        foreach ($oldEffect as $k => $v) $negOld[$k] = -1 * (int)$v;
        $delta = map_add($delta, $negOld);

        if ($delta) $missing = apply_stock_delta($pdo, $delta);

        if ($id > 0) {
          $st = $pdo->prepare("
            UPDATE devolucoes
            SET venda_no=:venda_no, cliente=:cliente, data=:data, hora=:hora,
                tipo=:tipo, produto=:produto, qtd=:qtd, valor=:valor,
                motivo=:motivo, obs=:obs, status=:status
            WHERE id=:id
          ");
          $st->execute([
            ':venda_no' => $saleNo,
            ':cliente'  => ($customer !== '' ? $customer : null),
            ':data'     => $date,
            ':hora'     => $time,
            ':tipo'     => $type,
            ':produto'  => $db_produto,
            ':qtd'      => $db_qtd,
            ':valor'    => $amount,
            ':motivo'   => $reason,
            ':obs'      => ($note !== '' ? $note : null),
            ':status'   => $status,
            ':id'       => $id,
          ]);
        } else {
          $st = $pdo->prepare("
            INSERT INTO devolucoes
              (venda_no, cliente, data, hora, tipo, produto, qtd, valor, motivo, obs, status)
            VALUES
              (:venda_no, :cliente, :data, :hora, :tipo, :produto, :qtd, :valor, :motivo, :obs, :status)
          ");
          $st->execute([
            ':venda_no' => $saleNo,
            ':cliente'  => ($customer !== '' ? $customer : null),
            ':data'     => $date,
            ':hora'     => $time,
            ':tipo'     => $type,
            ':produto'  => $db_produto,
            ':qtd'      => $db_qtd,
            ':valor'    => $amount,
            ':motivo'   => $reason,
            ':obs'      => ($note !== '' ? $note : null),
            ':status'   => $status,
          ]);
          $id = (int)$pdo->lastInsertId();
        }

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }

      $st = $pdo->prepare("SELECT * FROM devolucoes WHERE id=?");
      $st->execute([$id]);
      $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

      $msg = 'Devolução salva com sucesso!';
      if ($missing) $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';

      json_out([
        'ok' => true,
        'msg' => $msg,
        'item' => [
          'id' => (int)($r['id'] ?? $id),
          'saleNo' => ($r['venda_no'] !== null ? (int)$r['venda_no'] : null),
          'customer' => (string)($r['cliente'] ?? ''),
          'date' => (string)($r['data'] ?? $date),
          'time' => (string)($r['hora'] ?? $time),
          'type' => (string)($r['tipo'] ?? $type),
          'product' => (string)($r['produto'] ?? ''),
          'qty' => ($r['qtd'] !== null ? (int)$r['qtd'] : null),
          'amount' => (float)($r['valor'] ?? $amount),
          'reason' => (string)($r['motivo'] ?? $reason),
          'note' => (string)($r['obs'] ?? ''),
          'status' => (string)($r['status'] ?? $status),
          'created_at' => (string)($r['created_at'] ?? ''),
        ],
        'stock' => ['missing_codes' => $missing]
      ]);
    }

    if ($ajax === 'del') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido.'], 403);

      $id = to_int($payload['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 400);

      $pdo->beginTransaction();
      $missing = [];

      try {
        $stOld = $pdo->prepare("SELECT * FROM devolucoes WHERE id = ? FOR UPDATE");
        $stOld->execute([$id]);
        $old = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
          $pdo->rollBack();
          json_out(['ok' => false, 'msg' => 'Devolução não encontrada.'], 404);
        }

        $oldEffect = devolucao_effect($pdo, [
          'status' => (string)($old['status'] ?? ''),
          'tipo'   => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd'    => (int)($old['qtd'] ?? 0),
        ]);

        if ($oldEffect) {
          $neg = [];
          foreach ($oldEffect as $k => $v) $neg[$k] = -1 * (int)$v;
          $missing = apply_stock_delta($pdo, $neg);
        }

        $st = $pdo->prepare("DELETE FROM devolucoes WHERE id=?");
        $st->execute([$id]);

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }

      $msg = 'Devolução excluída.';
      if ($missing) $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';
      json_out(['ok' => true, 'msg' => $msg]);
    }

    json_out(['ok' => false, 'msg' => 'Ação ajax inválida.'], 400);
  } catch (Throwable $e) {
    if (table_missing($e)) {
      json_out(['ok' => false, 'msg' => "Tabela necessária não encontrada (devolucoes/vendas/venda_itens/produtos). Rode os SQLs."], 500);
    }
    json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
  }
}

/* =========================================================
   HTML NORMAL
========================================================= */
$csrf = csrf_token();
$flash = flash_pop();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= e($csrf) ?>">

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Devoluções</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px
    }

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important
    }

    .cardx {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden
    }

    .cardx .head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap
    }

    .cardx .body {
      padding: 14px
    }

    .muted {
      font-size: 12px;
      color: #64748b
    }

    .pill {
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, .25);
      font-weight: 900;
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(248, 250, 252, .7)
    }

    .pill.ok {
      border-color: rgba(34, 197, 94, .25);
      background: rgba(240, 253, 244, .9);
      color: #166534
    }

    .pill.warn {
      border-color: rgba(245, 158, 11, .28);
      background: rgba(255, 251, 235, .9);
      color: #92400e
    }

    .chip-toggle {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .chip {
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 999px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 900;
      font-size: 12px;
      user-select: none;
      background: #fff
    }

    .chip.active {
      background: rgba(239, 246, 255, .75);
      border-color: rgba(37, 99, 235, .55);
      outline: 2px solid rgba(37, 99, 235, .25)
    }

    .reason-box {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 10px 12px;
      background: rgba(248, 250, 252, .7)
    }

    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center
    }

    .toolbar .grow {
      flex: 1 1 260px;
      min-width: 240px
    }

    .toolbar .w180 {
      min-width: 180px
    }

    .table td,
    .table th {
      vertical-align: middle
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch
    }

    #tbDev {
      width: 100%;
      min-width: 1080px
    }

    #tbDev th,
    #tbDev td {
      white-space: nowrap !important
    }

    .mini {
      font-size: 12px;
      color: #475569;
      font-weight: 800
    }

    .money {
      font-weight: 1000;
      color: #0b5ed7
    }

    .badge-soft {
      font-weight: 1000;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px
    }

    .b-open {
      background: rgba(255, 251, 235, .95);
      color: #92400e;
      border: 1px solid rgba(245, 158, 11, .25)
    }

    .b-done {
      background: rgba(240, 253, 244, .95);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, .25)
    }

    .b-cancel {
      background: rgba(254, 242, 242, .95);
      color: #991b1b;
      border: 1px solid rgba(239, 68, 68, .25)
    }

    .box-tot {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      background: #fff;
      padding: 12px
    }

    .tot-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: #334155;
      margin-bottom: 8px;
      font-weight: 900
    }

    .tot-hr {
      height: 1px;
      background: rgba(148, 163, 184, .22);
      margin: 10px 0
    }

    .grand {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 10px;
      margin-top: 4px
    }

    .grand .lbl {
      font-weight: 1000;
      color: #0f172a;
      font-size: 16px
    }

    .grand .val {
      font-weight: 1000;
      color: #0b5ed7;
      font-size: 26px;
      letter-spacing: .2px
    }

    .search-wrap {
      position: relative
    }

    .suggest {
      position: absolute;
      z-index: 9999;
      left: 0;
      right: 0;
      top: calc(100% + 6px);
      background: #fff;
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, .10);
      max-height: 280px;
      overflow: auto;
      display: none
    }

    .suggest .it {
      padding: 10px 12px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: 10px
    }

    .suggest .it:hover {
      background: rgba(241, 245, 249, .9)
    }

    .suggest .t {
      font-weight: 900;
      font-size: 12px;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .suggest .s {
      font-size: 12px;
      color: #64748b;
      white-space: nowrap
    }

    @media(max-width:991.98px) {
      #tbDev {
        min-width: 980px
      }

      .grand .val {
        font-size: 22px
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <!-- sidebar + header continuam do seu template -->
  <!-- (mantive enxuto aqui, porque você já tem o template. Se quiser eu colo a sidebar completa igual a sua.) -->

  <main class="main-wrapper">
    <section class="section">
      <div class="container-fluid">

        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Devoluções</h2>
                <div class="muted">Registro e controle de devoluções • <b>F2</b> salvar</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNova" type="button">
                <i class="lni lni-plus me-1"></i> Nova devolução
              </button>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-6">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-package me-1"></i> Lançamento</div>
                <span class="pill warn" id="formMode"><i class="lni lni-pencil"></i> NOVO</span>
              </div>
              <div class="body">
                <input type="hidden" id="dId" />

                <div class="mb-3">
                  <label class="form-label">Venda (Nº) / Cliente</label>
                  <div class="search-wrap">
                    <input class="form-control compact" id="dVendaNo" placeholder="Digite nº da venda ou nome do cliente..." autocomplete="off" />
                    <div class="suggest" id="saleSuggest"></div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Cliente</label>
                  <input class="form-control compact" id="dCliente" placeholder="CPF ou Nome (opcional)" />
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Data</label>
                    <input class="form-control compact" id="dData" type="date" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Hora</label>
                    <input class="form-control compact" id="dHora" type="time" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Tipo</label>
                  <div class="chip-toggle">
                    <div class="chip active" id="chipTotal">Devolução Total</div>
                    <div class="chip" id="chipParcial">Parcial</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Produto</label>
                  <div class="search-wrap">
                    <input class="form-control compact" id="dProduto" placeholder="Nome / Código do produto" autocomplete="off" />
                    <div class="suggest" id="prodSuggest"></div>
                  </div>
                  <div class="muted mt-1">No TOTAL, aqui vai um resumo da venda.</div>
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Qtd</label>
                    <input class="form-control compact" id="dQtd" type="number" min="1" value="1" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Valor (R$)</label>
                    <input class="form-control compact" id="dValor" placeholder="0,00" value="0,00" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Motivo</label>
                  <div class="reason-box">
                    <select class="form-select compact mb-2" id="dMotivo">
                      <option value="DEFEITO">Defeito</option>
                      <option value="TROCA">Troca</option>
                      <option value="ARREPENDIMENTO">Arrependimento</option>
                      <option value="AVARIA_TRANSPORTE">Avaria no Transporte</option>
                      <option value="OUTRO" selected>Outro</option>
                    </select>
                    <input class="form-control compact" id="dObs" placeholder="Observação (opcional)" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select class="form-select compact" id="dStatus">
                    <option value="ABERTO" selected>Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>
                  <div class="muted mt-1">* Estoque repõe somente em <b>CONCLUÍDO</b>.</div>
                </div>

                <div class="d-grid gap-2">
                  <button class="main-btn primary-btn btn-hover btn-compact" id="btnSalvar" type="button">
                    <i class="lni lni-save me-1"></i> Salvar (F2)
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                    <i class="lni lni-eraser me-1"></i> Limpar
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="cardx h-100">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-stats-up me-1"></i> Resumo</div>
              </div>
              <div class="body">
                <div class="box-tot">
                  <div class="tot-row"><span>Total em aberto</span><span class="money" id="tAberto">R$ 0,00</span></div>
                  <div class="tot-row"><span>Total concluído</span><span class="money" id="tConcl">R$ 0,00</span></div>
                  <div class="tot-row"><span>Total cancelado</span><span class="money" id="tCancel">R$ 0,00</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <span class="lbl">TOTAL (geral)</span>
                    <span class="val" id="tGeral">R$ 0,00</span>
                  </div>
                </div>
                <div class="muted mt-2">* Somatório pelo campo “Valor”.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-30">
          <div class="col-12">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-list me-1"></i> Listagem</div>
                <div class="toolbar" style="width:100%;">
                  <input class="form-control compact grow" id="qDev" placeholder="Buscar: venda, cliente, produto, motivo..." />
                  <select class="form-select compact w180" id="fStatus">
                    <option value="">Todos</option>
                    <option value="ABERTO">Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>
                </div>
              </div>

              <div class="body">
                <div class="table-responsive">
                  <table class="table text-nowrap" id="tbDev">
                    <thead>
                      <tr>
                        <th style="min-width:80px;">ID</th>
                        <th style="min-width:140px;">Data/Hora</th>
                        <th style="min-width:120px;">Venda</th>
                        <th style="min-width:200px;">Cliente</th>
                        <th style="min-width:120px;">Tipo</th>
                        <th style="min-width:340px;">Produto</th>
                        <th style="min-width:90px;" class="text-center">Qtd</th>
                        <th style="min-width:140px;" class="text-end">Valor</th>
                        <th style="min-width:180px;">Motivo</th>
                        <th style="min-width:150px;" class="text-center">Status</th>
                        <th style="min-width:160px;" class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyDev"></tbody>
                  </table>
                </div>
                <div class="muted mt-2" id="hintNone" style="display:none;">Nenhuma devolução encontrada.</div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const AJAX_URL = "devolucoes.php";

    function safeText(s) {
      return String(s ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    }

    function moneyToNumber(txt) {
      let s = String(txt ?? "").trim();
      if (!s) return 0;
      s = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
      const n = Number(s);
      return isNaN(n) ? 0 : n;
    }

    function numberToMoney(n) {
      const v = Number(n || 0);
      return "R$ " + v.toFixed(2).replace(".", ",");
    }

    function fetchJSON(url, opts = {}) {
      return fetch(url, opts).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || data.ok === false) throw new Error(data.msg || "Erro na requisição.");
        return data;
      });
    }

    function pad2(n) {
      return String(n).padStart(2, "0");
    }

    function nowISODate() {
      const d = new Date();
      return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    }

    function nowISOTime() {
      const d = new Date();
      return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }

    function fmtBRDateTime(dateISO, timeISO) {
      if (!dateISO) return "";
      const [y, m, d] = String(dateISO).split("-");
      const t = (timeISO || "00:00").slice(0, 5);
      return `${d}/${m}/${y} ${t}`;
    }

    let DEV = [];
    let TYPE = "TOTAL";
    let LAST_SALES = [];
    let saleTimer = null;
    let saleAbort = null;

    const dId = document.getElementById("dId");
    const dVendaNo = document.getElementById("dVendaNo");
    const saleSuggest = document.getElementById("saleSuggest");
    const dCliente = document.getElementById("dCliente");
    const dData = document.getElementById("dData");
    const dHora = document.getElementById("dHora");
    const dProduto = document.getElementById("dProduto");
    const dQtd = document.getElementById("dQtd");
    const dValor = document.getElementById("dValor");
    const dMotivo = document.getElementById("dMotivo");
    const dObs = document.getElementById("dObs");
    const dStatus = document.getElementById("dStatus");
    const chipTotal = document.getElementById("chipTotal");
    const chipParcial = document.getElementById("chipParcial");
    const formMode = document.getElementById("formMode");
    const btnNova = document.getElementById("btnNova");
    const btnSalvar = document.getElementById("btnSalvar");
    const btnLimpar = document.getElementById("btnLimpar");
    const qDev = document.getElementById("qDev");
    const fStatus = document.getElementById("fStatus");
    const tbodyDev = document.getElementById("tbodyDev");
    const hintNone = document.getElementById("hintNone");
    const tAberto = document.getElementById("tAberto");
    const tConcl = document.getElementById("tConcl");
    const tCancel = document.getElementById("tCancel");
    const tGeral = document.getElementById("tGeral");

    function setFormMode(mode) {
      if (mode === "EDIT") {
        formMode.className = "pill ok";
        formMode.innerHTML = `<i class="lni lni-checkmark-circle"></i> EDITANDO`;
      } else {
        formMode.className = "pill warn";
        formMode.innerHTML = `<i class="lni lni-pencil"></i> NOVO`;
      }
    }

    function hideSaleSuggest() {
      saleSuggest.style.display = "none";
      saleSuggest.innerHTML = "";
    }

    function setType(type) {
      TYPE = type;
      const isTotal = type === "TOTAL";
      chipTotal.classList.toggle("active", isTotal);
      chipParcial.classList.toggle("active", !isTotal);
      // no TOTAL você pode deixar preenchido (resumo), então não vou limpar
    }

    function resetForm() {
      dId.value = "";
      dVendaNo.value = "";
      dCliente.value = "";
      dData.value = nowISODate();
      dHora.value = nowISOTime();
      setType("TOTAL");
      dProduto.value = "";
      dQtd.value = 1;
      dValor.value = "0,00";
      dMotivo.value = "OUTRO";
      dObs.value = "";
      dStatus.value = "ABERTO";
      setFormMode("NEW");
      hideSaleSuggest();
    }

    // buscar vendas
    function showSaleSuggest(list) {
      if (!list.length) {
        hideSaleSuggest();
        return;
      }
      saleSuggest.innerHTML = list.map(v => `
      <div class="it" data-id="${Number(v.id)}">
        <div style="min-width:0">
          <div class="t">#${Number(v.id)} • ${safeText(v.customer||"Consumidor Final")}</div>
          <div class="s">${safeText(v.date||"")} • ${safeText(v.canal||"")} • ${numberToMoney(v.total||0)}</div>
        </div>
        <div class="s">Selecionar</div>
      </div>
    `).join("");
      saleSuggest.style.display = "block";
      saleSuggest.scrollTop = 0;
    }

    async function searchSales(q) {
      const s = String(q || "").trim();
      if (!s) return [];
      if (saleAbort) saleAbort.abort();
      saleAbort = new AbortController();
      const url = `${AJAX_URL}?ajax=buscarVendas&q=` + encodeURIComponent(s);
      const r = await fetch(url, {
        signal: saleAbort.signal
      });
      const data = await r.json().catch(() => ({}));
      if (!r.ok || data.ok === false) return [];
      return (data.items || []);
    }

    function refreshSaleDebounced() {
      clearTimeout(saleTimer);
      saleTimer = setTimeout(async () => {
        const q = dVendaNo.value.trim();
        if (!q) {
          LAST_SALES = [];
          hideSaleSuggest();
          return;
        }
        LAST_SALES = await searchSales(q);
        showSaleSuggest(LAST_SALES);
      }, 140);
    }

    dVendaNo.addEventListener("input", refreshSaleDebounced);
    dVendaNo.addEventListener("focus", refreshSaleDebounced);

    saleSuggest.addEventListener("click", (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const id = Number(it.getAttribute("data-id") || 0);
      const v = LAST_SALES.find(x => Number(x.id) === id);
      if (!v) return;

      dVendaNo.value = String(v.id);
      dCliente.value = String(v.customer || "Consumidor Final");

      // ✅ aqui não precisa montar resumo no JS — o PHP monta e salva no banco
      // mas para o form ficar bonito, a gente já mostra algo:
      dProduto.value = `Venda #${v.id} (TOTAL)`;
      dQtd.value = 1;
      if (moneyToNumber(dValor.value) <= 0 && Number(v.total || 0) > 0) {
        dValor.value = Number(v.total || 0).toFixed(2).replace(".", ",");
      }

      hideSaleSuggest();
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest("#saleSuggest") && !e.target.closest("#dVendaNo")) hideSaleSuggest();
    });

    function badgeStatus(s) {
      if (s === "CONCLUIDO") return `<span class="badge-soft b-done">CONCLUÍDO</span>`;
      if (s === "CANCELADO") return `<span class="badge-soft b-cancel">CANCELADO</span>`;
      return `<span class="badge-soft b-open">EM ABERTO</span>`;
    }

    function motivoLabel(m) {
      const map = {
        "DEFEITO": "Defeito",
        "TROCA": "Troca",
        "ARREPENDIMENTO": "Arrependimento",
        "AVARIA_TRANSPORTE": "Avaria Transporte",
        "OUTRO": "Outro"
      };
      return map[m] || m || "-";
    }

    function getFiltered() {
      const q = (qDev.value || "").toLowerCase().trim();
      const st = fStatus.value;
      return DEV.filter(x => {
        if (st && x.status !== st) return false;
        if (!q) return true;
        const blob = [
          x.id, x.saleNo ?? "", x.customer ?? "", x.type ?? "", x.product ?? "",
          x.reason ?? "", x.note ?? "", x.status ?? "",
          numberToMoney(x.amount),
          String(x.qty ?? "")
        ].join(" ").toLowerCase();
        return blob.includes(q);
      });
    }

    function recalcTotals() {
      let aberto = 0,
        concl = 0,
        cancel = 0,
        geral = 0;
      DEV.forEach(x => {
        const v = Number(x.amount || 0);
        geral += v;
        if (x.status === "CONCLUIDO") concl += v;
        else if (x.status === "CANCELADO") cancel += v;
        else aberto += v;
      });
      tAberto.textContent = numberToMoney(aberto);
      tConcl.textContent = numberToMoney(concl);
      tCancel.textContent = numberToMoney(cancel);
      tGeral.textContent = numberToMoney(geral);
    }

    function render() {
      const rows = getFiltered();
      tbodyDev.innerHTML = "";
      hintNone.style.display = rows.length ? "none" : "block";

      rows.forEach(x => {
        const dt = fmtBRDateTime(x.date, x.time);
        const sale = x.saleNo ? `#${safeText(x.saleNo)}` : "—";
        const cust = x.customer ? safeText(x.customer) : "Consumidor Final";

        // ✅ agora TOTAL também mostra
        const prod = x.product ? safeText(x.product) : "—";
        const qty = (x.qty != null && Number(x.qty) > 0) ? Number(x.qty) : "—";

        const motivo = motivoLabel(x.reason);
        const valor = numberToMoney(x.amount);

        tbodyDev.insertAdjacentHTML("beforeend", `
        <tr data-id="${Number(x.id)}">
          <td><span class="mini">${Number(x.id)}</span></td>
          <td>${safeText(dt)}</td>
          <td>${sale}</td>
          <td>${cust}</td>
          <td><span class="pill ${x.type==="PARCIAL"?"warn":"ok"}">${x.type==="PARCIAL"?"PARCIAL":"TOTAL"}</span></td>
          <td style="max-width:420px;white-space:normal;">${prod}</td>
          <td class="text-center">${qty}</td>
          <td class="text-end"><span class="money">${valor}</span></td>
          <td>${safeText(motivo)}${x.note?`<div class="muted" style="max-width:260px;white-space:normal;">${safeText(x.note)}</div>`:""}</td>
          <td class="text-center">${badgeStatus(x.status)}</td>
          <td class="text-center">
            <button class="main-btn light-btn btn-hover btn-compact icon-btn btnEdit" type="button" title="Editar"><i class="lni lni-pencil"></i></button>
            <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnDel" type="button" title="Excluir"><i class="lni lni-trash-can"></i></button>
          </td>
        </tr>
      `);
      });

      recalcTotals();
    }

    async function loadAll() {
      const r = await fetchJSON(`${AJAX_URL}?ajax=list`);
      DEV = (r.items || []).map(x => ({
        id: Number(x.id),
        saleNo: (x.saleNo == null ? null : Number(x.saleNo)),
        customer: String(x.customer || ""),
        date: String(x.date || ""),
        time: String(x.time || ""),
        type: String(x.type || "TOTAL").toUpperCase(),
        product: String(x.product || ""),
        qty: (x.qty == null ? null : Number(x.qty)),
        amount: Number(x.amount || 0),
        reason: String(x.reason || "OUTRO").toUpperCase(),
        note: String(x.note || ""),
        status: String(x.status || "ABERTO").toUpperCase(),
      }));
      render();
    }

    function validateForm() {
      if (!String(dData.value || "").trim()) return {
        ok: false,
        msg: "Informe a data."
      };
      if (!String(dHora.value || "").trim()) return {
        ok: false,
        msg: "Informe a hora."
      };
      const amt = moneyToNumber(dValor.value);
      if (amt <= 0) return {
        ok: false,
        msg: "Informe um valor (R$) maior que zero."
      };

      if (TYPE === "PARCIAL") {
        if (!String(dProduto.value || "").trim()) return {
          ok: false,
          msg: "Informe o produto (parcial)."
        };
        const q = Number(dQtd.value || 0);
        if (!q || q < 1) return {
          ok: false,
          msg: "Qtd inválida."
        };
      }
      return {
        ok: true
      };
    }

    async function saveDev() {
      const v = validateForm();
      if (!v.ok) {
        alert(v.msg);
        return;
      }

      const payload = {
        csrf_token: CSRF,
        id: dId.value ? Number(dId.value) : 0,
        saleNo: String(dVendaNo.value || "").trim(),
        customer: String(dCliente.value || "").trim(),
        date: String(dData.value || "").trim(),
        time: String(dHora.value || "").trim(),
        type: TYPE,
        product: String(dProduto.value || "").trim(), // ✅ manda sempre (TOTAL também)
        qty: Number(dQtd.value || 0), // ✅ manda sempre (TOTAL também)
        amount: moneyToNumber(dValor.value),
        reason: String(dMotivo.value || "OUTRO"),
        note: String(dObs.value || "").trim(),
        status: String(dStatus.value || "ABERTO"),
      };

      btnSalvar.disabled = true;
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=save`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(payload)
        });

        const item = r.item;
        const norm = {
          id: Number(item.id),
          saleNo: (item.saleNo == null ? null : Number(item.saleNo)),
          customer: String(item.customer || ""),
          date: String(item.date || ""),
          time: String(item.time || ""),
          type: String(item.type || "TOTAL").toUpperCase(),
          product: String(item.product || ""),
          qty: (item.qty == null ? null : Number(item.qty)),
          amount: Number(item.amount || 0),
          reason: String(item.reason || "OUTRO").toUpperCase(),
          note: String(item.note || ""),
          status: String(item.status || "ABERTO").toUpperCase(),
        };

        const idx = DEV.findIndex(x => Number(x.id) === Number(norm.id));
        if (idx >= 0) DEV[idx] = norm;
        else DEV.unshift(norm);

        render();
        resetForm();
        alert(r.msg || "Devolução salva!");
      } catch (e) {
        alert(e.message || "Erro ao salvar.");
      } finally {
        btnSalvar.disabled = false;
      }
    }

    function editDev(id) {
      const x = DEV.find(d => Number(d.id) === Number(id));
      if (!x) return;

      dId.value = String(x.id);
      dVendaNo.value = x.saleNo ? String(x.saleNo) : "";
      dCliente.value = x.customer || "";
      dData.value = x.date || nowISODate();
      dHora.value = (x.time || nowISOTime()).slice(0, 5);

      setType(x.type === "PARCIAL" ? "PARCIAL" : "TOTAL");
      dProduto.value = x.product || "";
      dQtd.value = x.qty || 1;

      dValor.value = Number(x.amount || 0).toFixed(2).replace(".", ",");
      dMotivo.value = x.reason || "OUTRO";
      dObs.value = x.note || "";
      dStatus.value = x.status || "ABERTO";

      setFormMode("EDIT");
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    async function deleteDev(id) {
      if (!confirm(`Excluir devolução #${id}?`)) return;
      try {
        await fetchJSON(`${AJAX_URL}?ajax=del`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            csrf_token: CSRF,
            id: Number(id)
          })
        });
        DEV = DEV.filter(d => Number(d.id) !== Number(id));
        render();
        resetForm();
      } catch (e) {
        alert(e.message || "Erro ao excluir.");
      }
    }

    btnNova.addEventListener("click", resetForm);
    btnSalvar.addEventListener("click", saveDev);
    btnLimpar.addEventListener("click", resetForm);
    chipTotal.addEventListener("click", () => setType("TOTAL"));
    chipParcial.addEventListener("click", () => setType("PARCIAL"));
    qDev.addEventListener("input", render);
    fStatus.addEventListener("change", render);

    tbodyDev.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = Number(tr.getAttribute("data-id") || 0);
      if (!id) return;
      if (e.target.closest(".btnEdit")) return editDev(id);
      if (e.target.closest(".btnDel")) return deleteDev(id);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "F2") {
        e.preventDefault();
        saveDev();
      }
    });

    async function init() {
      resetForm();
      await loadAll();
    }
    init();
  </script>
</body>

</html>