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

    public function deleteProduct(int $id, string $reason, int $userId): void
    {
        $reason = trim($reason);
        $length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reason === '' || $length > 255 || str_contains($reason, "\0") || $reason !== strip_tags($reason)) {
            throw new InvalidArgumentException('Informe um motivo válido com até 255 caracteres.');
        }

        $this->products->softDelete($id, $reason, $userId);
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
