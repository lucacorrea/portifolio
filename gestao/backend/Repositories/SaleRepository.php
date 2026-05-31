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
        $stmt = $this->db->prepare(
            'SELECT
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
             LEFT JOIN cliente_contas cc ON cc.venda_id = v.id
             WHERE v.empresa_id = :empresa_id
             ORDER BY v.criado_em DESC, v.id DESC'
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        $sales = $stmt->fetchAll();

        if (!$sales) {
            return [];
        }

        $itemsBySale = $this->getItemsBySale(array_column($sales, 'id'));

        return array_map(function (array $sale) use ($itemsBySale): array {
            $createdAt = new \DateTimeImmutable((string)$sale['criado_em']);

            return [
                'id' => (int)$sale['id'],
                'number' => $sale['numero_venda'],
                'date' => $createdAt->format('Y-m-d'),
                'time' => $createdAt->format('H:i'),
                'seller' => $sale['seller'],
                'customer' => $sale['customer'],
                'customerPhone' => $sale['customerPhone'],
                'payment' => $this->formatPayment((string)$sale['payment']),
                'status' => $this->formatStatus((string)$sale['status']),
                'subtotal' => (float)$sale['subtotal'],
                'discount' => (float)$sale['desconto'],
                'addition' => (float)$sale['acrescimo'],
                'total' => (float)$sale['total'],
                'paid' => (float)$sale['paid'],
                'change' => (float)$sale['changeValue'],
                'due' => $sale['due'] ?? '',
                'device' => '',
                'items' => $itemsBySale[(int)$sale['id']] ?? [],
                'audit' => [
                    'createdBy' => $sale['seller'],
                    'createdAt' => $createdAt->format('d/m/Y') . ' às ' . $createdAt->format('H:i'),
                    'lastChange' => $sale['atualizado_em'] ? (new \DateTimeImmutable((string)$sale['atualizado_em']))->format('d/m/Y H:i') : 'Nenhuma',
                ],
            ];
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
