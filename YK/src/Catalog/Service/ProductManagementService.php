<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\DTO\ProductFormData;
use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductRepository;
use InvalidArgumentException;

final class ProductManagementService
{
    public function __construct(
        private readonly ProductRepository $products
    ) {
    }

    /** @return Product[] */
    public function listProducts(array $filters = []): array
    {
        return $this->products->findAll($filters);
    }

    /** @return array{total:int,active:int,low_stock:int,out_of_stock:int} */
    public function productSummary(): array
    {
        return $this->products->summary();
    }

    public function getProduct(int $id): Product
    {
        $product = $this->products->findById($id);

        if ($product === null) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        return $product;
    }

    public function createProduct(ProductFormData $data): Product
    {
        $this->assertUniqueBarcode($data->barcode());

        return $this->products->create($data);
    }

    public function updateProduct(
        int $id,
        ProductFormData $data
    ): void {
        $this->getProduct($id);
        $this->assertUniqueBarcode($data->barcode(), $id);
        $this->products->update($id, $data);
    }

    private function assertUniqueBarcode(
        ?string $barcode,
        ?int $ignoreId = null
    ): void {
        if ($barcode === null || trim($barcode) === '') {
            return;
        }

        if ($this->products->existsByBarcode($barcode, $ignoreId)) {
            throw new InvalidArgumentException(
                'Já existe um produto com este código de barras.'
            );
        }
    }
}
