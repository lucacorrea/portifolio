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

    public function findAll(int $empresaId, string $query = '', ?bool $active = true): array
    {
        $query = trim($query);

        $sql = '
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
            COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image,
            p.descricao AS description,
            p.marca AS brand,
            p.unidade AS unit,
            p.quantidade_embalagem AS packageQuantity,
            p.ncm,
            p.cest,
            p.fabricante AS manufacturer,
            COALESCE(NULLIF(p.origem_dados, \'\'), \'manual\') AS source,
            p.url_imagem_origem AS externalImageUrl,
            p.ativo AS active
        FROM produtos p
        LEFT JOIN categorias c
               ON p.categoria_id = c.id
              AND c.empresa_id = p.empresa_id
        WHERE p.empresa_id = :empresa_id
    ';

        $params = [
            ':empresa_id' => $empresaId,
        ];

        if ($active !== null) {
            $sql .= ' AND p.ativo = :ativo';
            $params[':ativo'] = $active ? 1 : 0;
        }

        if ($query !== '') {
            $sql .= '
          AND (
              p.nome LIKE :like_nome
              OR p.sku LIKE :like_sku
              OR p.codigo_barras LIKE :like_codigo
              OR p.lote LIKE :like_lote
              OR c.nome LIKE :like_categoria
          )
        ';

            $like = '%' . $query . '%';

            $params[':like_nome'] = $like;
            $params[':like_sku'] = $like;
            $params[':like_codigo'] = $like;
            $params[':like_lote'] = $like;
            $params[':like_categoria'] = $like;
        }

        $sql .= ' ORDER BY p.nome ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $products = $stmt->fetchAll();

        return array_map([$this, 'mapProduct'], $products);
    }

    public function findById(int $empresaId, int $id): ?array
    {
        return $this->findByIdWithStatus($empresaId, $id, true);
    }

    public function findByIdWithStatus(int $empresaId, int $id, ?bool $active = true): ?array
    {
        $sql = '
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
            COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image,
            p.descricao AS description,
            p.marca AS brand,
            p.unidade AS unit,
            p.quantidade_embalagem AS packageQuantity,
            p.ncm,
            p.cest,
            p.fabricante AS manufacturer,
            COALESCE(NULLIF(p.origem_dados, \'\'), \'manual\') AS source,
            p.url_imagem_origem AS externalImageUrl,
            p.ativo AS active
            FROM produtos p
            LEFT JOIN categorias c
                   ON p.categoria_id = c.id
                  AND c.empresa_id = p.empresa_id
            WHERE p.empresa_id = :empresa_id
              AND p.id = :id
        ';

        $params = [
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ];

        if ($active !== null) {
            $sql .= ' AND p.ativo = :ativo';
            $params[':ativo'] = $active ? 1 : 0;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

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
            COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image,
            p.descricao AS description,
            p.marca AS brand,
            p.unidade AS unit,
            p.quantidade_embalagem AS packageQuantity,
            p.ncm,
            p.cest,
            p.fabricante AS manufacturer,
            COALESCE(NULLIF(p.origem_dados, \'\'), \'manual\') AS source,
            p.url_imagem_origem AS externalImageUrl
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

    public function findByBarcode(int $empresaId, string $barcode, ?int $ignoreProductId = null): ?array
    {
        $sql = '
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
                COALESCE(NULLIF(p.imagem, \'\'), \'prod-placeholder.svg\') AS image,
                p.descricao AS description,
                p.marca AS brand,
                p.unidade AS unit,
                p.quantidade_embalagem AS packageQuantity,
                p.ncm,
                p.cest,
                p.fabricante AS manufacturer,
                COALESCE(NULLIF(p.origem_dados, \'\'), \'manual\') AS source,
                p.url_imagem_origem AS externalImageUrl
            FROM produtos p
            LEFT JOIN categorias c
                   ON p.categoria_id = c.id
                  AND c.empresa_id = p.empresa_id
            WHERE p.empresa_id = :empresa_id
              AND p.ativo = 1
              AND p.codigo_barras = :codigo_barras
        ';

        $params = [
            ':empresa_id' => $empresaId,
            ':codigo_barras' => trim($barcode),
        ];

        if ($ignoreProductId !== null && $ignoreProductId > 0) {
            $sql .= ' AND p.id <> :ignore_id';
            $params[':ignore_id'] = $ignoreProductId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $product = $stmt->fetch();

        return $product ? $this->mapProduct($product) : null;
    }

    public function create(int $empresaId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO produtos (
                empresa_id, categoria_id, nome, sku, codigo_barras, lote, validade,
                quantidade, estoque_minimo, preco_custo, preco_venda, imagem,
                descricao, marca, unidade, quantidade_embalagem, ncm, cest,
                fabricante, origem_dados, url_imagem_origem
             )
             VALUES (
                :empresa_id, :categoria_id, :nome, :sku, :codigo_barras, :lote, :validade,
                :quantidade, :estoque_minimo, :preco_custo, :preco_venda, :imagem,
                :descricao, :marca, :unidade, :quantidade_embalagem, :ncm, :cest,
                :fabricante, :origem_dados, :url_imagem_origem
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
            ':descricao' => $data['descricao'] ?: null,
            ':marca' => $data['marca'] ?: null,
            ':unidade' => $data['unidade'] ?: null,
            ':quantidade_embalagem' => $data['quantidade_embalagem'] ?: null,
            ':ncm' => $data['ncm'] ?: null,
            ':cest' => $data['cest'] ?: null,
            ':fabricante' => $data['fabricante'] ?: null,
            ':origem_dados' => $data['origem_dados'] ?: 'manual',
            ':url_imagem_origem' => $data['url_imagem_origem'] ?: null,
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
                 imagem = :imagem,
                 descricao = :descricao,
                 marca = :marca,
                 unidade = :unidade,
                 quantidade_embalagem = :quantidade_embalagem,
                 ncm = :ncm,
                 cest = :cest,
                 fabricante = :fabricante,
                 origem_dados = :origem_dados,
                 url_imagem_origem = :url_imagem_origem
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
            ':descricao' => $data['descricao'] ?: null,
            ':marca' => $data['marca'] ?: null,
            ':unidade' => $data['unidade'] ?: null,
            ':quantidade_embalagem' => $data['quantidade_embalagem'] ?: null,
            ':ncm' => $data['ncm'] ?: null,
            ':cest' => $data['cest'] ?: null,
            ':fabricante' => $data['fabricante'] ?: null,
            ':origem_dados' => $data['origem_dados'] ?: 'manual',
            ':url_imagem_origem' => $data['url_imagem_origem'] ?: null,
        ]);
    }

    public function inactivate(int $empresaId, int $id): void
    {
        $this->setActive($empresaId, $id, false);
    }

    public function activate(int $empresaId, int $id): void
    {
        $this->setActive($empresaId, $id, true);
    }

    public function hasSaleHistory(int $empresaId, int $id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
               FROM venda_itens vi
               INNER JOIN vendas v ON v.id = vi.venda_id
              WHERE v.empresa_id = :empresa_id
                AND vi.produto_id = :id
              LIMIT 1'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function deleteInactive(int $empresaId, int $id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM produtos
              WHERE empresa_id = :empresa_id
                AND id = :id
                AND ativo = 0'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function setActive(int $empresaId, int $id, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE produtos
             SET ativo = :ativo
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':ativo' => $active ? 1 : 0,
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
            'description' => $product['description'] ?? '',
            'brand' => $product['brand'] ?? '',
            'unit' => $product['unit'] ?? '',
            'packageQuantity' => $product['packageQuantity'] ?? '',
            'ncm' => $product['ncm'] ?? '',
            'cest' => $product['cest'] ?? '',
            'manufacturer' => $product['manufacturer'] ?? '',
            'source' => $product['source'] ?? 'manual',
            'externalImageUrl' => $product['externalImageUrl'] ?? '',
            'active' => (int)($product['active'] ?? 1) === 1,
        ];
    }
}
