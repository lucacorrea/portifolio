<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../_helpers.php';

if (!is_post()) redirect('../../vendas.php');
csrf_validate_or_die('csrf');

$pdo = db();

$id       = to_int(post('id', 0), 0);
$data     = to_str(post('data', ''));
$pedido   = to_str(post('pedido', ''));
$cliente  = to_str(post('cliente', ''));
$canal    = strtoupper(to_str(post('canal', 'PRESENCIAL')));
$pagamento = strtoupper(to_str(post('pagamento', 'DINHEIRO')));
$obs      = to_str(post('obs', ''));

$produtoIds = $_POST['produto_id'] ?? [];
$qtds       = $_POST['qtd'] ?? [];
$precos     = $_POST['preco'] ?? [];

if ($data === '' || $cliente === '') {
    flash_set('danger', 'Informe a data e o cliente.');
    redirect('../../vendas.php');
}

$validCanal = in_array($canal, ['PRESENCIAL', 'DELIVERY'], true) ? $canal : 'PRESENCIAL';

$items = [];
for ($i = 0; $i < max(count($produtoIds), count($qtds), count($precos)); $i++) {
    $pid = isset($produtoIds[$i]) ? to_int($produtoIds[$i], 0) : 0;
    $qtd = isset($qtds[$i]) ? to_int($qtds[$i], 0) : 0;
    $pre = isset($precos[$i]) ? brl_to_float((string)$precos[$i]) : 0.0;

    if ($pid <= 0) continue;
    if ($qtd <= 0) continue;

    $items[] = ['produto_id' => $pid, 'qtd' => $qtd, 'preco' => $pre];
}

if (!$items) {
    flash_set('danger', 'Adicione pelo menos 1 item na venda.');
    redirect('../../vendas.php');
}

// carrega produtos p/ validar preço/estoque
$ids = array_values(array_unique(array_map(fn($x) => (int)$x['produto_id'], $items)));
$in  = implode(',', array_fill(0, count($ids), '?'));
$stP = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE id IN ($in)");
$stP->execute($ids);
$prodMap = [];
while ($p = $stP->fetch(PDO::FETCH_ASSOC)) {
    $prodMap[(int)$p['id']] = $p;
}

// valida todos os itens
foreach ($items as &$it) {
    $pid = (int)$it['produto_id'];
    if (!isset($prodMap[$pid])) {
        flash_set('danger', 'Produto inválido na venda.');
        redirect('../../vendas.php');
    }
    // se preço veio 0, usa o preço do produto
    if ((float)$it['preco'] <= 0) $it['preco'] = (float)$prodMap[$pid]['preco'];
}
unset($it);

try {
    $pdo->beginTransaction();

    // EDITAR: devolve estoque antigo e apaga itens antigos
    if ($id > 0) {
        $stOld = $pdo->prepare("SELECT produto_id, qtd FROM venda_itens WHERE venda_id = ?");
        $stOld->execute([$id]);
        $old = $stOld->fetchAll(PDO::FETCH_ASSOC);

        foreach ($old as $o) {
            $pid = (int)$o['produto_id'];
            $qtd = (int)$o['qtd'];
            $up = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
            $up->execute([$qtd, $pid]);
        }

        $pdo->prepare("DELETE FROM venda_itens WHERE venda_id = ?")->execute([$id]);

        $pdo->prepare("
      UPDATE vendas
      SET data = ?, pedido = ?, cliente = ?, canal = ?, pagamento = ?, obs = ?
      WHERE id = ?
    ")->execute([$data, $pedido ?: null, $cliente, $validCanal, $pagamento, $obs ?: null, $id]);
    } else {
        $pdo->prepare("
      INSERT INTO vendas (data, pedido, cliente, canal, pagamento, obs, total)
      VALUES (?, ?, ?, ?, ?, ?, 0.00)
    ")->execute([$data, $pedido ?: null, $cliente, $validCanal, $pagamento, $obs ?: null]);
        $id = (int)$pdo->lastInsertId();
    }

    // insere itens + baixa estoque
    $totalVenda = 0.0;

    foreach ($items as $it) {
        $pid = (int)$it['produto_id'];
        $qtd = (int)$it['qtd'];
        $pre = (float)$it['preco'];
        $tot = $qtd * $pre;

        // baixa estoque com trava: só baixa se tiver
        $up = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque >= ?");
        $up->execute([$qtd, $pid, $qtd]);
        if ($up->rowCount() <= 0) {
            $nome = (string)$prodMap[$pid]['nome'];
            throw new RuntimeException("Estoque insuficiente para: {$nome}");
        }

        $pdo->prepare("
      INSERT INTO venda_itens (venda_id, produto_id, qtd, preco, total)
      VALUES (?, ?, ?, ?, ?)
    ")->execute([$id, $pid, $qtd, $pre, $tot]);

        $totalVenda += $tot;
    }

    $pdo->prepare("UPDATE vendas SET total = ? WHERE id = ?")->execute([$totalVenda, $id]);

    $pdo->commit();

    flash_set('success', 'Venda salva com sucesso! (estoque atualizado)');
    redirect('../../vendas.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('danger', 'Erro ao salvar venda: ' . $e->getMessage());
    redirect('../../vendas.php');
}
