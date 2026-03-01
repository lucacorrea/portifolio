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
   ESTOQUE (reposição ao concluir devolução)
   - Só mexe no estoque quando status = CONCLUIDO
   - Se mudar de CONCLUIDO -> ABERTO/CANCELADO, desfaz (subtrai)
========================================================= */

function extract_product_code(string $product): string
{
  $p = trim(str_replace(["\r", "\n", "\t"], ' ', $product));
  if ($p === '') return '';
  // Se for "CODIGO - Nome"
  if (strpos($p, ' - ') !== false) {
    $parts = explode(' - ', $p, 2);
    $c = trim($parts[0] ?? '');
    return $c;
  }
  // fallback: pega o primeiro "token"
  if (preg_match('/^([A-Za-z0-9._-]+)/', $p, $m)) return trim($m[1]);
  return '';
}

/**
 * Gera mapa de reposição de estoque:
 *   [codigo => qtd]
 * Só retorna algo se status = CONCLUIDO.
 *
 * TOTAL: usa venda_itens (pela venda_no).
 * PARCIAL: usa produto (string) + qtd.
 */
function devolucao_effect(PDO $pdo, array $dev): array
{
  $status = strtoupper(trim((string)($dev['status'] ?? '')));
  if ($status !== 'CONCLUIDO') return [];

  $type   = strtoupper(trim((string)($dev['type'] ?? $dev['tipo'] ?? 'TOTAL')));
  $saleNo = (int)($dev['saleNo'] ?? $dev['venda_no'] ?? 0);

  $effect = [];

  if ($type === 'TOTAL') {
    if ($saleNo <= 0) return []; // sem venda, não dá pra repor corretamente

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

  // PARCIAL
  $product = (string)($dev['product'] ?? $dev['produto'] ?? '');
  $qty     = (int)($dev['qty'] ?? $dev['qtd'] ?? 0);
  if ($qty <= 0) return [];

  $code = extract_product_code($product);
  if ($code === '') return []; // sem código, não repõe
  $effect[$code] = ($effect[$code] ?? 0) + $qty;
  return $effect;
}

/**
 * Aplica delta no estoque:
 * - delta pode ser positivo (repor) ou negativo (desfazer)
 * - nunca deixa estoque negativo
 * Retorna lista de códigos não encontrados.
 */
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

    $up->execute([
      ':delta' => $delta,
      ':codigo' => $code,
    ]);

    if ($up->rowCount() === 0) {
      $missing[] = $code;
    }
  }

  return $missing;
}

/** soma mapas: $a + $b (b pode ter negativos) */
function map_add(array $a, array $b): array
{
  foreach ($b as $k => $v) {
    $a[$k] = (int)($a[$k] ?? 0) + (int)$v;
    if ((int)$a[$k] === 0) unset($a[$k]);
  }
  return $a;
}

/* =========================================================
   PRODUTOS (fallback) - carrega 1x (para autocomplete quando não tiver venda)
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

    // ===== buscar vendas digitando =====
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

    // ===== itens da venda selecionada =====
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

    // ===== list devolucoes =====
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

    // ===== save devolucao (COM ESTOQUE) =====
    if ($ajax === 'save') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $id     = to_int($payload['id'] ?? 0);
      $saleNoRaw = trim((string)($payload['saleNo'] ?? ''));
      $saleNo = ($saleNoRaw !== '' && ctype_digit($saleNoRaw)) ? (int)$saleNoRaw : null;

      $customer = trim((string)($payload['customer'] ?? ''));
      $date = trim((string)($payload['date'] ?? ''));
      $time = trim((string)($payload['time'] ?? ''));

      if ($date === '') json_out(['ok' => false, 'msg' => 'Informe a data.'], 400);
      if ($time === '') json_out(['ok' => false, 'msg' => 'Informe a hora.'], 400);

      $type = strtoupper(trim((string)($payload['type'] ?? 'TOTAL')));
      if (!in_array($type, ['TOTAL', 'PARCIAL'], true)) $type = 'TOTAL';

      $product = trim((string)($payload['product'] ?? ''));
      $qty = to_int($payload['qty'] ?? 1, 1);
      $amount = (float)to_float($payload['amount'] ?? 0);

      if ($amount <= 0) json_out(['ok' => false, 'msg' => 'Informe um valor (R$) maior que zero.'], 400);

      $reason = strtoupper(trim((string)($payload['reason'] ?? 'OUTRO')));
      $allowReason = ['DEFEITO', 'TROCA', 'ARREPENDIMENTO', 'AVARIA_TRANSPORTE', 'OUTRO'];
      if (!in_array($reason, $allowReason, true)) $reason = 'OUTRO';

      $note = trim((string)($payload['note'] ?? ''));

      $status = strtoupper(trim((string)($payload['status'] ?? 'ABERTO')));
      $allowStatus = ['ABERTO', 'CONCLUIDO', 'CANCELADO'];
      if (!in_array($status, $allowStatus, true)) $status = 'ABERTO';

      // validações por tipo
      if ($type === 'TOTAL') {
        $product = '';
        $qty = 0;

        // se for CONCLUIDO, precisa de venda_no para repor estoque corretamente
        if ($status === 'CONCLUIDO' && (!$saleNo || $saleNo <= 0)) {
          json_out(['ok' => false, 'msg' => 'Para concluir uma devolução TOTAL, informe o nº da venda (para repor estoque).'], 400);
        }
      } else {
        if ($product === '') json_out(['ok' => false, 'msg' => 'Informe o produto para devolução parcial.'], 400);
        if ($qty < 1) json_out(['ok' => false, 'msg' => 'Informe a quantidade (mín. 1).'], 400);

        if ($status === 'CONCLUIDO') {
          $code = extract_product_code($product);
          if ($code === '') {
            json_out(['ok' => false, 'msg' => 'Para concluir devolução PARCIAL, informe o produto com código (ex: P0001 - Arroz).'], 400);
          }
        }
      }

      // ===== TRANSAÇÃO: salva devolução + ajusta estoque corretamente
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

        // efeitos (estoque) antigo e novo
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
          'product' => $product,
          'qty'    => ($type === 'PARCIAL' ? $qty : 0),
        ]);

        // delta = new - old
        $delta = $newEffect;
        $negOld = [];
        foreach ($oldEffect as $k => $v) $negOld[$k] = -1 * (int)$v;
        $delta = map_add($delta, $negOld);

        // aplica delta no estoque
        if ($delta) {
          $missing = apply_stock_delta($pdo, $delta);
        }

        // grava devolução
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
            ':produto'  => ($type === 'PARCIAL' ? $product : null),
            ':qtd'      => ($type === 'PARCIAL' ? $qty : null),
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
            ':produto'  => ($type === 'PARCIAL' ? $product : null),
            ':qtd'      => ($type === 'PARCIAL' ? $qty : null),
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

      // devolve registro
      $st = $pdo->prepare("SELECT * FROM devolucoes WHERE id=?");
      $st->execute([$id]);
      $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

      $msg = 'Devolução salva com sucesso!';
      if ($missing) {
        $msg .= ' (Atenção: produto(s) não encontrado(s) para repor estoque: ' . implode(', ', $missing) . ')';
      }

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
        'stock' => [
          'missing_codes' => $missing
        ]
      ]);
    }

    // ===== delete (se estava CONCLUIDO, desfaz estoque) =====
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

        // desfaz (delta negativo)
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

    // ===== import (não mexe em estoque; você pode concluir depois) =====
    if ($ajax === 'import') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido.'], 403);

      $items = $payload['items'] ?? null;
      if (!is_array($items) || !$items) json_out(['ok' => false, 'msg' => 'Nenhum item para importar.'], 400);

      $ins = $pdo->prepare("
        INSERT INTO devolucoes
          (venda_no, cliente, data, hora, tipo, produto, qtd, valor, motivo, obs, status)
        VALUES
          (:venda_no, :cliente, :data, :hora, :tipo, :produto, :qtd, :valor, :motivo, :obs, :status)
      ");

      $count = 0;
      foreach ($items as $x) {
        if (!is_array($x)) continue;

        $date = trim((string)($x['date'] ?? $x['data'] ?? ''));
        $time = trim((string)($x['time'] ?? $x['hora'] ?? ''));
        if ($date === '' || $time === '') continue;

        $type = strtoupper(trim((string)($x['type'] ?? $x['tipo'] ?? 'TOTAL')));
        if (!in_array($type, ['TOTAL', 'PARCIAL'], true)) $type = 'TOTAL';

        $amount = (float)to_float($x['amount'] ?? $x['valor'] ?? 0);
        if ($amount <= 0) continue;

        $saleNo = trim((string)($x['saleNo'] ?? $x['vendaNo'] ?? $x['venda_no'] ?? ''));
        $saleNo = ($saleNo !== '' && ctype_digit($saleNo)) ? (int)$saleNo : null;

        $customer = trim((string)($x['customer'] ?? $x['cliente'] ?? ''));
        $product = trim((string)($x['product'] ?? $x['produto'] ?? ''));
        $qty = to_int($x['qty'] ?? $x['qtd'] ?? 1, 1);

        if ($type === 'TOTAL') {
          $product = '';
          $qty = 0;
        } else {
          if ($product === '' || $qty < 1) continue;
        }

        $reason = strtoupper(trim((string)($x['reason'] ?? $x['motivo'] ?? 'OUTRO')));
        $allowReason = ['DEFEITO', 'TROCA', 'ARREPENDIMENTO', 'AVARIA_TRANSPORTE', 'OUTRO'];
        if (!in_array($reason, $allowReason, true)) $reason = 'OUTRO';

        $note = trim((string)($x['note'] ?? $x['obs'] ?? ''));
        $status = strtoupper(trim((string)($x['status'] ?? 'ABERTO')));
        $allowStatus = ['ABERTO', 'CONCLUIDO', 'CANCELADO'];
        if (!in_array($status, $allowStatus, true)) $status = 'ABERTO';

        // Import não mexe estoque (pra evitar bagunça). Você conclui depois no sistema.
        if ($status === 'CONCLUIDO') $status = 'ABERTO';

        $ins->execute([
          ':venda_no' => $saleNo,
          ':cliente'  => ($customer !== '' ? $customer : null),
          ':data'     => $date,
          ':hora'     => $time,
          ':tipo'     => $type,
          ':produto'  => ($type === 'PARCIAL' ? $product : null),
          ':qtd'      => ($type === 'PARCIAL' ? $qty : null),
          ':valor'    => $amount,
          ':motivo'   => $reason,
          ':obs'      => ($note !== '' ? $note : null),
          ':status'   => $status,
        ]);
        $count++;
      }

      json_out(['ok' => true, 'msg' => "Importação concluída: {$count} item(ns)."]);
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
    .profile-box .dropdown-menu {
      width: max-content;
      min-width: 260px;
      max-width: calc(100vw - 24px)
    }

    .profile-box .dropdown-menu .author-info {
      width: max-content;
      max-width: 100%;
      display: flex !important;
      align-items: center;
      gap: 10px
    }

    .profile-box .dropdown-menu .author-info .content {
      min-width: 0;
      max-width: 100%
    }

    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important
    }

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px
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

    /* Itens da venda */
    .sale-box {
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 14px;
      background: rgba(248, 250, 252, .7);
      padding: 10px 12px;
      max-height: 180px;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
    }

    .sale-row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      padding: 6px 0;
      border-bottom: 1px dashed rgba(148, 163, 184, .35);
      font-size: 12px;
    }

    .sale-row:last-child {
      border-bottom: none
    }

    .sale-row .left {
      min-width: 0
    }

    .sale-row .left .nm {
      font-weight: 900;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 320px
    }

    .sale-row .left .cd {
      color: #64748b;
      font-size: 12px
    }

    .sale-row .right {
      white-space: nowrap;
      text-align: right
    }

    .sale-mini {
      font-size: 12px;
      color: #64748b;
      margin-top: 6px;
      display: flex;
      justify-content: space-between;
      gap: 10px
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

  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="dashboard.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item"><a href="dashboard.php"><span class="text">Dashboard</span></a></li>
        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="pedidos.php">Pedidos</a></li>
            <li><a href="vendas.php">Vendas</a></li>
            <li><a href="devolucoes.php" class="active">Devoluções</a></li>
          </ul>
        </li>
        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="text">Estoque</span>
          </a>
          <ul id="ddmenu_estoque" class="collapse dropdown-nav">
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="inventario.php">Inventário</a></li>
            <li><a href="entradas.php">Entradas</a></li>
            <li><a href="saidas.php">Saídas</a></li>
            <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
          </ul>
        </li>
        <li class="nav-item"><a href="relatorios.php"><span class="text">Relatórios</span></a></li>
        <span class="divider">
          <hr />
        </span>
        <li class="nav-item"><a href="suporte.php"><span class="text">Suporte</span></a></li>
      </ul>
    </nav>
  </aside>

  <div class="overlay"></div>

  <main class="main-wrapper">
    <header class="header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-5 col-md-5 col-6">
            <div class="header-left d-flex align-items-center">
              <div class="menu-toggle-btn mr-15">
                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex">
                <form action="#">
                  <input type="text" placeholder="Buscar devolução..." id="qGlobal" />
                  <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
            </div>
          </div>
          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right">
              <div class="profile-box ml-15">
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="profile-info">
                    <div class="info">
                      <div class="image"><img src="assets/images/profile/profile-image.png" alt="perfil" /></div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                  <li><a href="perfil.php"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.php"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
                  <li><a href="logout.php"><i class="lni lni-exit"></i> Sair</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Devoluções</h2>
                <div class="muted">Registro e controle de devoluções • <b>F2</b> salvar | <b>F4</b> focar na busca</div>
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

        <!-- Lançamento col-6 + Resumo col-6 -->
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
                  <div class="muted mt-1">Selecione na lista para puxar cliente e itens.</div>
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
                  <label class="form-label">Produto (se parcial)</label>
                  <div class="search-wrap">
                    <input class="form-control compact" id="dProduto" placeholder="Nome / Código do produto" autocomplete="off" />
                    <div class="suggest" id="prodSuggest"></div>
                  </div>
                  <div class="muted mt-1">Se selecionou a venda, sugere apenas itens da venda.</div>
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

                <div class="mb-3" id="saleItemsWrap" style="display:none;">
                  <label class="form-label">Itens da Venda Selecionada</label>
                  <div class="sale-box" id="saleItemsBox"></div>
                  <div class="sale-mini">
                    <div id="saleMiniLeft">—</div>
                    <div id="saleMiniRight">—</div>
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
                  <div class="muted mt-1">* Estoque só é reposto quando <b>CONCLUÍDO</b>.</div>
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
                <div class="muted mt-2">* Somatório baseado no campo “Valor (R$)” das devoluções.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabela col-12 -->
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
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnExport" type="button">
                    <i class="lni lni-download me-1"></i> Exportar JSON
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnImport" type="button">
                    <i class="lni lni-upload me-1"></i> Importar JSON
                  </button>
                  <input type="file" id="fileImport" accept="application/json" style="display:none;" />
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
                        <th style="min-width:160px;">Tipo</th>
                        <th style="min-width:240px;">Produto</th>
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

    <footer class="footer">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 order-last order-md-first">
            <div class="copyright text-center text-md-start">
              <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const AJAX_URL = "devolucoes.php";
    const PRODUCTS = <?= json_encode($PRODUTOS_CACHE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
    let SALE_SELECTED = null;
    let SALE_ITEMS = [];
    let LAST_SALES = [];
    let LAST_PROD = [];
    let saleTimer = null,
      prodTimer = null;
    let saleAbort = null;

    const qGlobal = document.getElementById("qGlobal");
    const btnNova = document.getElementById("btnNova");
    const btnSalvar = document.getElementById("btnSalvar");
    const btnLimpar = document.getElementById("btnLimpar");

    const dId = document.getElementById("dId");
    const dVendaNo = document.getElementById("dVendaNo");
    const saleSuggest = document.getElementById("saleSuggest");
    const dCliente = document.getElementById("dCliente");
    const dData = document.getElementById("dData");
    const dHora = document.getElementById("dHora");

    const dProduto = document.getElementById("dProduto");
    const prodSuggest = document.getElementById("prodSuggest");
    const dQtd = document.getElementById("dQtd");
    const dValor = document.getElementById("dValor");

    const dMotivo = document.getElementById("dMotivo");
    const dObs = document.getElementById("dObs");
    const dStatus = document.getElementById("dStatus");

    const chipTotal = document.getElementById("chipTotal");
    const chipParcial = document.getElementById("chipParcial");
    const formMode = document.getElementById("formMode");

    const saleItemsWrap = document.getElementById("saleItemsWrap");
    const saleItemsBox = document.getElementById("saleItemsBox");
    const saleMiniLeft = document.getElementById("saleMiniLeft");
    const saleMiniRight = document.getElementById("saleMiniRight");

    const qDev = document.getElementById("qDev");
    const fStatus = document.getElementById("fStatus");
    const tbodyDev = document.getElementById("tbodyDev");
    const hintNone = document.getElementById("hintNone");

    const tAberto = document.getElementById("tAberto");
    const tConcl = document.getElementById("tConcl");
    const tCancel = document.getElementById("tCancel");
    const tGeral = document.getElementById("tGeral");

    const btnExport = document.getElementById("btnExport");
    const btnImport = document.getElementById("btnImport");
    const fileImport = document.getElementById("fileImport");

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

    function hideProdSuggest() {
      prodSuggest.style.display = "none";
      prodSuggest.innerHTML = "";
    }

    function hideSaleItems() {
      saleItemsWrap.style.display = "none";
      saleItemsBox.innerHTML = "";
      saleMiniLeft.textContent = "—";
      saleMiniRight.textContent = "—";
    }

    function clearSaleSelection() {
      SALE_SELECTED = null;
      SALE_ITEMS = [];
      LAST_SALES = [];
      hideSaleSuggest();
      hideSaleItems();
    }

    function applyTotalFromSaleIfAny() {
      if (!SALE_SELECTED) return;
      const sumQty = SALE_ITEMS.reduce((a, x) => a + Number(x.qty || 0), 0);
      dProduto.value = `VENDA #${SALE_SELECTED.id} (TOTAL - ${SALE_ITEMS.length} itens)`;
      dQtd.value = sumQty > 0 ? sumQty : 1;

      const curVal = moneyToNumber(dValor.value);
      if (curVal <= 0 && Number(SALE_SELECTED.total || 0) > 0) {
        dValor.value = Number(SALE_SELECTED.total || 0).toFixed(2).replace(".", ",");
      }
    }

    function setType(type) {
      TYPE = type;
      const isTotal = type === "TOTAL";
      chipTotal.classList.toggle("active", isTotal);
      chipParcial.classList.toggle("active", !isTotal);

      dProduto.disabled = isTotal;
      dQtd.disabled = isTotal;

      if (isTotal) {
        hideProdSuggest();
        applyTotalFromSaleIfAny();
      }
    }

    function resetForm() {
      dId.value = "";
      dVendaNo.value = "";
      dCliente.value = "";
      dData.value = nowISODate();
      dHora.value = nowISOTime();
      TYPE = "TOTAL";
      setType("TOTAL");
      dProduto.value = "";
      dQtd.value = 1;
      dValor.value = "0,00";
      dMotivo.value = "OUTRO";
      dObs.value = "";
      dStatus.value = "ABERTO";
      clearSaleSelection();
      setFormMode("NEW");
    }

    // ===== buscar vendas =====
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

    dVendaNo.addEventListener("input", () => {
      if (SALE_SELECTED && String(SALE_SELECTED.id) !== dVendaNo.value.trim()) {
        clearSaleSelection();
      }
      refreshSaleDebounced();
    });
    dVendaNo.addEventListener("focus", refreshSaleDebounced);

    async function loadSaleItems(saleId) {
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=itensVenda&id=${saleId}`);
        SALE_ITEMS = (r.items || []).map(x => ({
          id: Number(x.id),
          code: String(x.code || ""),
          name: String(x.name || ""),
          qty: Number(x.qty || 0),
          unit: String(x.unit || ""),
          price: Number(x.price || 0),
          subtotal: Number(x.subtotal || 0),
        }));
      } catch {
        SALE_ITEMS = [];
      }
    }

    function renderSaleItems() {
      if (!SALE_SELECTED || !SALE_ITEMS.length) {
        hideSaleItems();
        return;
      }
      saleItemsWrap.style.display = "block";
      saleItemsBox.innerHTML = SALE_ITEMS.map(it => `
      <div class="sale-row">
        <div class="left">
          <div class="nm">${safeText(it.name)}</div>
          <div class="cd">${safeText(it.code)} • ${Number(it.qty||0)} ${safeText(it.unit||"")}</div>
        </div>
        <div class="right">
          <div style="font-weight:900;color:#0f172a;">${numberToMoney(it.subtotal||0)}</div>
          <div class="muted">Unit: ${numberToMoney(it.price||0)}</div>
        </div>
      </div>
    `).join("");

      const sumQty = SALE_ITEMS.reduce((a, x) => a + Number(x.qty || 0), 0);
      const sumSub = SALE_ITEMS.reduce((a, x) => a + Number(x.subtotal || 0), 0);
      saleMiniLeft.textContent = `Itens: ${SALE_ITEMS.length} • Qtd total: ${sumQty}`;
      saleMiniRight.textContent = `Subtotal itens: ${numberToMoney(sumSub)}`;
    }

    saleSuggest.addEventListener("click", async (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const id = Number(it.getAttribute("data-id") || 0);
      const v = LAST_SALES.find(x => Number(x.id) === id);
      if (!v) return;

      SALE_SELECTED = v;
      dVendaNo.value = String(v.id);
      dCliente.value = String(v.customer || "Consumidor Final");
      hideSaleSuggest();

      await loadSaleItems(id);
      renderSaleItems();

      if (TYPE === "TOTAL") applyTotalFromSaleIfAny();
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest("#saleSuggest") && !e.target.closest("#dVendaNo")) hideSaleSuggest();
      if (!e.target.closest("#prodSuggest") && !e.target.closest("#dProduto")) hideProdSuggest();
    });

    // ===== produto suggest (parcial) =====
    function onlyDigits(s) {
      return String(s || "").replace(/\D+/g, "");
    }

    function filterProductsLocal(q) {
      const s = String(q || "").trim().toLowerCase();
      if (!s) return [];
      const sDig = onlyDigits(s);

      const source = (SALE_SELECTED && SALE_ITEMS.length) ? SALE_ITEMS : PRODUCTS;

      const out = [];
      for (const p of source) {
        const code = String(p.code || "").toLowerCase();
        const name = String(p.name || "").toLowerCase();
        const cdig = onlyDigits(code);
        if (name.includes(s) || code.includes(s) || (sDig && cdig.includes(sDig))) out.push(p);
      }
      out.sort((a, b) => String(a.name || "").localeCompare(String(b.name || "")));
      return out.slice(0, 20);
    }

    function showProdSuggest(list) {
      if (!list.length) {
        hideProdSuggest();
        return;
      }
      prodSuggest.innerHTML = list.map(p => `
      <div class="it" data-code="${safeText(p.code)}">
        <div style="min-width:0">
          <div class="t">${safeText(p.name)}</div>
          <div class="s">${safeText(p.code)}${(p.qty!=null && p.qty>0) ? ` • Qtd venda: ${Number(p.qty)}` : ""}</div>
        </div>
        <div class="s">${(p.subtotal!=null && p.subtotal>0) ? numberToMoney(p.subtotal) : "OK"}</div>
      </div>
    `).join("");
      prodSuggest.style.display = "block";
      prodSuggest.scrollTop = 0;
    }

    function refreshProdDebounced() {
      clearTimeout(prodTimer);
      prodTimer = setTimeout(() => {
        if (TYPE !== "PARCIAL") {
          hideProdSuggest();
          return;
        }
        LAST_PROD = filterProductsLocal(dProduto.value);
        showProdSuggest(LAST_PROD);
      }, 120);
    }

    dProduto.addEventListener("input", refreshProdDebounced);
    dProduto.addEventListener("focus", refreshProdDebounced);

    prodSuggest.addEventListener("click", (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const code = it.getAttribute("data-code") || "";
      const p = LAST_PROD.find(x => String(x.code || "") === code);
      if (!p) return;

      dProduto.value = `${p.code} - ${p.name}`;
      if (SALE_SELECTED && SALE_ITEMS.length) {
        if (Number(p.qty || 0) > 0) dQtd.value = Number(p.qty);
        if (Number(p.subtotal || 0) > 0) dValor.value = Number(p.subtotal).toFixed(2).replace(".", ",");
      }
      hideProdSuggest();
    });

    // ===== listagem devoluções =====
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
      const q = (qDev.value || qGlobal.value || "").toLowerCase().trim();
      const st = fStatus.value;
      return DEV.filter(x => {
        if (st && x.status !== st) return false;
        if (!q) return true;
        const blob = [
          x.id, x.saleNo ?? "", x.customer ?? "", x.type ?? "", x.product ?? "",
          x.reason ?? "", x.note ?? "", x.status ?? "",
          fmtBRDateTime(x.date, x.time),
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
        const prod = (x.type === "PARCIAL") ? (x.product ? safeText(x.product) : "—") : "—";
        const qty = (x.type === "PARCIAL") ? Number(x.qty || 1) : "—";
        const motivo = motivoLabel(x.reason);
        const valor = numberToMoney(x.amount);

        tbodyDev.insertAdjacentHTML("beforeend", `
        <tr data-id="${Number(x.id)}">
          <td><span class="mini">${Number(x.id)}</span></td>
          <td>${safeText(dt)}</td>
          <td>${sale}</td>
          <td>${cust}</td>
          <td><span class="pill ${x.type==="PARCIAL"?"warn":"ok"}">${x.type==="PARCIAL"?"PARCIAL":"TOTAL"}</span></td>
          <td>${prod}</td>
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
      const date = String(dData.value || "").trim();
      const time = String(dHora.value || "").trim();
      if (!date) return {
        ok: false,
        msg: "Informe a data."
      };
      if (!time) return {
        ok: false,
        msg: "Informe a hora."
      };

      const amt = moneyToNumber(dValor.value);
      if (amt <= 0) return {
        ok: false,
        msg: "Informe um valor (R$) maior que zero."
      };

      if (TYPE === "PARCIAL") {
        const prod = String(dProduto.value || "").trim();
        if (!prod) return {
          ok: false,
          msg: "Informe o produto para devolução parcial."
        };
        const q = Number(dQtd.value || 0);
        if (!q || q < 1) return {
          ok: false,
          msg: "Informe a quantidade (mín. 1)."
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
        product: (TYPE === "PARCIAL") ? String(dProduto.value || "").trim() : "",
        qty: (TYPE === "PARCIAL") ? Number(dQtd.value || 1) : 0,
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

      TYPE = (x.type === "PARCIAL") ? "PARCIAL" : "TOTAL";
      setType(TYPE);

      dProduto.value = x.product || "";
      dQtd.value = x.qty || 1;
      dValor.value = Number(x.amount || 0).toFixed(2).replace(".", ",");
      dMotivo.value = x.reason || "OUTRO";
      dObs.value = x.note || "";
      dStatus.value = x.status || "ABERTO";

      clearSaleSelection();
      setFormMode("EDIT");
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    async function deleteDev(id) {
      if (!confirm(`Excluir devolução #${id}? (Se estava CONCLUÍDO, o estoque será desfeito.)`)) return;
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

    function exportJson() {
      const data = {
        exported_at: new Date().toISOString(),
        items: DEV
      };
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: "application/json;charset=utf-8"
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "devolucoes.json";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    async function importJson(file) {
      const reader = new FileReader();
      reader.onload = async () => {
        try {
          const obj = JSON.parse(reader.result);
          const items = Array.isArray(obj) ? obj : (obj.items || []);
          if (!Array.isArray(items) || !items.length) throw new Error("JSON sem itens.");
          const r = await fetchJSON(`${AJAX_URL}?ajax=import`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              items
            })
          });
          alert(r.msg || "Importação concluída!");
          await loadAll();
        } catch (e) {
          alert("Falha ao importar: " + (e.message || e));
        }
      };
      reader.readAsText(file);
    }

    btnNova.addEventListener("click", resetForm);
    btnSalvar.addEventListener("click", saveDev);
    btnLimpar.addEventListener("click", resetForm);

    chipTotal.addEventListener("click", () => setType("TOTAL"));
    chipParcial.addEventListener("click", () => setType("PARCIAL"));

    qDev.addEventListener("input", render);
    fStatus.addEventListener("change", render);
    qGlobal.addEventListener("input", () => {
      qDev.value = qGlobal.value;
      render();
    });

    tbodyDev.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = Number(tr.getAttribute("data-id") || 0);
      if (!id) return;
      if (e.target.closest(".btnEdit")) return editDev(id);
      if (e.target.closest(".btnDel")) return deleteDev(id);
    });

    btnExport.addEventListener("click", exportJson);
    btnImport.addEventListener("click", () => fileImport.click());
    fileImport.addEventListener("change", () => {
      const f = fileImport.files && fileImport.files[0];
      if (f) importJson(f);
      fileImport.value = "";
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "F2") {
        e.preventDefault();
        saveDev();
      }
      if (e.key === "F4") {
        e.preventDefault();
        qDev.focus();
      }
      if (e.key === "Escape") {
        hideSaleSuggest();
        hideProdSuggest();
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