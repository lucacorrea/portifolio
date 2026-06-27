<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SaleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function findAll(int $empresaId): array
    {
        $stmt = $this->db->prepare($this->baseSelect() . '
             WHERE v.empresa_id = :empresa_id
             ORDER BY v.criado_em DESC, v.id DESC'
        );

        $stmt->execute([':empresa_id' => $empresaId]);
        $sales = $stmt->fetchAll();

        return $this->mapSales($sales);
    }

    public function historySummary(int $empresaId, array $filters = []): array
    {
        $params = [];
        $where = $this->buildHistoryWhere($empresaId, $filters, $params);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(v.id) AS sales_count,

                COALESCE(SUM(CASE
                    WHEN v.status <> 'cancelada'
                    THEN v.total ELSE 0
                END), 0) AS total_sales,

                COALESCE(AVG(CASE
                    WHEN v.status <> 'cancelada'
                    THEN v.total ELSE NULL
                END), 0) AS average_ticket,

                COUNT(CASE
                    WHEN v.status = 'finalizada'
                    THEN 1
                END) AS finalized_count,

                COUNT(CASE
                    WHEN v.status = 'cancelada'
                    THEN 1
                END) AS canceled_count,

                COUNT(CASE
                    WHEN v.status = 'pendente'
                    THEN 1
                END) AS pending_count,

                COALESCE(SUM(CASE
                    WHEN v.status = 'cancelada'
                    THEN v.total ELSE 0
                END), 0) AS canceled_total
            FROM vendas v
            LEFT JOIN clientes c
                   ON c.id = v.cliente_id
                  AND c.empresa_id = v.empresa_id
            LEFT JOIN usuarios u
                   ON u.id = v.usuario_id
            WHERE {$where}
        ");

        $this->bindHistoryParams($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'sales_count' => (int)($row['sales_count'] ?? 0),
            'total_sales' => (float)($row['total_sales'] ?? 0),
            'average_ticket' => (float)($row['average_ticket'] ?? 0),
            'finalized_count' => (int)($row['finalized_count'] ?? 0),
            'canceled_count' => (int)($row['canceled_count'] ?? 0),
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'canceled_total' => (float)($row['canceled_total'] ?? 0),
        ];
    }

    public function history(int $empresaId, array $filters = []): array
    {
        $params = [];
        $where = $this->buildHistoryWhere($empresaId, $filters, $params);

        $limit = filter_var($filters['limit'] ?? 100, FILTER_VALIDATE_INT, [
            'options' => [
                'default' => 100,
                'min_range' => 1,
                'max_range' => 300,
            ],
        ]);

        $offset = filter_var($filters['offset'] ?? 0, FILTER_VALIDATE_INT, [
            'options' => [
                'default' => 0,
                'min_range' => 0,
            ],
        ]);

        $stmt = $this->db->prepare("
            SELECT
                v.id,
                v.empresa_id,
                v.cliente_id,
                v.usuario_id,
                v.numero_venda,
                v.status,
                v.subtotal,
                v.desconto,
                v.acrescimo,
                v.total,
                v.criado_em,
                v.atualizado_em,

                COALESCE(c.nome, 'Venda balcão') AS cliente_nome,
                COALESCE(c.telefone, '') AS cliente_telefone,
                COALESCE(c.cpf_cnpj, '') AS cliente_documento,

                COALESCE(u.nome, 'Operador') AS operador_nome,
                COALESCE(u.email, '') AS operador_email,

                COALESCE((
                    SELECT p1.metodo
                    FROM pagamentos p1
                    WHERE p1.venda_id = v.id
                    ORDER BY p1.id ASC
                    LIMIT 1
                ), '') AS forma_pagamento,

                (
                    SELECT p1.parcelas
                    FROM pagamentos p1
                    WHERE p1.venda_id = v.id
                    ORDER BY p1.id ASC
                    LIMIT 1
                ) AS parcelas,

                COALESCE((
                    SELECT SUM(p2.valor)
                    FROM pagamentos p2
                    WHERE p2.venda_id = v.id
                ), 0) AS valor_pago,

                COALESCE((
                    SELECT SUM(p3.valor_recebido)
                    FROM pagamentos p3
                    WHERE p3.venda_id = v.id
                ), 0) AS valor_recebido,

                COALESCE((
                    SELECT SUM(p4.troco)
                    FROM pagamentos p4
                    WHERE p4.venda_id = v.id
                ), 0) AS troco,

                (
                    SELECT COUNT(*)
                    FROM venda_itens vi_count
                    WHERE vi_count.venda_id = v.id
                ) AS itens_count,

                cc.vencimento AS vencimento_conta
            FROM vendas v
            LEFT JOIN clientes c
                   ON c.id = v.cliente_id
                  AND c.empresa_id = v.empresa_id
            LEFT JOIN usuarios u
                   ON u.id = v.usuario_id
            LEFT JOIN cliente_contas cc
                   ON cc.venda_id = v.id
                  AND cc.empresa_id = v.empresa_id
            WHERE {$where}
            ORDER BY v.criado_em DESC, v.id DESC
            LIMIT :limit
            OFFSET :offset
        ");

        $this->bindHistoryParams($stmt, $params);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapHistorySale'], $stmt->fetchAll());
    }

    public function findById(int $empresaId, int $id): ?array
    {
        $stmt = $this->db->prepare($this->baseSelect() . '
             WHERE v.empresa_id = :empresa_id AND v.id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        $sale = $stmt->fetch();

        if (!$sale) {
            return null;
        }

        $mapped = $this->mapSale($sale);
        $itemsBySale = $this->getItemsBySale([(int)$sale['id']]);
        $mapped['items'] = $itemsBySale[(int)$sale['id']] ?? [];

        return $mapped;
    }

    public function findSaleById(int $empresaId, int $vendaId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                v.id,
                v.empresa_id,
                v.cliente_id,
                v.usuario_id,
                v.numero_venda,
                v.status,
                v.subtotal,
                v.desconto,
                v.acrescimo,
                v.total,
                v.motivo_cancelamento,
                v.cancelada_em,
                v.criado_em,
                v.atualizado_em,
                COALESCE(p.metodo, \'\') AS forma_pagamento,
                p.parcelas AS parcelas,
                COALESCE(p.valor_recebido, 0) AS valor_recebido,
                COALESCE(p.troco, 0) AS troco,
                COALESCE(p.valor, 0) AS valor_pago,
                COALESCE(p.status, \'\') AS pagamento_status,
                COALESCE(c.nome, \'Cliente não informado\') AS cliente_nome,
                COALESCE(c.telefone, \'\') AS cliente_telefone,
                COALESCE(c.cpf_cnpj, \'\') AS cliente_cpf_cnpj,
                COALESCE(c.endereco, \'\') AS cliente_endereco,
                COALESCE(u.nome, CONCAT(\'Usuário #\', v.usuario_id)) AS operador_nome,
                COALESCE(u.email, \'\') AS operador_email,
                \'\' AS observacao
            FROM vendas v
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            LEFT JOIN clientes c
                   ON c.id = v.cliente_id
                  AND c.empresa_id = v.empresa_id
            LEFT JOIN usuarios u
                   ON u.id = v.usuario_id
            WHERE v.empresa_id = :empresa_id
              AND v.id = :venda_id
            LIMIT 1
        ');

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':venda_id' => $vendaId,
        ]);

        $sale = $stmt->fetch();

        return $sale ?: null;
    }

    public function findSaleItems(int $empresaId, int $vendaId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                vi.id,
                vi.venda_id,
                vi.produto_id,
                vi.produto_nome,
                vi.lote,
                DATE_FORMAT(vi.validade, \'%Y-%m-%d\') AS validade,
                vi.quantidade,
                vi.preco_unitario,
                vi.subtotal
            FROM venda_itens vi
            INNER JOIN vendas v
                    ON v.id = vi.venda_id
                   AND v.empresa_id = :empresa_id
            WHERE vi.venda_id = :venda_id
            ORDER BY vi.id ASC
        ');

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':venda_id' => $vendaId,
        ]);

        return $stmt->fetchAll();
    }

    public function create(int $empresaId, int $usuarioId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO vendas (
                empresa_id, usuario_id, cliente_id, numero_venda, status,
                subtotal, desconto, acrescimo, total
             )
             VALUES (
                :empresa_id, :usuario_id, :cliente_id, :numero_venda, :status,
                :subtotal, :desconto, :acrescimo, :total
             )'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':numero_venda' => $data['numero_venda'],
            ':status' => $data['status'],
            ':subtotal' => $data['subtotal'],
            ':desconto' => $data['desconto'],
            ':acrescimo' => $data['acrescimo'],
            ':total' => $data['total'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addItem(int $saleId, array $item): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO venda_itens (
                venda_id, produto_id, produto_nome, lote, validade, quantidade, preco_unitario, subtotal
             )
             VALUES (
                :venda_id, :produto_id, :produto_nome, :lote, :validade, :quantidade, :preco_unitario, :subtotal
             )'
        );

        $stmt->execute([
            ':venda_id' => $saleId,
            ':produto_id' => $item['produto_id'],
            ':produto_nome' => $item['produto_nome'],
            ':lote' => $item['lote'] ?: null,
            ':validade' => $item['validade'] ?: null,
            ':quantidade' => $item['quantidade'],
            ':preco_unitario' => $item['preco_unitario'],
            ':subtotal' => $item['subtotal'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function cancel(int $empresaId, int $id, int $usuarioId, string $reason): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vendas
             SET status = \'cancelada\',
                 motivo_cancelamento = :motivo,
                 cancelada_por = :usuario_id,
                 cancelada_em = NOW()
             WHERE empresa_id = :empresa_id
               AND id = :id
               AND status <> \'cancelada\''
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':usuario_id' => $usuarioId,
            ':motivo' => $reason,
        ]);
    }

    public function stockItemsForSale(int $empresaId, int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT vi.produto_id, vi.quantidade
             FROM venda_itens vi
             INNER JOIN vendas v ON v.id = vi.venda_id
             WHERE v.empresa_id = :empresa_id
               AND v.id = :id
               AND vi.produto_id IS NOT NULL'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        return $stmt->fetchAll();
    }

    private function buildHistoryWhere(int $empresaId, array $filters, array &$params): string
    {
        $clauses = ['v.empresa_id = :empresa_id'];
        $params[':empresa_id'] = $empresaId;

        $periodo = strtolower(trim((string)($filters['periodo'] ?? 'hoje')));

        if ($periodo === 'hoje') {
            $clauses[] = 'DATE(v.criado_em) = CURDATE()';
        } elseif ($periodo === 'ontem') {
            $clauses[] = 'DATE(v.criado_em) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
        } elseif ($periodo === 'semana') {
            $clauses[] = 'v.criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
            $clauses[] = 'v.criado_em < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        } elseif ($periodo === 'mes') {
            $clauses[] = 'v.criado_em >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')';
            $clauses[] = 'v.criado_em < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)';
        }

        $inicio = trim((string)($filters['inicio'] ?? ''));
        if ($this->isDate($inicio)) {
            $clauses[] = 'v.criado_em >= :inicio';
            $params[':inicio'] = $inicio . ' 00:00:00';
        }

        $fim = trim((string)($filters['fim'] ?? ''));
        if ($this->isDate($fim)) {
            $clauses[] = 'v.criado_em <= :fim';
            $params[':fim'] = $fim . ' 23:59:59';
        }

        $status = strtolower(trim((string)($filters['status'] ?? 'todos')));
        if ($status !== '' && $status !== 'todos') {
            $allowedStatus = ['finalizada', 'pendente', 'cancelada', 'em_aberto'];
            if (in_array($status, $allowedStatus, true)) {
                $clauses[] = 'v.status = :status';
                $params[':status'] = $status;
            }
        }

        $pagamento = strtolower(trim((string)($filters['pagamento'] ?? 'todos')));
        if ($pagamento !== '' && $pagamento !== 'todos') {
            if ($pagamento === 'cartao') {
                $clauses[] = "EXISTS (
                    SELECT 1
                    FROM pagamentos pf
                    WHERE pf.venda_id = v.id
                      AND pf.metodo IN ('credito', 'debito', 'cartao_credito', 'cartao_debito')
                )";
            } else {
                $allowedPayments = [
                    'pix',
                    'dinheiro',
                    'credito',
                    'debito',
                    'cartao_credito',
                    'cartao_debito',
                    'conta_cliente',
                    'misto',
                    'outro',
                ];

                if (in_array($pagamento, $allowedPayments, true)) {
                    $clauses[] = 'EXISTS (
                        SELECT 1
                        FROM pagamentos pf
                        WHERE pf.venda_id = v.id
                          AND pf.metodo = :pagamento
                    )';
                    $params[':pagamento'] = $pagamento;
                }
            }
        }

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $searchClauses = [
                'v.numero_venda LIKE :q_numero',
                'c.nome LIKE :q_cliente',
                'c.telefone LIKE :q_telefone',
                'c.cpf_cnpj LIKE :q_documento',
                'u.nome LIKE :q_operador',
                'EXISTS (
                    SELECT 1
                    FROM venda_itens viq
                    WHERE viq.venda_id = v.id
                      AND viq.produto_nome LIKE :q_produto
                )',
            ];

            if (ctype_digit($query)) {
                $searchClauses[] = 'v.id = :q_id';
                $params[':q_id'] = (int)$query;
            }

            $clauses[] = '(' . implode(' OR ', $searchClauses) . ')';

            $like = '%' . $query . '%';
            $params[':q_numero'] = $like;
            $params[':q_cliente'] = $like;
            $params[':q_telefone'] = $like;
            $params[':q_documento'] = $like;
            $params[':q_operador'] = $like;
            $params[':q_produto'] = $like;
        }

        return implode(' AND ', $clauses);
    }

    private function bindHistoryParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function baseSelect(): string
    {
        return 'SELECT
                v.id,
                v.numero_venda,
                v.status,
                v.subtotal,
                v.desconto,
                v.acrescimo,
                v.total,
                v.criado_em,
                v.atualizado_em,
                COALESCE(u.nome, \'Operador\') AS seller,
                COALESCE(c.nome, \'Venda balcão\') AS customer,
                COALESCE(c.telefone, \'\') AS customerPhone,
                COALESCE(p.metodo, \'\') AS payment,
                p.parcelas AS paymentInstallments,
                COALESCE(p.valor, 0) AS paid,
                COALESCE(p.valor_recebido, 0) AS received,
                COALESCE(p.troco, 0) AS changeValue,
                cc.vencimento AS due
             FROM vendas v
             LEFT JOIN usuarios u
                    ON u.id = v.usuario_id
             LEFT JOIN clientes c
                    ON c.id = v.cliente_id
                   AND c.empresa_id = v.empresa_id
             LEFT JOIN pagamentos p
                    ON p.venda_id = v.id
             LEFT JOIN cliente_contas cc
                    ON cc.venda_id = v.id
                   AND cc.empresa_id = v.empresa_id';
    }

    private function mapSales(array $sales): array
    {
        if (!$sales) {
            return [];
        }

        $itemsBySale = $this->getItemsBySale(array_column($sales, 'id'));

        return array_map(function (array $sale) use ($itemsBySale): array {
            $mapped = $this->mapSale($sale);
            $mapped['items'] = $itemsBySale[(int)$sale['id']] ?? [];

            return $mapped;
        }, $sales);
    }

    private function getItemsBySale(array $saleIds): array
    {
        $saleIds = array_values(array_filter(array_map('intval', $saleIds)));

        if (!$saleIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($saleIds), '?'));

        $stmt = $this->db->prepare(
            "SELECT venda_id, produto_nome, lote, DATE_FORMAT(validade, '%Y-%m-%d') AS validade, quantidade, preco_unitario
             FROM venda_itens
             WHERE venda_id IN ($placeholders)
             ORDER BY id ASC"
        );

        $stmt->execute($saleIds);

        $items = [];

        foreach ($stmt->fetchAll() as $item) {
            $items[(int)$item['venda_id']][] = [
                'name' => $item['produto_nome'],
                'lot' => $item['lote'] ?? '',
                'expiry' => $item['validade'] ?? '',
                'qty' => (float)$item['quantidade'],
                'unit' => (float)$item['preco_unitario'],
            ];
        }

        return $items;
    }

    private function mapSale(array $sale): array
    {
        $createdAt = new \DateTimeImmutable((string)$sale['criado_em']);

        return [
            'id' => (int)$sale['id'],
            'number' => $sale['numero_venda'],
            'date' => $createdAt->format('Y-m-d'),
            'time' => $createdAt->format('H:i'),
            'seller' => $sale['seller'],
            'customer' => $sale['customer'],
            'customerPhone' => $sale['customerPhone'],
            'payment' => $this->formatPaymentWithInstallments((string)$sale['payment'], $sale['paymentInstallments'] ?? null),
            'paymentInstallments' => $this->installmentsValue($sale['paymentInstallments'] ?? null),
            'status' => $this->formatStatus((string)$sale['status']),
            'subtotal' => (float)$sale['subtotal'],
            'discount' => (float)$sale['desconto'],
            'addition' => (float)$sale['acrescimo'],
            'total' => (float)$sale['total'],
            'paid' => (float)$sale['paid'],
            'change' => (float)$sale['changeValue'],
            'due' => $sale['due'] ?? '',
            'device' => '',
            'items' => [],
            'audit' => [
                'createdBy' => $sale['seller'],
                'createdAt' => $createdAt->format('d/m/Y') . ' às ' . $createdAt->format('H:i'),
                'lastChange' => $sale['atualizado_em']
                    ? (new \DateTimeImmutable((string)$sale['atualizado_em']))->format('d/m/Y H:i')
                    : 'Nenhuma',
            ],
        ];
    }

    private function mapHistorySale(array $sale): array
    {
        $createdAt = new \DateTimeImmutable((string)$sale['criado_em']);
        $paymentMethod = (string)($sale['forma_pagamento'] ?? '');
        $status = (string)($sale['status'] ?? '');

        return [
            'id' => (int)$sale['id'],
            'empresa_id' => (int)$sale['empresa_id'],
            'cliente_id' => $sale['cliente_id'] !== null ? (int)$sale['cliente_id'] : null,
            'usuario_id' => (int)$sale['usuario_id'],
            'numero_venda' => (string)$sale['numero_venda'],
            'status' => $status,
            'status_label' => $this->formatStatus($status),
            'subtotal' => (float)$sale['subtotal'],
            'desconto' => (float)$sale['desconto'],
            'acrescimo' => (float)$sale['acrescimo'],
            'total' => (float)$sale['total'],
            'criado_em' => (string)$sale['criado_em'],
            'data' => $createdAt->format('Y-m-d'),
            'hora' => $createdAt->format('H:i'),
            'data_br' => $createdAt->format('d/m/Y H:i'),
            'cliente_nome' => (string)$sale['cliente_nome'],
            'cliente_telefone' => (string)$sale['cliente_telefone'],
            'cliente_documento' => (string)$sale['cliente_documento'],
            'operador_nome' => (string)$sale['operador_nome'],
            'operador_email' => (string)$sale['operador_email'],
            'forma_pagamento' => $paymentMethod,
            'parcelas' => $this->installmentsValue($sale['parcelas'] ?? null),
            'forma_pagamento_label' => $this->formatPaymentWithInstallments($paymentMethod, $sale['parcelas'] ?? null),
            'valor_pago' => (float)$sale['valor_pago'],
            'valor_recebido' => (float)$sale['valor_recebido'],
            'troco' => (float)$sale['troco'],
            'itens_count' => (int)$sale['itens_count'],
            'vencimento_conta' => $sale['vencimento_conta'] ?? '',
        ];
    }

    private function formatPayment(string $payment): string
    {
        return [
            'pix' => 'PIX',
            'credito' => 'Crédito',
            'debito' => 'Débito',
            'cartao_credito' => 'Cartão de crédito',
            'cartao_debito' => 'Cartão de débito',
            'dinheiro' => 'Dinheiro',
            'conta_cliente' => 'Conta do cliente',
            'misto' => 'Misto',
            'outro' => 'Outro',
            '' => 'Não informado',
        ][$payment] ?? ucfirst(str_replace('_', ' ', $payment));
    }

    private function formatPaymentWithInstallments(string $payment, mixed $installments): string
    {
        $label = $this->formatPayment($payment);
        $installments = $this->installmentsValue($installments);

        if (!in_array($payment, ['credito', 'cartao_credito'], true) || $installments === null) {
            return $label;
        }

        return $installments === 1 ? $label . ' - à vista' : $label . ' - ' . $installments . 'x';
    }

    private function installmentsValue(mixed $value): ?int
    {
        $installments = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 12,
            ],
        ]);

        return $installments === false ? null : (int)$installments;
    }

    private function formatStatus(string $status): string
    {
        return [
            'finalizada' => 'Finalizada',
            'pendente' => 'Pendente',
            'cancelada' => 'Cancelada',
            'em_aberto' => 'Em aberto',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
