<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\DTO\ProductFormData;
use App\Catalog\Entity\Product;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

final class ProductRepository
{
    private const SELECT_COLUMNS = '
        id,
        codigo,
        nome,
        descricao,
        categoria,
        fabricante,
        unidade,

        ncm,
        cest,
        origem_mercadoria,
        cfop_padrao,
        cst_icms,
        csosn,
        cst_pis,
        cst_cofins,
        aliquota_icms,
        aliquota_pis,
        aliquota_cofins,
        gtin_tributavel,
        unidade_tributavel,
        cst_ibs_cbs,
        classificacao_tributaria_ibs_cbs,

        codigo_barras,
        preco_custo,
        preco_venda,
        estoque,
        estoque_minimo,
        localizacao,
        status,
        criado_em,
        atualizado_em
    ';

    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * @return Product[]
     */
    public function findAll(
        array $filters = []
    ): array {
        $where = [
            'excluido_em IS NULL',
        ];

        $params = [];

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if ($search !== '') {
            $where[] = '(
                codigo LIKE :search_code
                OR nome LIKE :search_name
                OR descricao LIKE :search_description
                OR fabricante LIKE :search_manufacturer
                OR ncm LIKE :search_ncm
                OR cfop_padrao LIKE :search_cfop
                OR codigo_barras LIKE :search_barcode
            )';

            $like = '%' . $search . '%';

            $params = [
                'search_code' => $like,
                'search_name' => $like,
                'search_description' => $like,
                'search_manufacturer' => $like,
                'search_ncm' => $like,
                'search_cfop' => $like,
                'search_barcode' => $like,
            ];
        }

        foreach (
            [
                'category' => 'categoria',
                'status' => 'status',
            ]
            as $key => $column
        ) {
            $value = trim(
                (string) (
                    $filters[$key]
                    ?? ''
                )
            );

            if ($value !== '') {
                $where[] =
                    $column
                    . ' = :'
                    . $key;

                $params[$key] = $value;
            }
        }

        $stockSituation =
            (string) (
                $filters['stock_situation']
                ?? ''
            );

        if (
            $stockSituation
            === 'sem_estoque'
        ) {
            $where[] = 'estoque <= 0';
        } elseif (
            $stockSituation
            === 'estoque_baixo'
        ) {
            $where[] =
                'estoque > 0
                 AND estoque <= estoque_minimo';
        } elseif (
            $stockSituation
            === 'em_estoque'
        ) {
            $where[] =
                'estoque > estoque_minimo';
        }

        $sql =
            'SELECT '
            . self::SELECT_COLUMNS
            . '
             FROM produtos
             WHERE '
            . implode(
                ' AND ',
                $where
            )
            . '
             ORDER BY
                nome ASC,
                id ASC';

        $statement =
            $this->connection->prepare(
                $sql
            );

        $statement->execute(
            $params
        );

        return array_map(
            static fn (
                array $row
            ): Product =>
                Product::fromArray($row),

            $statement->fetchAll()
        );
    }

    public function findById(
        int $id
    ): ?Product {
        $this->assertPositiveId($id);

        $statement =
            $this->connection->prepare(
                'SELECT '
                . self::SELECT_COLUMNS
                . '
                 FROM produtos
                 WHERE id = :id
                   AND excluido_em IS NULL
                 LIMIT 1'
            );

        $statement->execute([
            'id' => $id,
        ]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : Product::fromArray($row);
    }

    public function findByIdForUpdate(
        int $id
    ): ?Product {
        $this->assertPositiveId($id);

        $statement =
            $this->connection->prepare(
                'SELECT '
                . self::SELECT_COLUMNS
                . '
                 FROM produtos
                 WHERE id = :id
                   AND excluido_em IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );

        $statement->execute([
            'id' => $id,
        ]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : Product::fromArray($row);
    }

    /**
     * @return array{
     *     total:int,
     *     active:int,
     *     low_stock:int,
     *     out_of_stock:int
     * }
     */
    public function summary(): array
    {
        $statement =
            $this->connection->query(
                "SELECT
                    COUNT(*) AS total,

                    SUM(
                        CASE
                            WHEN status = 'ativo'
                            THEN 1
                            ELSE 0
                        END
                    ) AS active,

                    SUM(
                        CASE
                            WHEN estoque > 0
                             AND estoque <= estoque_minimo
                            THEN 1
                            ELSE 0
                        END
                    ) AS low_stock,

                    SUM(
                        CASE
                            WHEN estoque <= 0
                            THEN 1
                            ELSE 0
                        END
                    ) AS out_of_stock

                 FROM produtos

                 WHERE excluido_em IS NULL"
            );

        $row =
            $statement->fetch()
            ?: [];

        return [
            'total' =>
                (int) ($row['total'] ?? 0),

            'active' =>
                (int) ($row['active'] ?? 0),

            'low_stock' =>
                (int) ($row['low_stock'] ?? 0),

            'out_of_stock' =>
                (int) ($row['out_of_stock'] ?? 0),
        ];
    }

    public function create(
        ProductFormData $data
    ): Product {
        $this->connection
            ->beginTransaction();

        try {
            $statement =
                $this->connection->prepare(
                    'INSERT INTO produtos
                        (
                            nome,
                            descricao,
                            categoria,
                            fabricante,
                            unidade,

                            ncm,
                            cest,
                            origem_mercadoria,
                            cfop_padrao,
                            cst_icms,
                            csosn,
                            cst_pis,
                            cst_cofins,
                            aliquota_icms,
                            aliquota_pis,
                            aliquota_cofins,
                            gtin_tributavel,
                            unidade_tributavel,
                            cst_ibs_cbs,
                            classificacao_tributaria_ibs_cbs,

                            codigo_barras,
                            preco_custo,
                            preco_venda,
                            estoque,
                            estoque_minimo,
                            localizacao,
                            status
                        )
                     VALUES
                        (
                            :name,
                            :description,
                            :category,
                            :manufacturer,
                            :unit,

                            :ncm,
                            :cest,
                            :origin,
                            :default_cfop,
                            :icms_cst,
                            :csosn,
                            :pis_cst,
                            :cofins_cst,
                            :icms_rate,
                            :pis_rate,
                            :cofins_rate,
                            :tax_gtin,
                            :tax_unit,
                            :ibs_cbs_cst,
                            :ibs_cbs_classification,

                            :barcode,
                            :cost_price,
                            :sale_price,
                            :stock,
                            :minimum_stock,
                            :location,
                            :status
                        )'
                );

            $this->bindForm(
                $statement,
                $data
            );

            $statement->execute();

            $id = (int) (
                $this->connection
                    ->lastInsertId()
            );

            $this->assertPositiveId($id);

            $code = sprintf(
                'PRD-%06d',
                $id
            );

            $update =
                $this->connection->prepare(
                    'UPDATE produtos
                        SET codigo = :code
                      WHERE id = :id'
                );

            $update->execute([
                'id' => $id,
                'code' => $code,
            ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if (
                $this->connection
                    ->inTransaction()
            ) {
                $this->connection
                    ->rollBack();
            }

            throw $exception;
        }

        $product =
            $this->findById($id);

        if ($product === null) {
            throw new InvalidArgumentException(
                'Produto não encontrado após cadastro.'
            );
        }

        return $product;
    }

    public function update(
        int $id,
        ProductFormData $data
    ): void {
        $this->assertPositiveId($id);

        $statement =
            $this->connection->prepare(
                'UPDATE produtos
                    SET
                        nome = :name,
                        descricao = :description,
                        categoria = :category,
                        fabricante = :manufacturer,
                        unidade = :unit,

                        ncm = :ncm,
                        cest = :cest,
                        origem_mercadoria = :origin,
                        cfop_padrao = :default_cfop,
                        cst_icms = :icms_cst,
                        csosn = :csosn,
                        cst_pis = :pis_cst,
                        cst_cofins = :cofins_cst,
                        aliquota_icms = :icms_rate,
                        aliquota_pis = :pis_rate,
                        aliquota_cofins = :cofins_rate,
                        gtin_tributavel = :tax_gtin,
                        unidade_tributavel = :tax_unit,
                        cst_ibs_cbs = :ibs_cbs_cst,
                        classificacao_tributaria_ibs_cbs =
                            :ibs_cbs_classification,

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

        $statement->bindValue(
            'id',
            $id,
            PDO::PARAM_INT
        );

        $this->bindForm(
            $statement,
            $data
        );

        $statement->execute();
    }

    public function existsByBarcode(
        string $barcode,
        ?int $ignoreId = null
    ): bool {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return false;
        }

        $sql =
            'SELECT COUNT(*)
               FROM produtos
              WHERE codigo_barras = :barcode';

        $params = [
            'barcode' => $barcode,
        ];

        if ($ignoreId !== null) {
            $this->assertPositiveId(
                $ignoreId
            );

            $sql .=
                ' AND id <> :ignore_id';

            $params['ignore_id'] =
                $ignoreId;
        }

        $statement =
            $this->connection->prepare(
                $sql
            );

        $statement->execute(
            $params
        );

        return (int) (
            $statement->fetchColumn()
        ) > 0;
    }

    public function softDelete(
        int $id,
        int $userId
    ): void {
        $this->assertPositiveId($id);
        $this->assertPositiveId(
            $userId
        );

        $this->connection
            ->beginTransaction();

        try {
            $statement =
                $this->connection->prepare(
                    'SELECT
                        id,
                        estoque,
                        excluido_em
                     FROM produtos
                     WHERE id = :id
                     FOR UPDATE'
                );

            $statement->execute([
                'id' => $id,
            ]);

            $product =
                $statement->fetch();

            if (
                $product === false
                || $product['excluido_em']
                    !== null
            ) {
                throw new InvalidArgumentException(
                    'Produto não encontrado.'
                );
            }

            if (
                abs(
                    (float) $product['estoque']
                ) >= 0.0005
            ) {
                throw new InvalidArgumentException(
                    'Produto com saldo não pode ser excluído. Marque-o como inativo.'
                );
            }

            if (
                $this->hasOperationalHistory(
                    $id
                )
            ) {
                throw new InvalidArgumentException(
                    'Produto já utilizado não pode ser excluído. Marque-o como inativo para preservar o histórico.'
                );
            }

            $update =
                $this->connection->prepare(
                    'UPDATE produtos
                        SET
                            status = "inativo",
                            excluido_em =
                                CURRENT_TIMESTAMP,
                            excluido_por =
                                :user_id,
                            motivo_exclusao =
                                NULL

                      WHERE id = :id
                        AND excluido_em IS NULL'
                );

            $update->execute([
                'id' => $id,
                'user_id' => $userId,
            ]);

            if (
                $update->rowCount() !== 1
            ) {
                throw new InvalidArgumentException(
                    'Produto não encontrado.'
                );
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if (
                $this->connection
                    ->inTransaction()
            ) {
                $this->connection
                    ->rollBack();
            }

            throw $exception;
        }
    }

    private function hasOperationalHistory(
        int $id
    ): bool {
        $checks = [
            "SELECT 1
               FROM ordem_servico_itens
              WHERE tipo = 'produto'
                AND referencia_id = :id
              LIMIT 1",

            "SELECT 1
               FROM orcamento_itens
              WHERE tipo = 'produto'
                AND referencia_id = :id
              LIMIT 1",

            'SELECT 1
               FROM estoque_autorizacoes
              WHERE produto_id = :id
              LIMIT 1',

            'SELECT 1
               FROM estoque_movimentacoes
              WHERE produto_id = :id
              LIMIT 1',

            'SELECT 1
               FROM venda_avulsa_itens
              WHERE produto_id = :id
              LIMIT 1',
        ];

        foreach ($checks as $sql) {
            $statement =
                $this->connection->prepare(
                    $sql
                );

            $statement->execute([
                'id' => $id,
            ]);

            if (
                $statement->fetchColumn()
                !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function bindForm(
        PDOStatement $statement,
        ProductFormData $data
    ): void {
        $statement->bindValue(
            'name',
            $data->name()
        );

        $statement->bindValue(
            'description',
            $data->description()
        );

        $statement->bindValue(
            'category',
            $data->category()
        );

        $statement->bindValue(
            'manufacturer',
            $data->manufacturer()
        );

        $statement->bindValue(
            'unit',
            $data->unit()
        );

        $statement->bindValue(
            'ncm',
            $data->ncm()
        );

        $statement->bindValue(
            'cest',
            $data->cest()
        );

        $statement->bindValue(
            'origin',
            $data->origin(),
            $data->origin() === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_INT
        );

        $statement->bindValue(
            'default_cfop',
            $data->defaultCfop()
        );

        $statement->bindValue(
            'icms_cst',
            $data->icmsCst()
        );

        $statement->bindValue(
            'csosn',
            $data->csosn()
        );

        $statement->bindValue(
            'pis_cst',
            $data->pisCst()
        );

        $statement->bindValue(
            'cofins_cst',
            $data->cofinsCst()
        );

        $statement->bindValue(
            'icms_rate',
            $data->icmsRate()
        );

        $statement->bindValue(
            'pis_rate',
            $data->pisRate()
        );

        $statement->bindValue(
            'cofins_rate',
            $data->cofinsRate()
        );

        $statement->bindValue(
            'tax_gtin',
            $data->taxGtin()
        );

        $statement->bindValue(
            'tax_unit',
            $data->taxUnit()
        );

        $statement->bindValue(
            'ibs_cbs_cst',
            $data->ibsCbsCst()
        );

        $statement->bindValue(
            'ibs_cbs_classification',
            $data->ibsCbsClassification()
        );

        $statement->bindValue(
            'barcode',
            $data->barcode()
        );

        $statement->bindValue(
            'cost_price',
            $data->costPrice()
        );

        $statement->bindValue(
            'sale_price',
            $data->salePrice()
        );

        $statement->bindValue(
            'stock',
            $data->stock()
        );

        $statement->bindValue(
            'minimum_stock',
            $data->minimumStock()
        );

        $statement->bindValue(
            'location',
            $data->location()
        );

        $statement->bindValue(
            'status',
            $data->status()
        );
    }

    private function assertPositiveId(
        int $id
    ): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID inválido.'
            );
        }
    }
}