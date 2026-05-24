<?php
require_once __DIR__ . '/orders.php';

function pdv_products(): array
{
    $statement = db()->query(
        'SELECT
            p.id,
            p.sku,
            p.nome,
            p.estoque,
            p.preco,
            p.preco_promocional,
            c.nome AS categoria,
            (
              SELECT pi.url
              FROM produto_imagens pi
              WHERE pi.produto_id = p.id
              ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
              LIMIT 1
            ) AS imagem
         FROM produtos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.removido_em IS NULL
           AND p.disponivel_pdv = 1
           AND p.status = "disponivel"
           AND p.estoque > 0
         ORDER BY p.nome ASC'
    );

    return array_map(static function (array $product): array {
        return [
            'id' => (int) $product['id'],
            'sku' => (string) ($product['sku'] ?? ''),
            'nome' => (string) $product['nome'],
            'categoria' => (string) ($product['categoria'] ?? 'Sem categoria'),
            'preco' => effective_price($product),
            'imagem' => (string) ($product['imagem'] ?? ''),
            'estoque' => (int) $product['estoque'],
        ];
    }, $statement->fetchAll());
}

function pdv_generate_code(): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = '#PDV-' . date('ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $statement = db()->prepare('SELECT COUNT(*) AS total FROM pedidos WHERE codigo = :codigo');
        $statement->execute(['codigo' => $candidate]);
        if ((int) ($statement->fetch()['total'] ?? 0) === 0) {
            return $candidate;
        }
    }

    return '#PDV-' . date('ymdHis') . '-' . random_int(100, 999);
}

function pdv_decimal(mixed $value): float
{
    $text = str_replace(',', '.', order_clean_text($value, 20));

    return max(0, round((float) $text, 2));
}

function pdv_create_sale_from_payload(array $payload, array $adminUser): array
{
    $items = $payload['itens'] ?? [];
    if (!is_array($items) || empty($items)) {
        throw new InvalidArgumentException('Adicione produtos antes de finalizar a venda.');
    }

    $payment = order_normalize_payment_method((string) ($payload['pagamento'] ?? ''));
    if (!isset(order_payment_method_options()[$payment])) {
        throw new InvalidArgumentException('Forma de pagamento inválida.');
    }

    $normalizedItems = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $productId = filter_var($item['produto_id'] ?? $item['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $quantity = filter_var($item['quantidade'] ?? $item['qty'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        if ($productId > 0 && $quantity > 0) {
            $normalizedItems[$productId] = ($normalizedItems[$productId] ?? 0) + $quantity;
        }
    }

    if (empty($normalizedItems)) {
        throw new InvalidArgumentException('Venda sem itens válidos.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $ids = array_keys($normalizedItems);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            'SELECT p.*, c.nome AS categoria_nome
             FROM produtos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id IN (' . $placeholders . ')
               AND p.removido_em IS NULL
             FOR UPDATE'
        );
        $statement->execute($ids);

        $products = [];
        foreach ($statement->fetchAll() as $product) {
            $products[(int) $product['id']] = $product;
        }

        $lines = [];
        $subtotal = 0.0;
        foreach ($normalizedItems as $productId => $quantity) {
            $product = $products[$productId] ?? null;
            if (!$product) {
                throw new InvalidArgumentException('Um produto da venda não foi encontrado.');
            }
            if ((int) $product['disponivel_pdv'] !== 1 || (string) $product['status'] !== 'disponivel') {
                throw new InvalidArgumentException('O produto "' . $product['nome'] . '" não está disponível no PDV.');
            }
            if ((int) $product['estoque'] < $quantity) {
                throw new InvalidArgumentException('Estoque insuficiente para "' . $product['nome'] . '".');
            }

            $unitPrice = effective_price($product);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'produto' => $product,
                'quantidade' => $quantity,
                'preco_unitario' => $unitPrice,
                'total_linha' => $lineTotal,
            ];
        }

        $discount = min(pdv_decimal($payload['desconto'] ?? 0), $subtotal);
        $total = max(0, round($subtotal - $discount, 2));
        $received = pdv_decimal($payload['valor_recebido'] ?? $total);
        $change = max(0, round($received - $total, 2));
        $clientName = order_clean_text($payload['cliente'] ?? 'Cliente balcão', 160) ?: 'Cliente balcão';
        $clientContact = order_clean_text($payload['contato'] ?? 'Balcão', 60) ?: 'Balcão';
        $customerId = null;

        if ($clientName !== 'Cliente balcão' && $clientContact !== 'Balcão') {
            $customerId = order_find_or_create_customer([
                'cliente_nome' => $clientName,
                'cliente_contato' => $clientContact,
                'bairro' => null,
            ]);
        }

        $code = pdv_generate_code();
        $pdo->prepare(
            'INSERT INTO pedidos (
                codigo, cliente_id, cliente_nome, cliente_contato, origem, status,
                forma_pagamento, status_pagamento, recebimento, subtotal,
                desconto_total, taxa_entrega, total, finalizado_em
             ) VALUES (
                :codigo, :cliente_id, :cliente_nome, :cliente_contato, "pdv", "finalizado",
                :forma_pagamento, "confirmado", "retirada", :subtotal,
                :desconto_total, 0, :total, CURRENT_TIMESTAMP
             )'
        )->execute([
            'codigo' => $code,
            'cliente_id' => $customerId,
            'cliente_nome' => $clientName,
            'cliente_contato' => $clientContact,
            'forma_pagamento' => $payment,
            'subtotal' => $subtotal,
            'desconto_total' => $discount,
            'total' => $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        foreach ($lines as $line) {
            $product = $line['produto'];
            $pdo->prepare(
                'INSERT INTO pedido_itens (
                    pedido_id, produto_id, produto_sku, produto_nome, produto_categoria,
                    quantidade, preco_unitario, desconto_unitario, total_linha
                 ) VALUES (
                    :pedido_id, :produto_id, :produto_sku, :produto_nome, :produto_categoria,
                    :quantidade, :preco_unitario, 0, :total_linha
                 )'
            )->execute([
                'pedido_id' => $orderId,
                'produto_id' => (int) $product['id'],
                'produto_sku' => $product['sku'],
                'produto_nome' => $product['nome'],
                'produto_categoria' => $product['categoria_nome'] ?? null,
                'quantidade' => $line['quantidade'],
                'preco_unitario' => $line['preco_unitario'],
                'total_linha' => $line['total_linha'],
            ]);

            $before = (int) $product['estoque'];
            $after = $before - (int) $line['quantidade'];
            $pdo->prepare(
                'UPDATE produtos
                 SET estoque = :estoque,
                     status = CASE WHEN :estoque_status <= 0 THEN "sem_estoque" ELSE status END,
                     atualizado_em = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([
                'id' => (int) $product['id'],
                'estoque' => $after,
                'estoque_status' => $after,
            ]);

            $pdo->prepare(
                'INSERT INTO estoque_movimentacoes (
                    produto_id, pedido_id, usuario_admin_id, tipo, origem, quantidade,
                    estoque_anterior, estoque_novo, responsavel_nome, motivo, status, movimentado_em
                 ) VALUES (
                    :produto_id, :pedido_id, :usuario_admin_id, "saida", "venda", :quantidade,
                    :estoque_anterior, :estoque_novo, :responsavel_nome, :motivo, "concluido", CURRENT_TIMESTAMP
                 )'
            )->execute([
                'produto_id' => (int) $product['id'],
                'pedido_id' => $orderId,
                'usuario_admin_id' => (int) ($adminUser['id'] ?? 0) ?: null,
                'quantidade' => (int) $line['quantidade'],
                'estoque_anterior' => $before,
                'estoque_novo' => $after,
                'responsavel_nome' => order_clean_text($adminUser['nome'] ?? 'Admin', 140),
                'motivo' => 'Baixa automática da venda PDV ' . $code,
            ]);
        }

        $pdo->prepare(
            'INSERT INTO pagamentos (pedido_id, forma_pagamento, status, provedor, valor, confirmado_em)
             VALUES (:pedido_id, :forma_pagamento, "confirmado", "manual", :valor, CURRENT_TIMESTAMP)'
        )->execute([
            'pedido_id' => $orderId,
            'forma_pagamento' => $payment,
            'valor' => $total,
        ]);

        $pdo->prepare(
            'INSERT INTO pedido_status_historico (pedido_id, usuario_admin_id, status_anterior, status_novo, observacao)
             VALUES (:pedido_id, :usuario_admin_id, NULL, "finalizado", :observacao)'
        )->execute([
            'pedido_id' => $orderId,
            'usuario_admin_id' => (int) ($adminUser['id'] ?? 0) ?: null,
            'observacao' => 'Venda finalizada pelo PDV.',
        ]);

        $pdo->prepare(
            'INSERT INTO pdv_vendas (pedido_id, operador_id, valor_recebido, valor_troco, status)
             VALUES (:pedido_id, :operador_id, :valor_recebido, :valor_troco, "finalizada")'
        )->execute([
            'pedido_id' => $orderId,
            'operador_id' => (int) ($adminUser['id'] ?? 0) ?: null,
            'valor_recebido' => $received,
            'valor_troco' => $change,
        ]);

        $pdo->commit();

        return order_find_by_id($orderId) ?? ['id' => $orderId, 'codigo' => $code, 'total' => $total];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
