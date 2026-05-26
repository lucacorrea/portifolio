<?php
require_once __DIR__ . '/helpers.php';

function order_status_options(): array
{
    return [
        'pedido_recebido' => 'Pedido recebido',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pagamento_confirmado' => 'Pagamento confirmado',
        'em_preparo' => 'Em preparo',
        'saiu_para_entrega' => 'Saiu para entrega',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
    ];
}

function order_payment_status_options(): array
{
    return [
        'pendente' => 'Pendente',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'confirmado' => 'Confirmado',
        'cancelado' => 'Cancelado',
        'estornado' => 'Estornado',
    ];
}

function order_payment_method_options(): array
{
    return [
        'pix' => 'Pix manual',
        'dinheiro' => 'Dinheiro',
        'cartao_presencial' => 'Cartão presencial',
        'pagamento_retirada' => 'Pagamento na retirada',
    ];
}

function order_status_label(string $status): string
{
    return order_status_options()[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function order_payment_status_label(string $status): string
{
    return order_payment_status_options()[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function order_payment_method_label(string $method): string
{
    return order_payment_method_options()[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function order_receipt_label(string $receipt): string
{
    return $receipt === 'retirada' ? 'Retirada' : 'Entrega';
}

function order_origin_label(string $origin): string
{
    return match ($origin) {
        'pdv' => 'PDV',
        'atendimento' => 'Atendimento',
        default => 'Catálogo',
    };
}

function order_normalize_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if ($code === '') {
        return '';
    }

    return str_starts_with($code, '#') ? $code : '#' . $code;
}

function order_code_variants(string $code): array
{
    $normalized = order_normalize_code($code);
    if ($normalized === '') {
        return [];
    }

    return array_values(array_unique([$normalized, ltrim($normalized, '#')]));
}

function order_generate_code(): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = '#AF-' . date('ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $statement = db()->prepare('SELECT COUNT(*) AS total FROM pedidos WHERE codigo = :codigo');
        $statement->execute(['codigo' => $candidate]);
        if ((int) ($statement->fetch()['total'] ?? 0) === 0) {
            return $candidate;
        }
    }

    return '#AF-' . date('ymdHis') . '-' . random_int(100, 999);
}

function order_clean_text(mixed $value, int $limit): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function order_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function order_normalize_payment_method(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = str_replace(['ã', 'á', 'à', 'â', 'ç'], ['a', 'a', 'a', 'a', 'c'], $normalized);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? '';

    return match ($normalized) {
        'pix' => 'pix',
        'dinheiro' => 'dinheiro',
        'cartao_presencial', 'cartao', 'cartao_presencial_' => 'cartao_presencial',
        'pagamento_na_retirada', 'retirada' => 'pagamento_retirada',
        default => '',
    };
}

function order_normalize_receipt(string $value): string
{
    $normalized = strtolower(trim($value));

    return str_starts_with($normalized, 'ret') ? 'retirada' : 'entrega';
}

function order_normalize_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function order_normalize_time(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }

    $time = DateTimeImmutable::createFromFormat('H:i:s', $value);

    return $time && $time->format('H:i:s') === $value ? $value : null;
}

function order_checkout_payload(array $payload): array
{
    $cliente = is_array($payload['cliente'] ?? null) ? $payload['cliente'] : [];
    $items = $payload['itens'] ?? $payload['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    $name = order_clean_text($cliente['nome'] ?? $payload['nome'] ?? '', 160);
    $contact = order_clean_text($cliente['contato'] ?? $payload['contato'] ?? '', 60);
    $receipt = order_normalize_receipt((string) ($payload['recebimento'] ?? 'entrega'));
    $payment = order_normalize_payment_method((string) ($payload['forma_pagamento'] ?? $payload['pagamento'] ?? ''));
    $bairro = order_clean_text($payload['bairro'] ?? '', 120);
    $endereco = order_clean_text($payload['endereco'] ?? '', 255);
    $referencia = order_clean_text($payload['referencia'] ?? '', 255);
    $date = order_normalize_date($payload['data_desejada'] ?? $payload['data'] ?? null);
    $time = order_normalize_time($payload['horario_desejado'] ?? $payload['horario'] ?? null);

    if (order_text_length($name) < 3) {
        throw new InvalidArgumentException('Informe o nome completo para finalizar o pedido.');
    }
    if (order_text_length($contact) < 8) {
        throw new InvalidArgumentException('Informe um contato/WhatsApp válido para acompanhar o pedido.');
    }
    if (!isset(order_payment_method_options()[$payment])) {
        throw new InvalidArgumentException('Selecione uma forma de pagamento válida.');
    }
    if ($receipt === 'entrega' && ($bairro === '' || $endereco === '')) {
        throw new InvalidArgumentException('Informe bairro e endereço para entrega.');
    }
    if (empty($items)) {
        throw new InvalidArgumentException('Adicione pelo menos um produto ao carrinho.');
    }

    $normalizedItems = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $productId = (int) ($item['produto_id'] ?? $item['product_id'] ?? $item['id'] ?? 0);
        $colorId = (int) ($item['produto_cor_id'] ?? $item['cor_id'] ?? $item['color_id'] ?? 0);
        $quantity = (int) ($item['quantidade'] ?? $item['qty'] ?? 0);
        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }
        if ($quantity > 99) {
            throw new InvalidArgumentException('A quantidade por item deve ser menor que 100.');
        }

        $normalizedItems[] = [
            'produto_id' => $productId,
            'produto_cor_id' => $colorId > 0 ? $colorId : null,
            'quantidade' => $quantity,
            'mensagem_cartao' => order_clean_text($item['mensagem_cartao'] ?? $item['mensagem'] ?? '', 500),
            'observacoes' => order_clean_text($item['observacoes'] ?? '', 1000),
        ];
    }

    if (empty($normalizedItems)) {
        throw new InvalidArgumentException('O carrinho não possui produtos válidos.');
    }

    return [
        'cliente_nome' => $name,
        'cliente_contato' => $contact,
        'recebimento' => $receipt,
        'bairro' => $bairro !== '' ? $bairro : null,
        'endereco' => $endereco !== '' ? $endereco : null,
        'referencia' => $referencia !== '' ? $referencia : null,
        'data_desejada' => $date,
        'horario_desejado' => $time,
        'mensagem_cartao' => order_clean_text($payload['mensagem_cartao'] ?? $payload['mensagem'] ?? '', 500) ?: null,
        'observacoes' => order_clean_text($payload['observacoes'] ?? '', 2000) ?: null,
        'forma_pagamento' => $payment,
        'itens' => $normalizedItems,
    ];
}

function order_find_or_create_customer(array $data): int
{
    $statement = db()->prepare(
        'SELECT id
         FROM clientes
         WHERE removido_em IS NULL
           AND (telefone = :contato OR whatsapp = :contato)
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['contato' => $data['cliente_contato']]);
    $customer = $statement->fetch();

    if ($customer) {
        db()->prepare(
            'UPDATE clientes
             SET nome = :nome, telefone = :telefone, whatsapp = :whatsapp, bairro = :bairro, atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute([
            'id' => (int) $customer['id'],
            'nome' => $data['cliente_nome'],
            'telefone' => $data['cliente_contato'],
            'whatsapp' => $data['cliente_contato'],
            'bairro' => $data['bairro'],
        ]);

        return (int) $customer['id'];
    }

    db()->prepare(
        'INSERT INTO clientes (nome, telefone, whatsapp, bairro, canal_preferido)
         VALUES (:nome, :telefone, :whatsapp, :bairro, "whatsapp")'
    )->execute([
        'nome' => $data['cliente_nome'],
        'telefone' => $data['cliente_contato'],
        'whatsapp' => $data['cliente_contato'],
        'bairro' => $data['bairro'],
    ]);

    return (int) db()->lastInsertId();
}

function order_load_products_for_update(array $items): array
{
    $ids = array_values(array_unique(array_map(static fn (array $item): int => (int) $item['produto_id'], $items)));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        'SELECT p.*, c.nome AS categoria_nome,
            (
              SELECT pi.url
              FROM produto_imagens pi
              WHERE pi.produto_id = p.id
              ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
              LIMIT 1
            ) AS imagem
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

    return $products;
}

function order_load_color_variants_for_update(array $items): array
{
    $productIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int) $item['produto_id'], $items), static fn(int $id): bool => $id > 0)));
    $colorIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int) ($item['produto_cor_id'] ?? 0), $items), static fn(int $id): bool => $id > 0)));

    $activeCounts = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = db()->prepare(
            'SELECT produto_id, COUNT(*) AS total
             FROM produto_cores
             WHERE produto_id IN (' . $placeholders . ')
               AND ativo = 1
             GROUP BY produto_id'
        );
        $statement->execute($productIds);
        foreach ($statement->fetchAll() as $row) {
            $activeCounts[(int) $row['produto_id']] = (int) $row['total'];
        }
    }

    $colors = [];
    if (!empty($colorIds)) {
        $placeholders = implode(',', array_fill(0, count($colorIds), '?'));
        $statement = db()->prepare(
            'SELECT id, produto_id, nome, hex, imagem_url, estoque, ativo
             FROM produto_cores
             WHERE id IN (' . $placeholders . ')
             FOR UPDATE'
        );
        $statement->execute($colorIds);
        foreach ($statement->fetchAll() as $color) {
            $colors[(int) $color['id']] = $color;
        }
    }

    return [
        'active_counts' => $activeCounts,
        'colors' => $colors,
    ];
}

function order_calculate_discount(float $subtotal): float
{
    return $subtotal >= 250 ? round($subtotal * 0.05, 2) : 0.0;
}

function order_create_from_checkout(array $payload): array
{
    $data = order_checkout_payload($payload);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $products = order_load_products_for_update($data['itens']);
        $colorState = order_load_color_variants_for_update($data['itens']);
        $requiredStock = [];
        $requiredColorStock = [];

        foreach ($data['itens'] as $item) {
            $productId = (int) $item['produto_id'];
            $requiredStock[$productId] = ($requiredStock[$productId] ?? 0) + $item['quantidade'];
            $colorId = (int) ($item['produto_cor_id'] ?? 0);
            if ($colorId > 0) {
                $requiredColorStock[$colorId] = ($requiredColorStock[$colorId] ?? 0) + $item['quantidade'];
            }
        }

        foreach ($requiredStock as $productId => $quantity) {
            $product = $products[$productId] ?? null;
            if (!$product) {
                throw new InvalidArgumentException('Um dos produtos do carrinho não está mais disponível.');
            }
            if ((int) $product['exibir_catalogo'] !== 1 || (int) $product['permitir_venda_online'] !== 1) {
                throw new InvalidArgumentException('O produto "' . $product['nome'] . '" não está disponível para venda online.');
            }
            if ((string) $product['status'] !== 'disponivel') {
                throw new InvalidArgumentException('O produto "' . $product['nome'] . '" não está disponível para compra direta.');
            }
            if ((int) $product['estoque'] < $quantity) {
                throw new InvalidArgumentException('Estoque insuficiente para "' . $product['nome'] . '".');
            }
        }

        foreach ($data['itens'] as $item) {
            $product = $products[(int) $item['produto_id']] ?? null;
            if (!$product) {
                continue;
            }

            $activeColorCount = (int) ($colorState['active_counts'][(int) $product['id']] ?? 0);
            $colorId = (int) ($item['produto_cor_id'] ?? 0);
            if ($activeColorCount > 0 && $colorId <= 0) {
                throw new InvalidArgumentException('Escolha uma cor para "' . $product['nome'] . '".');
            }
            if ($colorId <= 0) {
                continue;
            }

            $color = $colorState['colors'][$colorId] ?? null;
            if (!$color || (int) $color['produto_id'] !== (int) $product['id'] || (int) $color['ativo'] !== 1) {
                throw new InvalidArgumentException('A cor escolhida para "' . $product['nome'] . '" não está disponível.');
            }
        }

        foreach ($requiredColorStock as $colorId => $quantity) {
            $color = $colorState['colors'][$colorId] ?? null;
            if (!$color || (int) $color['estoque'] < $quantity) {
                throw new InvalidArgumentException('Estoque insuficiente para a cor "' . (string) ($color['nome'] ?? 'selecionada') . '".');
            }
        }

        $lines = [];
        $subtotal = 0.0;
        foreach ($data['itens'] as $item) {
            $product = $products[$item['produto_id']];
            $colorId = (int) ($item['produto_cor_id'] ?? 0);
            $color = $colorId > 0 ? ($colorState['colors'][$colorId] ?? null) : null;
            $unitPrice = effective_price($product);
            $lineTotal = round($unitPrice * $item['quantidade'], 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'produto' => $product,
                'cor' => $color,
                'quantidade' => $item['quantidade'],
                'preco_unitario' => $unitPrice,
                'total_linha' => $lineTotal,
                'mensagem_cartao' => $item['mensagem_cartao'] ?: null,
                'observacoes' => $item['observacoes'] ?: null,
            ];
        }

        $discount = order_calculate_discount($subtotal);
        $deliveryFee = 0.0;
        $total = max(0, round($subtotal - $discount + $deliveryFee, 2));
        $paymentStatus = $data['forma_pagamento'] === 'pix' ? 'aguardando_pagamento' : 'pendente';
        $orderStatus = $paymentStatus === 'aguardando_pagamento' ? 'aguardando_pagamento' : 'pedido_recebido';
        $customerId = order_find_or_create_customer($data);
        $code = order_generate_code();

        $pdo->prepare(
            'INSERT INTO pedidos (
                codigo, cliente_id, cliente_nome, cliente_contato, origem, status,
                forma_pagamento, status_pagamento, recebimento, bairro, endereco,
                referencia, data_desejada, horario_desejado, mensagem_cartao, observacoes,
                subtotal, desconto_total, taxa_entrega, total
             ) VALUES (
                :codigo, :cliente_id, :cliente_nome, :cliente_contato, "catalogo", :status,
                :forma_pagamento, :status_pagamento, :recebimento, :bairro, :endereco,
                :referencia, :data_desejada, :horario_desejado, :mensagem_cartao, :observacoes,
                :subtotal, :desconto_total, :taxa_entrega, :total
             )'
        )->execute([
            'codigo' => $code,
            'cliente_id' => $customerId,
            'cliente_nome' => $data['cliente_nome'],
            'cliente_contato' => $data['cliente_contato'],
            'status' => $orderStatus,
            'forma_pagamento' => $data['forma_pagamento'],
            'status_pagamento' => $paymentStatus,
            'recebimento' => $data['recebimento'],
            'bairro' => $data['bairro'],
            'endereco' => $data['endereco'],
            'referencia' => $data['referencia'],
            'data_desejada' => $data['data_desejada'],
            'horario_desejado' => $data['horario_desejado'],
            'mensagem_cartao' => $data['mensagem_cartao'],
            'observacoes' => $data['observacoes'],
            'subtotal' => $subtotal,
            'desconto_total' => $discount,
            'taxa_entrega' => $deliveryFee,
            'total' => $total,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        foreach ($lines as $line) {
            $product = $line['produto'];
            $color = $line['cor'];
            $pdo->prepare(
                'INSERT INTO pedido_itens (
                    pedido_id, produto_id, produto_cor_id, produto_sku, produto_nome, produto_categoria,
                    produto_cor_nome, produto_cor_hex, produto_cor_imagem,
                    quantidade, preco_unitario, desconto_unitario, total_linha,
                    mensagem_cartao, observacoes
                 ) VALUES (
                    :pedido_id, :produto_id, :produto_cor_id, :produto_sku, :produto_nome, :produto_categoria,
                    :produto_cor_nome, :produto_cor_hex, :produto_cor_imagem,
                    :quantidade, :preco_unitario, 0, :total_linha,
                    :mensagem_cartao, :observacoes
                 )'
            )->execute([
                'pedido_id' => $orderId,
                'produto_id' => (int) $product['id'],
                'produto_cor_id' => $color ? (int) $color['id'] : null,
                'produto_sku' => $product['sku'],
                'produto_nome' => $product['nome'],
                'produto_categoria' => $product['categoria_nome'] ?? null,
                'produto_cor_nome' => $color['nome'] ?? null,
                'produto_cor_hex' => $color['hex'] ?? null,
                'produto_cor_imagem' => $color['imagem_url'] ?? null,
                'quantidade' => $line['quantidade'],
                'preco_unitario' => $line['preco_unitario'],
                'total_linha' => $line['total_linha'],
                'mensagem_cartao' => $line['mensagem_cartao'],
                'observacoes' => $line['observacoes'],
            ]);
        }

        $pixKey = function_exists('integration_setting')
            ? (integration_setting('pix_key', '') ?: null)
            : null;

        $pdo->prepare(
            'INSERT INTO pagamentos (pedido_id, forma_pagamento, status, provedor, valor, chave_pix, codigo_pix)
             VALUES (:pedido_id, :forma_pagamento, :status, :provedor, :valor, :chave_pix, :codigo_pix)'
        )->execute([
            'pedido_id' => $orderId,
            'forma_pagamento' => $data['forma_pagamento'],
            'status' => $paymentStatus,
            'provedor' => 'manual',
            'valor' => $total,
            'chave_pix' => $data['forma_pagamento'] === 'pix' ? $pixKey : null,
            'codigo_pix' => $data['forma_pagamento'] === 'pix' ? 'PIX MANUAL - CONFIRMACAO PELO ADMIN' : null,
        ]);

        $pdo->prepare(
            'INSERT INTO pedido_status_historico (pedido_id, status_anterior, status_novo, observacao)
             VALUES (:pedido_id, NULL, :status_novo, :observacao)'
        )->execute([
            'pedido_id' => $orderId,
            'status_novo' => $orderStatus,
            'observacao' => 'Pedido criado pelo checkout público.',
        ]);

        $productStockBalance = [];
        $colorStockBalance = [];
        foreach ($lines as $line) {
            $product = $line['produto'];
            $color = $line['cor'];
            $productId = (int) $product['id'];
            $quantity = (int) $line['quantidade'];
            $before = $productStockBalance[$productId] ?? (int) $product['estoque'];
            $after = $before - $quantity;
            $productStockBalance[$productId] = $after;
            $newStatus = $after <= 0 ? 'sem_estoque' : $product['status'];

            $pdo->prepare(
                'UPDATE produtos
                 SET estoque = :estoque, status = :status, atualizado_em = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([
                'id' => $productId,
                'estoque' => $after,
                'status' => $newStatus,
            ]);

            if ($color) {
                $colorId = (int) $color['id'];
                $colorBefore = $colorStockBalance[$colorId] ?? (int) $color['estoque'];
                $colorAfter = $colorBefore - $quantity;
                $colorStockBalance[$colorId] = $colorAfter;
                $pdo->prepare(
                    'UPDATE produto_cores
                     SET estoque = :estoque, atualizado_em = CURRENT_TIMESTAMP
                     WHERE id = :id AND produto_id = :produto_id'
                )->execute([
                    'id' => $colorId,
                    'produto_id' => $productId,
                    'estoque' => $colorAfter,
                ]);
            }

            $pdo->prepare(
                'INSERT INTO estoque_movimentacoes (
                    produto_id, produto_cor_id, pedido_id, tipo, origem, quantidade,
                    estoque_anterior, estoque_novo, responsavel_nome, motivo, status, movimentado_em
                 ) VALUES (
                    :produto_id, :produto_cor_id, :pedido_id, "saida", "venda", :quantidade,
                    :estoque_anterior, :estoque_novo, "Checkout público", :motivo, "concluido", CURRENT_TIMESTAMP
                 )'
            )->execute([
                'produto_id' => $productId,
                'produto_cor_id' => $color ? (int) $color['id'] : null,
                'pedido_id' => $orderId,
                'quantidade' => $quantity,
                'estoque_anterior' => $before,
                'estoque_novo' => $after,
                'motivo' => 'Baixa automática do pedido ' . $code,
            ]);
        }

        $pdo->commit();

        return order_find_by_id($orderId) ?? ['id' => $orderId, 'codigo' => $code, 'status' => $orderStatus];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

function order_find_by_id(int $orderId): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $orderId]);
    $order = $statement->fetch();

    return $order ?: null;
}

function order_find_by_code(string $code): ?array
{
    $variants = order_code_variants($code);
    if (!$variants) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM pedidos
         WHERE codigo IN (:codigo_hash, :codigo_plain)
         LIMIT 1'
    );
    $statement->execute([
        'codigo_hash' => $variants[0],
        'codigo_plain' => $variants[1] ?? ltrim($variants[0], '#'),
    ]);
    $order = $statement->fetch();

    return $order ?: null;
}

function order_items(int $orderId): array
{
    if ($orderId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT pi.*,
            COALESCE(pi.produto_cor_imagem, (
              SELECT img.url
              FROM produto_imagens img
              WHERE img.produto_id = pi.produto_id
              ORDER BY img.principal DESC, img.ordem ASC, img.id ASC
              LIMIT 1
            )) AS imagem
         FROM pedido_itens pi
         WHERE pi.pedido_id = :pedido_id
         ORDER BY pi.id ASC'
    );
    $statement->execute(['pedido_id' => $orderId]);

    return $statement->fetchAll();
}

function order_history(int $orderId): array
{
    if ($orderId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT h.*, u.nome AS admin_nome
         FROM pedido_status_historico h
         LEFT JOIN usuarios_admin u ON u.id = h.usuario_admin_id
         WHERE h.pedido_id = :pedido_id
         ORDER BY h.criado_em ASC, h.id ASC'
    );
    $statement->execute(['pedido_id' => $orderId]);

    return $statement->fetchAll();
}

function order_payment(int $orderId): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM pagamentos
         WHERE pedido_id = :pedido_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['pedido_id' => $orderId]);
    $payment = $statement->fetch();

    return $payment ?: null;
}

function order_admin_filters_from_request(array $request): array
{
    return [
        'busca' => order_clean_text($request['busca'] ?? '', 120),
        'status' => order_clean_text($request['status'] ?? '', 60),
        'status_pagamento' => order_clean_text($request['status_pagamento'] ?? '', 60),
        'origem' => order_clean_text($request['origem'] ?? '', 40),
        'data_inicio' => order_normalize_date($request['data_inicio'] ?? null),
        'data_fim' => order_normalize_date($request['data_fim'] ?? null),
    ];
}

function order_admin_where(array $filters, array &$params): array
{
    $where = ['1 = 1'];

    if (($filters['busca'] ?? '') !== '') {
        $where[] = '(codigo LIKE :busca_codigo OR cliente_nome LIKE :busca_cliente OR cliente_contato LIKE :busca_contato OR bairro LIKE :busca_bairro)';
        $params['busca_codigo'] = '%' . $filters['busca'] . '%';
        $params['busca_cliente'] = '%' . $filters['busca'] . '%';
        $params['busca_contato'] = '%' . $filters['busca'] . '%';
        $params['busca_bairro'] = '%' . $filters['busca'] . '%';
    }
    if (($filters['status'] ?? '') !== '' && isset(order_status_options()[$filters['status']])) {
        $where[] = 'status = :status';
        $params['status'] = $filters['status'];
    }
    if (($filters['status_pagamento'] ?? '') !== '' && isset(order_payment_status_options()[$filters['status_pagamento']])) {
        $where[] = 'status_pagamento = :status_pagamento';
        $params['status_pagamento'] = $filters['status_pagamento'];
    }
    if (($filters['origem'] ?? '') !== '' && in_array($filters['origem'], ['catalogo', 'pdv', 'atendimento'], true)) {
        $where[] = 'origem = :origem';
        $params['origem'] = $filters['origem'];
    }
    if (!empty($filters['data_inicio'])) {
        $where[] = 'criado_em >= :data_inicio';
        $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
    }
    if (!empty($filters['data_fim'])) {
        $where[] = 'criado_em <= :data_fim';
        $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
    }

    return $where;
}

function orders_admin_list(array $filters = [], int $limit = 80): array
{
    $params = [];
    $where = order_admin_where($filters, $params);
    $limit = max(1, min(200, $limit));

    $statement = db()->prepare(
        'SELECT *
         FROM pedidos
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY criado_em DESC, id DESC
         LIMIT ' . $limit
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function orders_admin_stats(array $filters = []): array
{
    $params = [];
    $where = order_admin_where($filters, $params);
    $statement = db()->prepare(
        'SELECT
            SUM(CASE WHEN DATE(criado_em) = CURRENT_DATE THEN 1 ELSE 0 END) AS pedidos_hoje,
            SUM(CASE WHEN status_pagamento IN ("pendente", "aguardando_pagamento") THEN 1 ELSE 0 END) AS aguardando_pagamento,
            SUM(CASE WHEN status_pagamento = "confirmado" THEN 1 ELSE 0 END) AS pagamento_confirmado,
            SUM(CASE WHEN status = "em_preparo" THEN 1 ELSE 0 END) AS em_preparo,
            SUM(CASE WHEN status = "saiu_para_entrega" THEN 1 ELSE 0 END) AS saiu_para_entrega,
            SUM(CASE WHEN status = "finalizado" THEN 1 ELSE 0 END) AS finalizados,
            SUM(CASE WHEN status = "cancelado" THEN 1 ELSE 0 END) AS cancelados,
            COALESCE(SUM(CASE WHEN status <> "cancelado" THEN total ELSE 0 END), 0) AS faturamento
         FROM pedidos
         WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($params);
    $stats = $statement->fetch() ?: [];

    return [
        'pedidos_hoje' => (int) ($stats['pedidos_hoje'] ?? 0),
        'aguardando_pagamento' => (int) ($stats['aguardando_pagamento'] ?? 0),
        'pagamento_confirmado' => (int) ($stats['pagamento_confirmado'] ?? 0),
        'em_preparo' => (int) ($stats['em_preparo'] ?? 0),
        'saiu_para_entrega' => (int) ($stats['saiu_para_entrega'] ?? 0),
        'finalizados' => (int) ($stats['finalizados'] ?? 0),
        'cancelados' => (int) ($stats['cancelados'] ?? 0),
        'faturamento' => (float) ($stats['faturamento'] ?? 0),
    ];
}

function order_update_status(int $orderId, string $newStatus, ?int $adminId = null, string $note = ''): void
{
    if ($orderId <= 0 || !isset(order_status_options()[$newStatus])) {
        throw new InvalidArgumentException('Status de pedido inválido.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, status FROM pedidos WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if (!$order) {
            throw new InvalidArgumentException('Pedido não encontrado.');
        }

        $oldStatus = (string) $order['status'];
        $pdo->prepare(
            'UPDATE pedidos
             SET status = :status,
                 finalizado_em = CASE WHEN :status_finalizado = "finalizado" THEN COALESCE(finalizado_em, CURRENT_TIMESTAMP) ELSE finalizado_em END,
                 cancelado_em = CASE WHEN :status_cancelado = "cancelado" THEN COALESCE(cancelado_em, CURRENT_TIMESTAMP) ELSE cancelado_em END,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute([
            'id' => $orderId,
            'status' => $newStatus,
            'status_finalizado' => $newStatus,
            'status_cancelado' => $newStatus,
        ]);

        $pdo->prepare(
            'INSERT INTO pedido_status_historico (pedido_id, usuario_admin_id, status_anterior, status_novo, observacao)
             VALUES (:pedido_id, :usuario_admin_id, :status_anterior, :status_novo, :observacao)'
        )->execute([
            'pedido_id' => $orderId,
            'usuario_admin_id' => $adminId ?: null,
            'status_anterior' => $oldStatus,
            'status_novo' => $newStatus,
            'observacao' => $note !== '' ? order_clean_text($note, 255) : 'Status atualizado no painel.',
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function order_update_payment(int $orderId, string $action, ?int $adminId = null): void
{
    if ($orderId <= 0 || !in_array($action, ['confirmar_pagamento', 'cancelar_pagamento'], true)) {
        throw new InvalidArgumentException('Ação de pagamento inválida.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, status FROM pedidos WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if (!$order) {
            throw new InvalidArgumentException('Pedido não encontrado.');
        }

        $paymentStatus = $action === 'confirmar_pagamento' ? 'confirmado' : 'cancelado';
        $pdo->prepare(
            'UPDATE pagamentos
             SET status = :status,
                 confirmado_em = CASE WHEN :status_confirmado = "confirmado" THEN COALESCE(confirmado_em, CURRENT_TIMESTAMP) ELSE confirmado_em END,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE pedido_id = :pedido_id
             ORDER BY id DESC
             LIMIT 1'
        )->execute([
            'pedido_id' => $orderId,
            'status' => $paymentStatus,
            'status_confirmado' => $paymentStatus,
        ]);

        $newOrderStatus = (string) $order['status'];
        if ($paymentStatus === 'confirmado' && in_array($newOrderStatus, ['pedido_recebido', 'aguardando_pagamento'], true)) {
            $newOrderStatus = 'pagamento_confirmado';
        }

        $pdo->prepare(
            'UPDATE pedidos
             SET status_pagamento = :status_pagamento, status = :status, atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute([
            'id' => $orderId,
            'status_pagamento' => $paymentStatus,
            'status' => $newOrderStatus,
        ]);

        $pdo->prepare(
            'INSERT INTO pedido_status_historico (pedido_id, usuario_admin_id, status_anterior, status_novo, observacao)
             VALUES (:pedido_id, :usuario_admin_id, :status_anterior, :status_novo, :observacao)'
        )->execute([
            'pedido_id' => $orderId,
            'usuario_admin_id' => $adminId ?: null,
            'status_anterior' => $order['status'],
            'status_novo' => $newOrderStatus,
            'observacao' => $paymentStatus === 'confirmado' ? 'Pagamento confirmado manualmente.' : 'Pagamento cancelado manualmente.',
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function order_badge_class(string $status): string
{
    return match ($status) {
        'finalizado', 'confirmado', 'pagamento_confirmado' => 'admin-badge-ok',
        'cancelado', 'estornado' => 'admin-badge-danger',
        'aguardando_pagamento', 'pendente', 'pedido_recebido' => 'admin-badge-info',
        default => 'admin-badge-warn',
    };
}
