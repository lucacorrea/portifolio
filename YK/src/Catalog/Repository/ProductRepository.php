<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\DTO\ProductFormData;
use App\Catalog\Entity\Product;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ProductRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return Product[] */
    public function findAll(array $filters = []): array
    {
        $where = ['excluido_em IS NULL'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $where[] = '(codigo LIKE :search_code OR nome LIKE :search_name OR descricao LIKE :search_description OR fabricante LIKE :search_manufacturer OR ncm LIKE :search_ncm OR codigo_barras LIKE :search_barcode)';
            $params['search_code'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
            $params['search_description'] = '%' . $search . '%';
            $params['search_manufacturer'] = '%' . $search . '%';
            $params['search_ncm'] = '%' . $search . '%';
            $params['search_barcode'] = '%' . $search . '%';
        }

        foreach (['category' => 'categoria', 'status' => 'status'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));

            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $stockSituation = (string) ($filters['stock_situation'] ?? '');

        if ($stockSituation === 'sem_estoque') {
            $where[] = 'estoque <= 0';
        } elseif ($stockSituation === 'estoque_baixo') {
            $where[] = 'estoque > 0 AND estoque <= estoque_minimo';
        } elseif ($stockSituation === 'em_estoque') {
            $where[] = 'estoque > estoque_minimo';
        }

        $sql = 'SELECT id, codigo, nome, descricao, categoria, fabricante, unidade,
                       ncm, codigo_barras, preco_custo, preco_venda, estoque, estoque_minimo,
                       localizacao, status, criado_em, atualizado_em
                  FROM produtos';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY nome ASC, id ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn(array $row): Product => Product::fromArray($row),
            $statement->fetchAll()
        );
    }

    public function findById(int $id): ?Product
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, codigo, nome, descricao, categoria, fabricante, unidade,
                    ncm, codigo_barras, preco_custo, preco_venda, estoque, estoque_minimo,
                    localizacao, status, criado_em, atualizado_em
               FROM produtos
              WHERE id = :id
                AND excluido_em IS NULL
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Product::fromArray($row);
    }

    public function findByIdForUpdate(int $id): ?Product
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, codigo, nome, descricao, categoria, fabricante, unidade,
                    ncm, codigo_barras, preco_custo, preco_venda, estoque, estoque_minimo,
                    localizacao, status, criado_em, atualizado_em
               FROM produtos
              WHERE id = :id
                AND excluido_em IS NULL
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Product::fromArray($row);
    }

    /** @return array{total:int,active:int,low_stock:int,out_of_stock:int} */
    public function summary(): array
    {
        $statement = $this->connection->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN estoque > 0 AND estoque <= estoque_minimo THEN 1 ELSE 0 END) AS low_stock,
                    SUM(CASE WHEN estoque <= 0 THEN 1 ELSE 0 END) AS out_of_stock
               FROM produtos
              WHERE excluido_em IS NULL"
        );
        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
        ];
    }

    public function create(ProductFormData $data): Product
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO produtos
                    (nome, descricao, categoria, fabricante, unidade, ncm, codigo_barras,
                     preco_custo, preco_venda, estoque, estoque_minimo, localizacao, status)
                 VALUES
                    (:name, :description, :category, :manufacturer, :unit, :ncm, :barcode,
                     :cost_price, :sale_price, :stock, :minimum_stock, :location, :status)'
            );
            $this->bindForm($statement, $data);
            $statement->execute();

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);
            $code = sprintf('PRD-%06d', $id);

            $update = $this->connection->prepare(
                'UPDATE produtos SET codigo = :code WHERE id = :id'
            );
            $update->execute(['id' => $id, 'code' => $code]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        $product = $this->findById($id);

        if ($product === null) {
            throw new InvalidArgumentException('Produto não encontrado após cadastro.');
        }

        return $product;
    }

    public function update(int $id, ProductFormData $data): void
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'UPDATE produtos
                SET nome = :name,
                    descricao = :description,
                    categoria = :category,
                    fabricante = :manufacturer,
                    unidade = :unit,
                    ncm = :ncm,
                    codigo_barras = :barcode,
                    preco_custo = :cost_price,
                    preco_venda = :sale_price,
                    estoque = :stock,
                    estoque_minimo = :minimum_stock,
                    localizacao = :location,
                    status = :status
              WHERE id = :id
                AND excluido_em IS NULL'
        );
        $statement->bindValue('id', $id);
        $this->bindForm($statement, $data);
        $statement->execute();
    }

    public function existsByBarcode(string $barcode, ?int $ignoreId = null): bool
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM produtos WHERE codigo_barras = :barcode';
        $params = ['barcode' => $barcode];

        if ($ignoreId !== null) {
            $this->assertPositiveId($ignoreId);
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->assertPositiveId($id);
        $this->assertPositiveId($userId);
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'SELECT id, estoque, excluido_em
                   FROM produtos
                  WHERE id = :id
                  FOR UPDATE'
            );
            $statement->execute(['id' => $id]);
            $product = $statement->fetch();

            if ($product === false || $product['excluido_em'] !== null) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }
            if (abs((float) $product['estoque']) >= 0.0005) {
                throw new InvalidArgumentException('Produto com saldo não pode ser excluído. Marque-o como inativo.');
            }
            if ($this->hasOperationalHistory($id)) {
                throw new InvalidArgumentException('Produto já utilizado não pode ser excluído. Marque-o como inativo para preservar o histórico.');
            }

            $update = $this->connection->prepare(
                'UPDATE produtos
                    SET status = "inativo",
                        excluido_em = CURRENT_TIMESTAMP,
                        excluido_por = :user_id,
                        motivo_exclusao = NULL
                  WHERE id = :id
                    AND excluido_em IS NULL'
            );
            $update->execute(['id' => $id, 'user_id' => $userId]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function hasOperationalHistory(int $id): bool
    {
        $checks = [
            "SELECT 1 FROM ordem_servico_itens WHERE tipo = 'produto' AND referencia_id = :id LIMIT 1",
            "SELECT 1 FROM orcamento_itens WHERE tipo = 'produto' AND referencia_id = :id LIMIT 1",
            'SELECT 1 FROM estoque_autorizacoes WHERE produto_id = :id LIMIT 1',
            'SELECT 1 FROM estoque_movimentacoes WHERE produto_id = :id LIMIT 1',
            'SELECT 1 FROM venda_avulsa_itens WHERE produto_id = :id LIMIT 1',
        ];

        foreach ($checks as $sql) {
            $statement = $this->connection->prepare($sql);
            $statement->execute(['id' => $id]);
            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    private function bindForm(\PDOStatement $statement, ProductFormData $data): void
    {
        $statement->bindValue('name', $data->name());
        $statement->bindValue('description', $data->description());
        $statement->bindValue('category', $data->category());
        $statement->bindValue('manufacturer', $data->manufacturer());
        $statement->bindValue('unit', $data->unit());
        $statement->bindValue('ncm', $data->ncm());
        $statement->bindValue('barcode', $data->barcode());
        $statement->bindValue('cost_price', $data->costPrice());
        $statement->bindValue('sale_price', $data->salePrice());
        $statement->bindValue('stock', $data->stock());
        $statement->bindValue('minimum_stock', $data->minimumStock());
        $statement->bindValue('location', $data->location());
        $statement->bindValue('status', $data->status());
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID inválido.');
        }
    }
}
