<?php
require_once __DIR__ . '/products.php';

function dashboard_order_status_label(string $status): string
{
    return match ($status) {
        'pedido_recebido' => 'Pedido recebido',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pagamento_confirmado' => 'Pagamento confirmado',
        'em_preparo' => 'Em preparo',
        'saiu_para_entrega' => 'Saiu para entrega',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function dashboard_payment_label(string $payment): string
{
    return match ($payment) {
        'pix' => 'Pix',
        'dinheiro' => 'Dinheiro',
        'cartao_presencial' => 'Cartão presencial',
        'pagamento_retirada' => 'Pagamento na retirada',
        default => ucfirst(str_replace('_', ' ', $payment)),
    };
}

function dashboard_origin_label(string $origin): string
{
    return match ($origin) {
        'catalogo' => 'Catálogo',
        'pdv' => 'PDV',
        'atendimento' => 'Atendimento',
        default => ucfirst(str_replace('_', ' ', $origin)),
    };
}

function dashboard_receipt_label(string $receipt): string
{
    return match ($receipt) {
        'entrega' => 'Entrega',
        'retirada' => 'Retirada',
        default => ucfirst(str_replace('_', ' ', $receipt)),
    };
}

function dashboard_order_badge_class(string $status): string
{
    return match ($status) {
        'finalizado', 'pagamento_confirmado' => 'admin-badge-ok',
        'cancelado' => 'admin-badge-danger',
        'aguardando_pagamento', 'pedido_recebido' => 'admin-badge-info',
        default => 'admin-badge-warn',
    };
}

function dashboard_today_summary(): array
{
    $row = db()->query(
        "SELECT
            SUM(CASE WHEN DATE(criado_em) = CURRENT_DATE AND status <> 'cancelado' THEN total ELSE 0 END) AS vendas_hoje,
            SUM(CASE WHEN DATE(criado_em) = CURRENT_DATE AND status <> 'cancelado' THEN 1 ELSE 0 END) AS pedidos_hoje,
            SUM(CASE WHEN status IN ('pedido_recebido', 'aguardando_pagamento', 'pagamento_confirmado', 'em_preparo', 'saiu_para_entrega') THEN 1 ELSE 0 END) AS pedidos_pendentes,
            SUM(CASE WHEN status = 'em_preparo' THEN 1 ELSE 0 END) AS em_preparo,
            SUM(CASE WHEN forma_pagamento = 'pix' AND status_pagamento IN ('pendente', 'aguardando_pagamento') AND status <> 'cancelado' THEN 1 ELSE 0 END) AS pix_pendente,
            SUM(CASE WHEN status = 'finalizado' AND DATE(COALESCE(finalizado_em, atualizado_em, criado_em)) = CURRENT_DATE THEN 1 ELSE 0 END) AS finalizados_hoje,
            AVG(CASE WHEN DATE(criado_em) = CURRENT_DATE AND status <> 'cancelado' THEN total ELSE NULL END) AS ticket_medio
         FROM pedidos"
    )->fetch() ?: [];

    return [
        'vendas_hoje' => (float) ($row['vendas_hoje'] ?? 0),
        'pedidos_hoje' => (int) ($row['pedidos_hoje'] ?? 0),
        'pedidos_pendentes' => (int) ($row['pedidos_pendentes'] ?? 0),
        'em_preparo' => (int) ($row['em_preparo'] ?? 0),
        'pix_pendente' => (int) ($row['pix_pendente'] ?? 0),
        'finalizados_hoje' => (int) ($row['finalizados_hoje'] ?? 0),
        'ticket_medio' => (float) ($row['ticket_medio'] ?? 0),
    ];
}

function dashboard_payment_summary_today(): array
{
    $rows = db()->query(
        "SELECT forma_pagamento, COUNT(*) AS total_pedidos, SUM(total) AS total_valor
         FROM pedidos
         WHERE DATE(criado_em) = CURRENT_DATE
           AND status <> 'cancelado'
         GROUP BY forma_pagamento
         ORDER BY total_valor DESC"
    )->fetchAll();

    $summary = [
        'pix' => ['label' => 'Pix', 'total_pedidos' => 0, 'total_valor' => 0.0],
        'dinheiro' => ['label' => 'Dinheiro', 'total_pedidos' => 0, 'total_valor' => 0.0],
        'cartao_presencial' => ['label' => 'Cartão presencial', 'total_pedidos' => 0, 'total_valor' => 0.0],
        'pagamento_retirada' => ['label' => 'Pagamento na retirada', 'total_pedidos' => 0, 'total_valor' => 0.0],
    ];

    foreach ($rows as $row) {
        $key = (string) $row['forma_pagamento'];
        $summary[$key] = [
            'label' => dashboard_payment_label($key),
            'total_pedidos' => (int) ($row['total_pedidos'] ?? 0),
            'total_valor' => (float) ($row['total_valor'] ?? 0),
        ];
    }

    return $summary;
}

function dashboard_category_sales(int $days = 30): array
{
    $days = max(1, min(365, $days));
    $statement = db()->query(
        "SELECT
            COALESCE(c.nome, pi.produto_categoria, 'Sem categoria') AS categoria,
            COUNT(DISTINCT p.id) AS pedidos,
            SUM(pi.quantidade) AS quantidade,
            SUM(pi.total_linha) AS total
         FROM pedido_itens pi
         INNER JOIN pedidos p ON p.id = pi.pedido_id
         LEFT JOIN produtos pr ON pr.id = pi.produto_id
         LEFT JOIN categorias c ON c.id = pr.categoria_id
         WHERE p.status <> 'cancelado'
           AND p.criado_em >= (CURRENT_DATE - INTERVAL {$days} DAY)
         GROUP BY categoria
         ORDER BY total DESC
         LIMIT 6"
    );
    $rows = $statement->fetchAll();
    $max = 0.0;
    foreach ($rows as $row) {
        $max = max($max, (float) ($row['total'] ?? 0));
    }

    return array_map(static function (array $row) use ($max): array {
        $total = (float) ($row['total'] ?? 0);
        return [
            'categoria' => (string) ($row['categoria'] ?? 'Sem categoria'),
            'pedidos' => (int) ($row['pedidos'] ?? 0),
            'quantidade' => (int) ($row['quantidade'] ?? 0),
            'total' => $total,
            'percentual' => $max > 0 ? max(8, (int) round(($total / $max) * 100)) : 0,
        ];
    }, $rows);
}

function dashboard_recent_orders(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $statement = db()->query(
        "SELECT id, codigo, cliente_nome, cliente_contato, origem, status, forma_pagamento, recebimento, total, criado_em
         FROM pedidos
         ORDER BY criado_em DESC, id DESC
         LIMIT {$limit}"
    );

    return $statement->fetchAll();
}

function dashboard_low_stock_products(int $limit = 4): array
{
    $limit = max(1, min(20, $limit));
    $statement = db()->query(
        "SELECT p.id, p.nome, p.sku, p.estoque, p.estoque_minimo, c.nome AS categoria_nome
         FROM produtos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.removido_em IS NULL
           AND p.estoque <= p.estoque_minimo
         ORDER BY p.estoque ASC, p.nome ASC
         LIMIT {$limit}"
    );

    return $statement->fetchAll();
}

function dashboard_alerts(array $summary, array $lowStockProducts): array
{
    $alerts = [];

    if ($summary['pix_pendente'] > 0) {
        $alerts[] = [
            'title' => 'Pix pendente',
            'text' => $summary['pix_pendente'] . ' pedido(s) aguardam confirmação manual no painel.',
            'class' => 'admin-alert-warning',
        ];
    }

    if ($summary['pedidos_pendentes'] > 0) {
        $alerts[] = [
            'title' => 'Fila de pedidos',
            'text' => $summary['pedidos_pendentes'] . ' pedido(s) ainda precisam de acompanhamento operacional.',
            'class' => 'admin-alert-info',
        ];
    }

    if (!empty($lowStockProducts)) {
        $names = array_map(static fn(array $product): string => (string) $product['nome'], array_slice($lowStockProducts, 0, 2));
        $alerts[] = [
            'title' => 'Estoque crítico',
            'text' => implode(' e ', $names) . ' precisam de reposição.',
            'class' => 'admin-alert-danger',
        ];
    }

    if (empty($alerts)) {
        $alerts[] = [
            'title' => 'Operação estável',
            'text' => 'Sem Pix pendente, fila crítica ou estoque baixo no momento.',
            'class' => 'admin-alert-success',
        ];
    }

    return $alerts;
}
