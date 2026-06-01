<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use InvalidArgumentException;

final class ProductService
{
    private ProductRepository $products;
    private CategoryRepository $categories;

    public function __construct(?ProductRepository $products = null, ?CategoryRepository $categories = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->categories = $categories ?? new CategoryRepository();
    }

    public function list(int $empresaId, string $query = ''): array
    {
        return $this->products->findAll($empresaId, $query);
    }

    public function find(int $empresaId, int $id): ?array
    {
        return $this->products->findById($empresaId, $id);
    }

    public function findByCode(int $empresaId, string $code): ?array
    {
        return $this->products->findByCode($empresaId, $code);
    }

    public function save(int $empresaId, array $payload): array
    {
        $id = (int)($payload['id'] ?? 0);
        $name = trim((string)($payload['name'] ?? $payload['nome'] ?? ''));
        $category = trim((string)($payload['category'] ?? $payload['categoria'] ?? ''));
        $expiry = trim((string)($payload['expiry'] ?? $payload['validade'] ?? ''));
        $image = trim((string)($payload['image'] ?? ''));

        if (!Validator::required($name) || !Validator::max($name, 180)) {
            throw new InvalidArgumentException('Informe um nome de produto válido.');
        }

        if (!Validator::required($category) || !Validator::max($category, 120)) {
            throw new InvalidArgumentException('Informe uma categoria válida.');
        }

        if (!Validator::date($expiry)) {
            throw new InvalidArgumentException('Informe uma validade válida.');
        }

        foreach (['cost', 'price', 'stock', 'minStock'] as $field) {
            if (!Validator::decimal($payload[$field] ?? 0)) {
                throw new InvalidArgumentException('Valores de preço e estoque devem ser numéricos.');
            }
        }

        if ($image !== '' && (!Validator::max($image, 255) || preg_match('/(^|\/)\.\.(\/|$)|[<>:"\\\\|?*]/', $image))) {
            throw new InvalidArgumentException('Imagem do produto inválida.');
        }

        $categoryId = $this->categories->findOrCreate($empresaId, $category);
        $data = [
            'categoria_id' => $categoryId,
            'nome' => $name,
            'sku' => trim((string)($payload['sku'] ?? '')),
            'codigo_barras' => trim((string)($payload['barcode'] ?? $payload['codigo_barras'] ?? '')),
            'lote' => trim((string)($payload['lot'] ?? $payload['lote'] ?? '')),
            'validade' => $expiry,
            'quantidade' => (float)($payload['stock'] ?? 0),
            'estoque_minimo' => (float)($payload['minStock'] ?? 0),
            'preco_custo' => (float)($payload['cost'] ?? 0),
            'preco_venda' => (float)($payload['price'] ?? 0),
            'imagem' => $image,
        ];

        if ($id > 0) {
            $this->products->update($empresaId, $id, $data);
        } else {
            $id = $this->products->create($empresaId, $data);
        }

        return $this->products->findById($empresaId, $id) ?? [];
    }

    public function inactivate(int $empresaId, int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }

        $this->products->inactivate($empresaId, $id);
    }
}
