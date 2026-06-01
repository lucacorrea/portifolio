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
        $itemsBySale = $this->getItemsBySale([(int) $sale['id']]);
        $mapped['items'] = $itemsBySale[(int) $sale['id']] ?? [];

        return $mapped;
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

        return (int) $this->db->lastInsertId();
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

        return (int) $this->db->lastInsertId();
    }

    public function cancel(int $empresaId, int $id, int $usuarioId, string $reason): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vendas
             SET status = "cancelada",
                 motivo_cancelamento = :motivo,
                 cancelada_por = :usuario_id,
                 cancelada_em = NOW()
             WHERE empresa_id = :empresa_id AND id = :id AND status <> "cancelada"'
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
                u.nome AS seller,
                COALESCE(c.nome, "Venda balcão") AS customer,
                COALESCE(c.telefone, "") AS customerPhone,
                COALESCE(p.metodo, "") AS payment,
                COALESCE(p.valor, 0) AS paid,
                COALESCE(p.valor_recebido, 0) AS received,
                COALESCE(p.troco, 0) AS changeValue,
                cc.vencimento AS due
             FROM vendas v
             INNER JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             LEFT JOIN pagamentos p ON p.venda_id = v.id
             LEFT JOIN cliente_contas cc ON cc.venda_id = v.id';
    }

    private function mapSales(array $sales): array
    {
        if (!$sales) {
            return [];
        }

        $itemsBySale = $this->getItemsBySale(array_column($sales, 'id'));

        return array_map(function (array $sale) use ($itemsBySale): array {
            $mapped = $this->mapSale($sale);
            $mapped['items'] = $itemsBySale[(int) $sale['id']] ?? [];

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
            $items[(int) $item['venda_id']][] = [
                'name' => $item['produto_nome'],
                'lot' => $item['lote'] ?? '',
                'expiry' => $item['validade'] ?? '',
                'qty' => (float) $item['quantidade'],
                'unit' => (float) $item['preco_unitario'],
            ];
        }

        return $items;
    }

    private function mapSale(array $sale): array
    {
        $createdAt = new \DateTimeImmutable((string) $sale['criado_em']);

        return [
            'id' => (int) $sale['id'],
            'number' => $sale['numero_venda'],
            'date' => $createdAt->format('Y-m-d'),
            'time' => $createdAt->format('H:i'),
            'seller' => $sale['seller'],
            'customer' => $sale['customer'],
            'customerPhone' => $sale['customerPhone'],
            'payment' => $this->formatPayment((string) $sale['payment']),
            'status' => $this->formatStatus((string) $sale['status']),
            'subtotal' => (float) $sale['subtotal'],
            'discount' => (float) $sale['desconto'],
            'addition' => (float) $sale['acrescimo'],
            'total' => (float) $sale['total'],
            'paid' => (float) $sale['paid'],
            'change' => (float) $sale['changeValue'],
            'due' => $sale['due'] ?? '',
            'device' => '',
            'items' => [],
            'audit' => [
                'createdBy' => $sale['seller'],
                'createdAt' => $createdAt->format('d/m/Y') . ' às ' . $createdAt->format('H:i'),
                'lastChange' => $sale['atualizado_em'] ? (new \DateTimeImmutable((string) $sale['atualizado_em']))->format('d/m/Y H:i') : 'Nenhuma',
            ],
        ];
    }

    private function formatPayment(string $payment): string
    {
        return [
            'pix' => 'PIX',
            'credito' => 'Crédito',
            'debito' => 'Débito',
            'dinheiro' => 'Dinheiro',
            'conta_cliente' => 'Conta do cliente',
            'misto' => 'Misto',
        ][$payment] ?? 'Não informado';
    }

    private function formatStatus(string $status): string
    {
        return [
            'finalizada' => 'Finalizada',
            'pendente' => 'Pendente',
            'cancelada' => 'Cancelada',
            'em_aberto' => 'Em aberto',
        ][$status] ?? ucfirst($status);
    }
}
