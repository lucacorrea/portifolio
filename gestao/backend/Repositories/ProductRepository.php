<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ProductRepository
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
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.nome AS name,
                p.sku,
                p.codigo_barras AS barcode,
                COALESCE(c.nome, "Sem categoria") AS category,
                p.lote AS lot,
                DATE_FORMAT(p.validade, "%Y-%m-%d") AS expiry,
                p.quantidade AS stock,
                p.estoque_minimo AS minStock,
                p.preco_custo AS cost,
                p.preco_venda AS price,
                COALESCE(NULLIF(p.imagem, ""), "prod-placeholder.svg") AS image
            FROM produtos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.empresa_id = :empresa_id AND p.ativo = 1
            ORDER BY p.nome ASC
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        $products = $stmt->fetchAll();

        return array_map(static fn (array $product): array => [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'] ?? '',
            'barcode' => $product['barcode'] ?? '',
            'category' => $product['category'],
            'lot' => $product['lot'] ?? '',
            'expiry' => $product['expiry'] ?? '',
            'stock' => (float)$product['stock'],
            'minStock' => (float)$product['minStock'],
            'cost' => (float)$product['cost'],
            'price' => (float)$product['price'],
            'image' => $product['image'],
        ], $products);
    }
}
