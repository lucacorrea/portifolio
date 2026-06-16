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

    public function findAll(int $empresaId, string $query = ''): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.nome AS name,
                p.sku,
                p.codigo_barras AS barcode,
                COALESCE(c.nome, \'Sem categoria\') AS category,
                p.lote AS lot,
                DATE_FORMAT(p.validade, \'%Y-%m-%d\') AS expiry,
                p.quantidade AS stock,
                p.estoque_minimo AS minStock,
                p.preco_custo AS cost,
                p.preco_venda AS price,
                COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image
            FROM produtos p
            LEFT JOIN categorias c
                   ON p.categoria_id = c.id
                  AND c.empresa_id = p.empresa_id
            WHERE p.empresa_id = :empresa_id
              AND p.ativo = 1
              AND (
                  :query = \'\'
                  OR p.nome LIKE :like_nome
                  OR p.sku LIKE :like_sku
                  OR p.codigo_barras LIKE :like_codigo
                  OR p.lote LIKE :like_lote
                  OR c.nome LIKE :like_categoria
              )
            ORDER BY p.nome ASC
        ');
        $like = '%' . trim($query) . '%';
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':query' => trim($query),
            ':like_nome' => $like,
            ':like_sku' => $like,
            ':like_codigo' => $like,
            ':like_lote' => $like,
            ':like_categoria' => $like,
        ]);
        $products = $stmt->fetchAll();

        return array_map([$this, 'mapProduct'], $products);
    }

    public function findById(int $empresaId, int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.nome AS name,
                p.sku,
                p.codigo_barras AS barcode,
                COALESCE(c.nome, \'Sem categoria\') AS category,
                p.lote AS lot,
                DATE_FORMAT(p.validade, \'%Y-%m-%d\') AS expiry,
                p.quantidade AS stock,
                p.estoque_minimo AS minStock,
                p.preco_custo AS cost,
                p.preco_venda AS price,
                COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image
            FROM produtos p
            LEFT JOIN categorias c
                   ON p.categoria_id = c.id
                  AND c.empresa_id = p.empresa_id
            WHERE p.empresa_id = :empresa_id
              AND p.id = :id
              AND p.ativo = 1
            LIMIT 1
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        $product = $stmt->fetch();

        return $product ? $this->mapProduct($product) : null;
    }

    public function findByCode(int $empresaId, string $code): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.nome AS name,
                p.sku,
                p.codigo_barras AS barcode,
                COALESCE(c.nome, \'Sem categoria\') AS category,
                p.lote AS lot,
                DATE_FORMAT(p.validade, \'%Y-%m-%d\') AS expiry,
                p.quantidade AS stock,
                p.estoque_minimo AS minStock,
                p.preco_custo AS cost,
                p.preco_venda AS price,
                COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image
            FROM produtos p
            LEFT JOIN categorias c
                   ON p.categoria_id = c.id
                  AND c.empresa_id = p.empresa_id
            WHERE p.empresa_id = :empresa_id
              AND p.ativo = 1
              AND (p.codigo_barras = :code_barcode OR p.sku = :code_sku)
            LIMIT 1
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':code_barcode' => trim($code),
            ':code_sku' => trim($code),
        ]);

        $product = $stmt->fetch();

        return $product ? $this->mapProduct($product) : null;
    }

    public function create(int $empresaId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO produtos (
                empresa_id, categoria_id, nome, sku, codigo_barras, lote, validade,
                quantidade, estoque_minimo, preco_custo, preco_venda, imagem
             )
             VALUES (
                :empresa_id, :categoria_id, :nome, :sku, :codigo_barras, :lote, :validade,
                :quantidade, :estoque_minimo, :preco_custo, :preco_venda, :imagem
             )'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':categoria_id' => $data['categoria_id'],
            ':nome' => $data['nome'],
            ':sku' => $data['sku'] ?: null,
            ':codigo_barras' => $data['codigo_barras'] ?: null,
            ':lote' => $data['lote'] ?: null,
            ':validade' => $data['validade'] ?: null,
            ':quantidade' => $data['quantidade'],
            ':estoque_minimo' => $data['estoque_minimo'],
            ':preco_custo' => $data['preco_custo'],
            ':preco_venda' => $data['preco_venda'],
            ':imagem' => $data['imagem'] ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $empresaId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos
             SET categoria_id = :categoria_id,
                 nome = :nome,
                 sku = :sku,
                 codigo_barras = :codigo_barras,
                 lote = :lote,
                 validade = :validade,
                 quantidade = :quantidade,
                 estoque_minimo = :estoque_minimo,
                 preco_custo = :preco_custo,
                 preco_venda = :preco_venda,
                 imagem = :imagem
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':categoria_id' => $data['categoria_id'],
            ':nome' => $data['nome'],
            ':sku' => $data['sku'] ?: null,
            ':codigo_barras' => $data['codigo_barras'] ?: null,
            ':lote' => $data['lote'] ?: null,
            ':validade' => $data['validade'] ?: null,
            ':quantidade' => $data['quantidade'],
            ':estoque_minimo' => $data['estoque_minimo'],
            ':preco_custo' => $data['preco_custo'],
            ':preco_venda' => $data['preco_venda'],
            ':imagem' => $data['imagem'] ?: null,
        ]);
    }

    public function inactivate(int $empresaId, int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos
             SET ativo = 0
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);
    }

    public function decreaseStock(int $empresaId, int $id, float $quantity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos
             SET quantidade = quantidade - :quantidade
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':quantidade' => $quantity,
        ]);
    }

    public function increaseStock(int $empresaId, int $id, float $quantity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos
             SET quantidade = quantidade + :quantidade
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':quantidade' => $quantity,
        ]);
    }

    private function mapProduct(array $product): array
    {
        return [
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
        ];
    }
}
