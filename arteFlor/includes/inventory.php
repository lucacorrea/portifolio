<?php
require_once __DIR__ . '/products.php';

function inventory_movement_type_options(): array
{
    return [
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        'ajuste' => 'Ajuste de saldo',
        'perda' => 'Perda',
        'reserva' => 'Reserva',
    ];
}

function inventory_origin_options(): array
{
    return [
        'compra' => 'Compra',
        'venda' => 'Venda',
        'correcao_interna' => 'Correção interna',
        'montagem_kit' => 'Montagem de kit',
        'encomenda' => 'Encomenda',
        'outro' => 'Outro',
    ];
}

function inventory_status_label(string $status): string
{
    return match ($status) {
        'pendente' => 'Pendente',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function inventory_type_badge_class(string $type): string
{
    return match ($type) {
        'entrada' => 'admin-badge-ok',
        'saida', 'reserva' => 'admin-badge-info',
        'perda' => 'admin-badge-danger',
        default => 'admin-badge-warn',
    };
}

function inventory_available_products(): array
{
    return db()->query(
        'SELECT id, sku, nome, estoque, estoque_minimo, status
         FROM produtos
         WHERE removido_em IS NULL
         ORDER BY nome ASC'
    )->fetchAll();
}

function inventory_stats(): array
{
    $row = db()->query(
        "SELECT
            SUM(CASE WHEN tipo = 'entrada' AND status = 'concluido' AND movimentado_em >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN quantidade ELSE 0 END) AS entradas_mes,
            SUM(CASE WHEN tipo IN ('saida', 'reserva') AND status = 'concluido' AND movimentado_em >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN quantidade ELSE 0 END) AS saidas_mes,
            SUM(CASE WHEN tipo = 'perda' AND status = 'concluido' AND movimentado_em >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN quantidade ELSE 0 END) AS perdas_mes,
            COUNT(*) AS movimentacoes_total
         FROM estoque_movimentacoes"
    )->fetch() ?: [];

    $products = product_stats();

    return [
        'entradas_mes' => (int) ($row['entradas_mes'] ?? 0),
        'saidas_mes' => (int) ($row['saidas_mes'] ?? 0),
        'perdas_mes' => (int) ($row['perdas_mes'] ?? 0),
        'movimentacoes_total' => (int) ($row['movimentacoes_total'] ?? 0),
        'estoque_baixo' => (int) ($products['estoque_baixo'] ?? 0),
    ];
}

function inventory_low_stock_products(int $limit = 6): array
{
    $limit = max(1, min(30, $limit));

    return db()->query(
        "SELECT id, nome, sku, estoque, estoque_minimo, status
         FROM produtos
         WHERE removido_em IS NULL
           AND estoque <= estoque_minimo
         ORDER BY estoque ASC, nome ASC
         LIMIT {$limit}"
    )->fetchAll();
}

function inventory_recent_movements(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));

    return db()->query(
        "SELECT
            m.*,
            p.nome AS produto_nome,
            p.sku AS produto_sku,
            u.nome AS usuario_nome
         FROM estoque_movimentacoes m
         INNER JOIN produtos p ON p.id = m.produto_id
         LEFT JOIN usuarios_admin u ON u.id = m.usuario_admin_id
         ORDER BY m.movimentado_em DESC, m.id DESC
         LIMIT {$limit}"
    )->fetchAll();
}

function inventory_normalize_datetime(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return date('Y-m-d H:i:s');
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $value .= ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        $value = str_replace('T', ' ', $value) . ':00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
        $value = str_replace('T', ' ', $value);
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException('Informe uma data de movimentação válida.');
    }

    return $value;
}

function inventory_calculate_new_stock(string $type, int $currentStock, int $amount): array
{
    $newStock = match ($type) {
        'entrada' => $currentStock + $amount,
        'saida', 'perda', 'reserva' => $currentStock - $amount,
        'ajuste' => $amount,
        default => throw new InvalidArgumentException('Tipo de movimentação inválido.'),
    };

    if ($type !== 'ajuste' && $amount <= 0) {
        throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
    }

    if ($newStock < 0) {
        throw new InvalidArgumentException('A movimentação deixaria o estoque negativo.');
    }

    $movementAmount = $type === 'ajuste' ? abs($newStock - $currentStock) : $amount;
    if ($movementAmount <= 0) {
        throw new InvalidArgumentException('O ajuste precisa alterar o saldo atual.');
    }

    return [$newStock, $movementAmount];
}

function inventory_save_movement_from_request(array $adminUser): int
{
    $productId = (int) ($_POST['produto_id'] ?? 0);
    $type = (string) ($_POST['tipo'] ?? '');
    $origin = (string) ($_POST['origem'] ?? 'outro');
    $amount = (int) ($_POST['quantidade'] ?? 0);
    $cost = product_normalize_money($_POST['custo_unitario'] ?? 0);
    $reason = trim((string) ($_POST['motivo'] ?? ''));
    $movedAt = inventory_normalize_datetime((string) ($_POST['movimentado_em'] ?? ''));

    if ($productId <= 0) {
        throw new InvalidArgumentException('Selecione um produto.');
    }
    if (!array_key_exists($type, inventory_movement_type_options())) {
        throw new InvalidArgumentException('Tipo de movimentação inválido.');
    }
    if (!array_key_exists($origin, inventory_origin_options())) {
        throw new InvalidArgumentException('Origem inválida.');
    }
    if ($reason !== '' && strlen($reason) > 5000) {
        throw new InvalidArgumentException('O motivo está muito longo.');
    }

    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT id, nome, sku, estoque, status
             FROM produtos
             WHERE id = :id AND removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();

        if (!$product) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $previousStock = (int) $product['estoque'];
        [$newStock, $movementAmount] = inventory_calculate_new_stock($type, $previousStock, $amount);
        $nextStatus = $newStock <= 0 ? 'sem_estoque' : 'disponivel';

        db()->prepare(
            'UPDATE produtos
             SET estoque = :estoque,
                 status = CASE
                    WHEN status IN ("disponivel", "sem_estoque") THEN :status
                    ELSE status
                 END
             WHERE id = :id'
        )->execute([
            'id' => $productId,
            'estoque' => $newStock,
            'status' => $nextStatus,
        ]);

        db()->prepare(
            'INSERT INTO estoque_movimentacoes (
                produto_id, usuario_admin_id, tipo, origem, quantidade,
                estoque_anterior, estoque_novo, custo_unitario,
                responsavel_nome, motivo, status, movimentado_em
             ) VALUES (
                :produto_id, :usuario_admin_id, :tipo, :origem, :quantidade,
                :estoque_anterior, :estoque_novo, :custo_unitario,
                :responsavel_nome, :motivo, "concluido", :movimentado_em
             )'
        )->execute([
            'produto_id' => $productId,
            'usuario_admin_id' => (int) ($adminUser['id'] ?? 0) ?: null,
            'tipo' => $type,
            'origem' => $origin,
            'quantidade' => $movementAmount,
            'estoque_anterior' => $previousStock,
            'estoque_novo' => $newStock,
            'custo_unitario' => $cost > 0 ? $cost : null,
            'responsavel_nome' => substr((string) ($adminUser['nome'] ?? 'Admin'), 0, 140),
            'motivo' => $reason !== '' ? $reason : null,
            'movimentado_em' => $movedAt,
        ]);

        $movementId = (int) db()->lastInsertId();
        db()->commit();

        return $movementId;
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}
